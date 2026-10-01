<?php

namespace App\Models;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Application-owned Platform Domain: maps a hostname to a Company.
 *
 * Platform data on the `platform` connection. System fields (company_id, is_primary, status,
 * verified_at, audit) are set by trusted application code, never mass-assigned from request
 * input. The `domain` value is the normalized hostname (see CreateDomainAction).
 */
class Domain extends Model
{
    /** @use HasFactory<DomainFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $connection = 'platform';

    protected $table = 'domains';

    /**
     * Only the hostname value is fillable. Ownership, primary flag, status, verification and
     * audit fields are application-controlled.
     */
    protected $fillable = [
        'domain',
    ];

    protected function casts(): array
    {
        return [
            'type' => DomainType::class,
            'status' => DomainStatus::class,
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    protected static function newFactory(): DomainFactory
    {
        return DomainFactory::new();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
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
