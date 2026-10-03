<?php

namespace App\Models;

use Database\Factories\WorkspacePermissionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A permission within a single Company's workspace (tenant) database (1G).
 *
 * Mirrors PlatformPermission but is tenant-resident (NO explicit connection → dynamic
 * `tenant` connection). Slugs follow the `{module}.{action}` convention (e.g.
 * workspace.access). Phase 1G seeds only proof permissions; module permissions are added
 * by each module later and assigned to roles explicitly.
 */
class WorkspacePermission extends Model
{
    /** @use HasFactory<WorkspacePermissionFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'workspace_permissions';

    /**
     * status is not mass assignable — set only by trusted system code.
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
        ];
    }

    protected static function newFactory(): WorkspacePermissionFactory
    {
        return WorkspacePermissionFactory::new();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkspaceRole::class,
            'workspace_role_permissions',
            'permission_id',
            'role_id'
        )->withTimestamps();
    }
}
