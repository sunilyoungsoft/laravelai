<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | Application-wide password hashing uses Argon2id (confirmed available on
    | the installed PHP via PASSWORD_ARGON2ID). Both Platform Users and
    | Workspace Users hash through Laravel's Hash abstraction (the `hashed`
    | model cast and Auth::attempt both read this driver). Never implement
    | custom hashing; never store plaintext.
    |
    | Supported: "bcrypt", "argon", "argon2id"
    |
    */

    'driver' => env('HASH_DRIVER', 'argon2id'),

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | Retained so pre-existing bcrypt hashes (e.g. the first Platform Admin
    | created before Argon2id was configured) still verify. With rehash on
    | login enabled, those hashes upgrade to Argon2id on the next successful
    | login.
    |
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', true),
        'limit' => env('BCRYPT_LIMIT', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    |
    | Conservative defaults suitable for interactive login hashing on commodity
    | hardware: 64 MiB memory, 4 iterations, single thread. Tunable per
    | environment via env without touching code. These apply to both the
    | `argon` and `argon2id` drivers.
    |
    */

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),

        // Must stay false while legacy bcrypt hashes still exist. With verify
        // enabled, the Argon2id hasher refuses to check a non-Argon2id hash and
        // throws, which would break login for accounts created under bcrypt
        // (e.g. the first Platform Admin). With it off, check() delegates to
        // password_verify(), which handles any stored algorithm, and
        // needsRehash() still upgrades those hashes to Argon2id on next login.
        'verify' => env('HASH_VERIFY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rehash On Login
    |--------------------------------------------------------------------------
    |
    | On a successful login, Laravel transparently rehashes a password whose
    | stored hash does not match the configured algorithm/work factor. This is
    | what upgrades legacy bcrypt hashes to Argon2id without any custom code.
    |
    */

    'rehash_on_login' => true,

];
