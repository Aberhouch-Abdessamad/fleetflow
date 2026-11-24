<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** Minimal tenant-owned model used to test the BelongsToCompany scope. */
class TenantNote extends Model
{
    use BelongsToCompany;

    protected $table = 'tenant_notes';

    protected $fillable = ['body', 'company_id'];
}
