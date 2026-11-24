<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = ['company_id', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
