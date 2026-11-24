<?php

namespace App\Services\Finance;

use App\Enums\DeliveryStatus;
use App\Enums\InvoiceStatus;
use App\Exceptions\DomainException;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Tenant\CompanySettings;
use App\Support\Money;
use App\Support\Reference;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** All amounts are computed here from delivery fees / line items — never accepted as totals from the client. */
class InvoiceService
{
    public function __construct(
        private readonly CompanySettings $settings,
        private readonly TenantContext $tenant,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  list<int>  $deliveryIds
     * @param  list<array{description:string,quantity:numeric,unit_price:numeric}>  $lines
     */
    public function create(Customer $customer, array $deliveryIds, array $lines, User $by, array $meta = []): Invoice
    {
        $company = $this->tenant->companyOrFail();
        if (! $deliveryIds && ! $lines) {
            throw new DomainException('Add at least one delivery or line item.', 'invoice_empty');
        }

        try {
            return DB::transaction(function () use ($company, $customer, $deliveryIds, $lines, $by, $meta) {
                $deliveries = Delivery::whereIn('id', $deliveryIds)->lockForUpdate()->get();
                if ($deliveries->count() !== count(array_unique($deliveryIds))) {
                    throw new DomainException('Some deliveries were not found.', 'not_found', 404);
                }
                foreach ($deliveries as $d) {
                    if ($d->customer_id !== $customer->id) {
                        throw new DomainException("{$d->reference_number} belongs to another customer.", 'customer_mismatch');
                    }
                    if ($d->status !== DeliveryStatus::Delivered) {
                        throw new DomainException("{$d->reference_number} is not delivered yet.", 'delivery_not_billable');
                    }
                    if ($d->invoiceItem()->exists()) {
                        throw new DomainException("{$d->reference_number} is already invoiced.", 'delivery_invoiced', 409);
                    }
                }

                $invoice = new Invoice([
                    'customer_id' => $customer->id,
                    'due_date' => $meta['due_date'] ?? now()->addDays((int) $this->settings->get($company, 'invoice_due_days'))->toDateString(),
                    'notes' => $meta['notes'] ?? null,
                    'tax_rate' => $meta['tax_rate'] ?? $this->settings->get($company, 'default_tax_rate'),
                ]);
                $invoice->currency = $company->currency;
                $invoice->created_by = $by->id;
                $invoice->invoice_number = Reference::unique('INV', fn ($r) => Invoice::where('invoice_number', $r)->exists());
                $invoice->save();

                foreach ($deliveries as $d) {
                    $invoice->items()->create([
                        'delivery_id' => $d->id,
                        'description' => "Delivery {$d->reference_number} — {$d->delivery_address}",
                        'quantity' => 1,
                        'unit_price' => $d->delivery_fee,
                        'total_price' => $d->delivery_fee,
                    ]);
                }
                foreach ($lines as $l) {
                    $unit = Money::fromCents(Money::toCents((string) $l['unit_price']));
                    $invoice->items()->create([
                        'description' => $l['description'],
                        'quantity' => $l['quantity'],
                        'unit_price' => $unit,
                        'total_price' => Money::mul($unit, $l['quantity']),
                    ]);
                }
                $this->recalculate($invoice);
                $this->logger->log('invoice.created', $invoice, ['total' => $invoice->total_amount, 'number' => $invoice->invoice_number]);

                return $invoice->load('items', 'customer');
            });
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique')) {
                throw new DomainException('One of these deliveries was just invoiced by someone else.', 'delivery_invoiced', 409);
            }
            throw $e;
        }
    }

    public function recalculate(Invoice $invoice): Invoice
    {
        $subtotal = Money::add(...$invoice->items()->pluck('total_price')->all());
        $tax = Money::percent($subtotal, $invoice->tax_rate);
        $invoice->forceFill([
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => Money::add($subtotal, $tax),
        ])->save();

        return $invoice;
    }

    public function issue(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new DomainException('Only draft invoices can be issued.', 'invalid_transition');
        }
        $invoice->forceFill(['status' => InvoiceStatus::Issued, 'issued_at' => now()])->save();
        $this->logger->log('invoice.issued', $invoice, ['total' => $invoice->total_amount]);

        return $invoice;
    }

    public function void(Invoice $invoice, string $reason): Invoice
    {
        if (Money::cmp($invoice->amount_paid, 0) > 0) {
            throw new DomainException('Invoices with payments cannot be voided.', 'invoice_paid');
        }
        DB::transaction(function () use ($invoice, $reason) {
            $invoice->items()->update(['delivery_id' => null]); // deliveries become billable again
            $invoice->forceFill(['status' => InvoiceStatus::Void, 'notes' => trim(($invoice->notes ?? '')."\nVoided: {$reason}")])->save();
        });
        $this->logger->log('invoice.voided', $invoice, ['reason' => $reason]);

        return $invoice;
    }

    public function recordPayment(Invoice $invoice, array $data, User $by): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $by) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if (! in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true)) {
                throw new DomainException('Payments can only be recorded on issued invoices.', 'invoice_not_payable');
            }
            $amount = Money::toCents((string) $data['amount']);
            $balance = Money::toCents($invoice->balance());
            if ($amount <= 0 || $amount > $balance) {
                throw new DomainException('The amount must be between 0.01 and the balance due ('.Money::fromCents($balance).').', 'invalid_amount');
            }

            $payment = new Payment([
                'invoice_id' => $invoice->id,
                'amount' => Money::fromCents($amount),
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
            ]);
            $payment->recorded_by = $by->id;
            $payment->save();

            $paid = Money::add($invoice->amount_paid, $payment->amount);
            $invoice->forceFill([
                'amount_paid' => $paid,
                'status' => Money::cmp($paid, $invoice->total_amount) >= 0 ? InvoiceStatus::Paid : InvoiceStatus::PartiallyPaid,
            ])->save();
            $this->logger->log('payment.recorded', $invoice, ['amount' => $payment->amount, 'method' => $payment->payment_method->value]);

            return $payment;
        });
    }
}
