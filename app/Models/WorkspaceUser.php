<?php

namespace App\Models;

use Database\Factories\WorkspaceUserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
}
