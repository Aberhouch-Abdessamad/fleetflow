<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class IntegrationRequest extends Model
{
    use BelongsToCompany;

    protected $fillable = ['api_client_id', 'idempotency_key', 'endpoint', 'request_hash', 'response_status', 'response_body', 'delivery_id'];

    protected function casts(): array
    {
        return ['response_body' => 'array'];
    }
}
