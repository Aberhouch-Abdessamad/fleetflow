<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Finance\InvoiceRequest;
use App\Http\Requests\Api\V1\Finance\PaymentRequest;
use App\Http\Resources\DeliveryResource;
use App\Http\Resources\InvoiceResource;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Services\Finance\InvoiceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $service) {}

    public function index(Request $request): JsonResponse
    {
        $q = Invoice::with('customer')
            ->when($request->query('status'), fn ($q, $s) => $q->whereIn('status', explode(',', $s)))
            ->when($request->query('customer_id'), fn ($q, $c) => $q->where('customer_id', $c))
            ->when($request->query('search'), fn ($q, $s) => $q->where('invoice_number', 'like', $this->like($s)))
            ->when($request->boolean('overdue'), fn ($q) => $q->whereIn('status', ['issued', 'partially_paid'])->where('due_date', '<', now()->toDateString()));

        return $this->paginated($this->sort($q, $request, ['created_at', 'due_date', 'total_amount'], '-created_at'), InvoiceResource::class, $request);
    }

    /** Delivered, not-yet-invoiced deliveries for a customer (invoice builder). */
    public function billable(Request $request): JsonResponse
    {
        $request->validate(['customer_id' => ['required', 'integer']]);
        $customer = Customer::findOrFail($request->query('customer_id'));
        $rows = Delivery::where('customer_id', $customer->id)->where('status', 'delivered')->whereDoesntHave('invoiceItem')
            ->latest('actual_delivery_at')->limit(500)->get();

        return ApiResponse::ok(DeliveryResource::collection($rows));
    }

    public function store(InvoiceRequest $request): JsonResponse
    {
        $v = $request->validated();
        $invoice = $this->service->create(Customer::findOrFail($v['customer_id']), $v['delivery_ids'] ?? [], $v['lines'] ?? [], $request->user(), $v);

        return ApiResponse::created(new InvoiceResource($invoice->load('items', 'payments', 'customer')), 'Invoice created.');
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return ApiResponse::ok(new InvoiceResource($invoice->load('items', 'payments', 'customer')));
    }

    public function issue(Invoice $invoice): JsonResponse
    {
        return ApiResponse::ok(new InvoiceResource($this->service->issue($invoice)->load('items', 'payments', 'customer')), 'Invoice issued.');
    }

    public function void(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return ApiResponse::ok(new InvoiceResource($this->service->void($invoice, $data['reason'])->load('items', 'payments', 'customer')), 'Invoice voided.');
    }

    public function payment(PaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $this->service->recordPayment($invoice, $request->validated(), $request->user());

        return ApiResponse::created(new InvoiceResource($invoice->fresh(['items', 'payments', 'customer'])), 'Payment recorded.');
    }
}
