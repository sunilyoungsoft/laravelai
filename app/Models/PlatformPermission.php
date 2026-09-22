<?php

namespace App\Models;

use Database\Factories\PlatformPermissionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlatformPermission extends Model
{
    /** @use HasFactory<PlatformPermissionFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $connection = 'platform';

    protected $table = 'platform_permissions';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
        ];
    }

    protected static function newFactory(): PlatformPermissionFactory
    {
        return PlatformPermissionFactory::new();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            PlatformRole::class,
            'platform_role_permissions',
            'permission_id',
            'role_id'
        )->withTimestamps();
    }
}
