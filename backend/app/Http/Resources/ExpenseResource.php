<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category->value,
            'description' => $this->description,
            'amount' => $this->amount,
            'expense_date' => Concerns::date($this->expense_date),
            'status' => $this->status->value,
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? ['id' => $this->vehicle->id, 'registration_number' => $this->vehicle->registration_number] : null),
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? ['id' => $this->driver->id, 'name' => $this->driver->fullName()] : null),
            'delivery_id' => $this->delivery_id,
            'source' => $this->source_type,
            'receipt_url' => Concerns::file($this->receipt_path, 'expense-receipt', $this->id),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_by_id' => $this->created_by,
            'reviewed_by' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),
            'reviewed_at' => Concerns::dt($this->reviewed_at),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => Concerns::dt($this->created_at),
        ];
    }
}
