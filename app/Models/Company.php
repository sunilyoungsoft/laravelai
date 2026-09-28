<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use App\Tenancy\Concerns\CompanyIsStanclTenant;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class Company extends Model implements TenantWithDatabase
{
    /** @use HasFactory<CompanyFactory> */
    use CompanyIsStanclTenant, HasFactory, HasUlids, SoftDeletes;

    protected $connection = 'platform';

    protected $table = 'companies';

    /**
     * Profile fields only. System fields (database_name, status, audit)
     * must be set explicitly by trusted application code.
     */
    protected $fillable = [
        'name',
        'slug',
        'contact_person',
        'email',
        'secondary_email',
        'phone',
        'gst_number',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'country_code',
        'postal_code',
    ];

    protected function casts(): array
    {
        return [
            'status' => CompanyStatus::class,
        ];
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'updated_by');
    }
}
