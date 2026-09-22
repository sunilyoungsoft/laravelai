<?php

namespace App\Models;

use Database\Factories\PlatformRoleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlatformRole extends Model
{
    /** @use HasFactory<PlatformRoleFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $connection = 'platform';

    protected $table = 'platform_roles';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'status' => 'integer',
        ];
    }

    protected static function newFactory(): PlatformRoleFactory
    {
        return PlatformRoleFactory::new();
    }

    public function isAdminSystemRole(): bool
    {
        return $this->is_system === true && $this->slug === 'admin';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            PlatformUser::class,
            'platform_user_roles',
            'role_id',
            'user_id'
        )->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            PlatformPermission::class,
            'platform_role_permissions',
            'role_id',
            'permission_id'
        )->withTimestamps();
    }
}
