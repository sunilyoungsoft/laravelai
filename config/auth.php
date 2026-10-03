<?php

use App\Models\PlatformUser;
use App\Models\WorkspaceUser;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Default guard is `platform` until Workspace auth exists.
    | PlatformUser belongs only to the platform auth system.
    |
    | When Workspace auth is implemented:
    |   platform guard -> PlatformUser
    |   web guard      -> WorkspaceUser
    |
    | Future Platform auth code must use Auth::guard('platform') explicitly.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'platform'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */

    'guards' => [
        'platform' => [
            'driver' => 'session',
            'provider' => 'platform_users',
        ],

        // Workspace (tenant) authentication. Its provider resolves WorkspaceUser on the
        // DEFAULT connection, which is the dynamic `tenant` connection only while Stancl
        // tenancy is initialized — so this guard is usable only behind `tenant.resolve`
        // on a resolved Company host. Workspace code must name this guard explicitly
        // (Auth::guard('workspace')); the application default guard stays `platform`.
        'workspace' => [
            'driver' => 'session',
            'provider' => 'workspace_users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'platform_users' => [
            'driver' => 'eloquent',
            'model' => PlatformUser::class,
        ],

        'workspace_users' => [
            'driver' => 'eloquent',
            'model' => WorkspaceUser::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | Platform password recovery is not implemented yet. Do not configure a
    | password broker against a missing password_reset_tokens table.
    |
    */

    'passwords' => [],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
