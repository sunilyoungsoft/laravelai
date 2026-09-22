<?php

use App\Models\PlatformUser;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Phase 1B: the default guard is `platform` because Workspace auth does
    | not exist yet. Platform and Workspace authentication remain separate.
    | Future Platform auth must use Auth::guard('platform') explicitly.
    | When Workspace auth is built, `web` can become the Workspace browser guard.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'platform'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'platform_users'),
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

        // Reserved for Workspace browser auth later. Temporarily unused.
        'web' => [
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
    | Password reset is not implemented in Phase 1B. This broker is named
    | for the platform provider only.
    |
    */

    'passwords' => [
        'platform_users' => [
            'provider' => 'platform_users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
