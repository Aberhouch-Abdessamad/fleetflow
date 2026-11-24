<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryItem extends Model
{
    protected $fillable = ['description', 'quantity', 'weight', 'dimensions', 'notes'];

    protected function casts(): array
    {
        return ['weight' => 'decimal:2', 'quantity' => 'integer'];
    }
}
