<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'customer' => $this->whenLoaded('customer', fn () => ['id' => $this->customer->id, 'name' => $this->customer->name, 'email' => $this->customer->email, 'tax_number' => $this->customer->tax_number]),
            'status' => $this->status->value,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'amount_paid' => $this->amount_paid,
            'balance' => $this->balance(),
            'due_date' => Concerns::date($this->due_date),
            'overdue' => in_array($this->status->value, ['issued', 'partially_paid'], true) && $this->due_date?->isPast(),
            'issued_at' => Concerns::dt($this->issued_at),
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id, 'delivery_id' => $i->delivery_id, 'description' => $i->description, 'quantity' => $i->quantity, 'unit_price' => $i->unit_price, 'total_price' => $i->total_price,
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id, 'amount' => $p->amount, 'payment_method' => $p->payment_method->value, 'reference' => $p->reference, 'paid_at' => Concerns::dt($p->paid_at),
            ])),
            'created_at' => Concerns::dt($this->created_at),
        ];
    }
}
