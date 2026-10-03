<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Feature tests for the Platform admin session auth flow (Req A1–A5).
 *
 * Covers login success -> dashboard, generic non-disclosing credential errors,
 * logout ending the session, unauthenticated access to protected routes being
 * redirected to login, and the absence of registration / password-reset surfaces.
 */
class PlatformAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials_redirects_to_dashboard_and_authenticates(): void
    {
        $user = PlatformUser::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'correct-password',
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('platform.dashboard'));
        $this->assertAuthenticatedAs($user, 'platform');
    }

    public function test_legacy_bcrypt_user_can_log_in_and_password_is_rehashed_to_argon2id(): void
    {
        // Simulate an account created before Argon2id was configured. The `hashed`
        // cast guards writes against the configured algorithm, so seed the legacy
        // bcrypt hash straight into the row via the query builder to represent a
        // pre-existing record.
        $user = PlatformUser::factory()->create(['email' => 'legacy@example.com']);
        PlatformUser::on('platform')->whereKey($user->id)->update([
            'password' => password_hash('legacy-password', PASSWORD_BCRYPT),
        ]);

        $this->assertSame('bcrypt', password_get_info($user->fresh()->password)['algoName']);

        $response = $this->post('/login', [
            'email' => 'legacy@example.com',
            'password' => 'legacy-password',
        ]);

        $response->assertRedirect(route('platform.dashboard'));
        $this->assertAuthenticatedAs($user, 'platform');

        // rehash_on_login upgraded the stored hash to Argon2id (no custom code).
        $this->assertSame('argon2id', password_get_info($user->fresh()->password)['algoName']);
    }

    public function test_invalid_credentials_return_generic_error_and_leave_user_unauthenticated(): void
    {
        PlatformUser::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'correct-password',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('platform');

        // The error must be the generic credential message and must not disclose
        // which field was wrong (no password-specific error).
        $errors = session('errors');
        $this->assertSame(__('auth.failed'), $errors->first('email'));
        $this->assertFalse($errors->has('password'));
    }

    public function test_login_fails_for_unknown_email_without_disclosing_the_field(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'any-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest('platform');
    }

    public function test_logout_ends_the_session_and_redirects_to_login(): void
    {
        $user = PlatformUser::factory()->create();

        $response = $this->actingAs($user, 'platform')->post('/logout');

        $response->assertRedirect(route('platform.login'));
        $this->assertGuest('platform');
    }

    public function test_unauthenticated_access_to_a_protected_route_redirects_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('platform.login'));
        $this->assertGuest('platform');
    }

    public function test_unauthenticated_logout_request_redirects_to_login(): void
    {
        $response = $this->post('/logout');

        $response->assertRedirect(route('platform.login'));
        $this->assertGuest('platform');
    }

    public function test_login_form_is_reachable_by_guests(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_authenticated_user_visiting_the_login_form_is_redirected_to_dashboard(): void
    {
        $user = PlatformUser::factory()->create();

        $this->actingAs($user, 'platform')
            ->get('/login')
            ->assertRedirect(route('platform.dashboard'));
    }

    public function test_no_registration_or_password_reset_routes_exist(): void
    {
        // Req A5: no registration, password-reset, or 2FA surfaces exist.
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('platform.register'));
        $this->assertFalse(Route::has('password.request'));
        $this->assertFalse(Route::has('password.reset'));
        $this->assertFalse(Route::has('password.email'));
        $this->assertFalse(Route::has('platform.password.request'));
    }
}
