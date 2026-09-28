<?php

use App\Models\PlatformUser;

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
