<?php

namespace App\Models;

use Database\Factories\WorkspaceUserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A user that belongs to a single Company's workspace (tenant) database (1F-B).
 *
 * This model intentionally sets NO explicit connection: it resolves on the default
 * connection, which is the dynamic `tenant` connection while Stancl tenancy is
 * initialized. It is therefore only usable inside tenant context (behind
 * `tenant.resolve`), and each Company's workspace database holds its own isolated
 * set of workspace users — a WorkspaceUser in Company A is invisible to Company B.
 *
 * Authentication uses the `workspace` guard. Passwords hash through Laravel's Hash
 * abstraction (Argon2id) via the `hashed` cast — never custom hashing, never plaintext.
 */
class WorkspaceUser extends Authenticatable
{
    /** @use HasFactory<WorkspaceUserFactory> */
    use HasFactory, HasUlids, Notifiable, SoftDeletes;

    protected $table = 'workspace_users';

    /**
     * Identity/credential fields only. Lifecycle/state fields
     * (status, must_change_password, last_login_at) are set by trusted code.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'status' => 'integer',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function newFactory(): WorkspaceUserFactory
    {
        return WorkspaceUserFactory::new();
    }

    /**
     * Roles held by this workspace user (tenant-resident, 1G).
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkspaceRole::class,
            'workspace_user_roles',
            'user_id',
            'role_id'
        )->withTimestamps();
    }

    /**
     * True when the user holds the active Workspace Admin system role
     * (slug=workspace-admin, is_system=true, status active). This is the Workspace
     * analogue of PlatformUser::isPlatformAdmin() and backs the Gate::before
     * short-circuit — system-role authority, not a wildcard permission.
     */
    public function isWorkspaceAdmin(): bool
    {
        return $this->roles->contains(
            fn (WorkspaceRole $role): bool => $role->isAdminSystemRole() && $role->status === 1
        );
    }

    /**
     * True when the user holds an active role with the given slug.
     */
    public function hasRole(string $slug): bool
    {
        return $this->roles->contains(
            fn (WorkspaceRole $role): bool => $role->slug === $slug && $role->status === 1
        );
    }

    /**
     * True when the user holds an active permission (by slug) through any of their active
     * roles. Resolved entirely within the tenant database — never from Platform RBAC.
     */
    public function hasPermission(string $slug): bool
    {
        return $this->roles()
            ->where('workspace_roles.status', 1)
            ->whereHas('permissions', function ($query) use ($slug): void {
                $query->where('workspace_permissions.slug', $slug)
                    ->where('workspace_permissions.status', 1);
            })
            ->exists();
    }
}
