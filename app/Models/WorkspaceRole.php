<?php

namespace App\Models;

use Database\Factories\WorkspaceRoleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A role within a single Company's workspace (tenant) database (1G).
 *
 * Mirrors PlatformRole but is tenant-resident: it sets NO explicit connection, so it
 * resolves on the dynamic `tenant` connection and is only usable inside tenant context.
 * Workspace authorization data is fully isolated per company and never shares storage
 * with Platform RBAC.
 *
 * The protected `workspace-admin` system role (is_system=true) is authorized via a
 * Gate::before short-circuit; ordinary roles carry explicit permissions (no wildcards).
 */
class WorkspaceRole extends Model
{
    /** @use HasFactory<WorkspaceRoleFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'workspace_roles';

    /**
     * is_system and status are not mass assignable — set only by trusted system code.
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'status' => 'integer',
        ];
    }

    protected static function newFactory(): WorkspaceRoleFactory
    {
        return WorkspaceRoleFactory::new();
    }

    /**
     * The protected Workspace Admin system role: slug=workspace-admin, is_system=true.
     */
    public function isAdminSystemRole(): bool
    {
        return $this->is_system === true && $this->slug === 'workspace-admin';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkspaceUser::class,
            'workspace_user_roles',
            'role_id',
            'user_id'
        )->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkspacePermission::class,
            'workspace_role_permissions',
            'role_id',
            'permission_id'
        )->withTimestamps();
    }
}
