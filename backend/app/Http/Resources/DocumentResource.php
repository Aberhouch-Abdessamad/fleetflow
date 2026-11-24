<?php

namespace App\Http\Resources;

use App\Models\VehicleDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Vehicle or driver document. */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $kind = $this->resource instanceof VehicleDocument ? 'vehicle-document' : 'driver-document';
        $expired = $this->expiry_date?->isPast();

        return [
            'id' => $this->id,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'file_url' => Concerns::file($this->file_path, $kind, $this->id),
            'original_name' => $this->original_name,
            'issue_date' => Concerns::date($this->issue_date),
            'expiry_date' => Concerns::date($this->expiry_date),
            'status' => $expired ? 'expired' : ($this->expiry_date && $this->expiry_date->lte(now()->addDays(30)) ? 'expiring' : 'valid'),
            'created_at' => Concerns::dt($this->created_at),
        ];
    }
}
