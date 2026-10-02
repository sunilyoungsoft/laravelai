<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Argon2id is the application-wide password hashing algorithm (1F-A).
 *
 * Hashing goes through Laravel's Hash abstraction with the configured Argon2id
 * driver — never custom hashing. Pre-existing bcrypt hashes must keep verifying
 * so accounts created before this change (e.g. the first Platform Admin) still
 * log in and upgrade on next login via rehash_on_login.
 */
class HashingConfigTest extends TestCase
{
    public function test_default_driver_is_argon2id(): void
    {
        $this->assertSame('argon2id', config('hashing.driver'));
    }

    public function test_hash_make_produces_an_argon2id_hash(): void
    {
        $hash = Hash::make('correct-horse-battery-staple');

        $info = Hash::info($hash);

        $this->assertSame('argon2id', $info['algoName']);
        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertTrue(Hash::check('correct-horse-battery-staple', $hash));
    }

    public function test_hash_make_never_returns_plaintext(): void
    {
        $plain = 'correct-horse-battery-staple';

        $this->assertNotSame($plain, Hash::make($plain));
    }

    public function test_existing_bcrypt_hash_still_verifies(): void
    {
        // A hash produced by the bcrypt algorithm before Argon2id was configured.
        $bcryptHash = password_hash('legacy-secret', PASSWORD_BCRYPT);

        $this->assertSame('bcrypt', Hash::info($bcryptHash)['algoName']);
        $this->assertTrue(Hash::check('legacy-secret', $bcryptHash));
        $this->assertFalse(Hash::check('wrong-secret', $bcryptHash));
    }

    public function test_bcrypt_hash_needs_rehash_under_argon2id_driver(): void
    {
        // Graceful upgrade path: a bcrypt hash is flagged for rehash so a
        // successful login re-stores it as Argon2id (rehash_on_login = true).
        $bcryptHash = password_hash('legacy-secret', PASSWORD_BCRYPT);

        $this->assertTrue(Hash::needsRehash($bcryptHash));
    }
}
