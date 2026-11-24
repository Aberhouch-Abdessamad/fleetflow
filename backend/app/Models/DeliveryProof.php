<?php

namespace App\Models;

use App\Enums\ProofType;
use Illuminate\Database\Eloquent\Model;

class DeliveryProof extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['driver_id', 'proof_type', 'file_path', 'recipient_name', 'recipient_signature_path', 'latitude', 'longitude', 'notes'];

    protected function casts(): array
    {
        return ['proof_type' => ProofType::class, 'latitude' => 'float', 'longitude' => 'float', 'created_at' => 'datetime'];
    }
}
