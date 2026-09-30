<?php

namespace Modules\Demo\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Temporary architecture-proof model stored on the platform connection.
 * Real CRM/ERP entities belong in Workspace databases after tenancy provisioning.
 */
class DemoNote extends Model
{
    use HasUlids;

    protected $connection = 'platform';

    protected $table = 'demo_notes';

    protected $fillable = [
        'title',
        'body',
    ];
}
