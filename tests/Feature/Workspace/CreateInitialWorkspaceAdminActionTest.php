<?php

namespace Tests\Feature\Workspace;

use App\Actions\Workspace\CreateInitialWorkspaceAdminAction;
use App\Actions\Workspace\CreateInitialWorkspaceAdminData;
use App\Models\WorkspaceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Feature\Workspace\Support\ProvisionsWorkspaces;
use Tests\TestCase;

/**
 * The initial Workspace Admin is created with a one-time temporary password (1F-E).
 *
 * Verifies the Action stores only an Argon2id hash, flags the forced first-login change,
 * returns the plaintext temp password exactly once, never logs it, and refuses to create
 * a second initial admin.
 */
class CreateInitialWorkspaceAdminActionTest extends TestCase
{
    use ProvisionsWorkspaces, RefreshDatabase;

    private function action(): CreateInitialWorkspaceAdminAction
    {
        return app(CreateInitialWorkspaceAdminAction::class);
    }

    public function test_creates_admin_with_argon2id_hash_and_returns_temp_password_once(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            $result = $this->action()->execute(
                new CreateInitialWorkspaceAdminData('Acme Admin', 'admin@acme.test')
            );

            // A plaintext temp password is returned exactly once to the caller.
            $this->assertNotEmpty($result->temporaryPassword);

            $stored = WorkspaceUser::query()->where('email', 'admin@acme.test')->firstOrFail();

            // Only an Argon2id hash is persisted — never the plaintext.
            $this->assertNotSame($result->temporaryPassword, $stored->password);
            $this->assertSame('argon2id', password_get_info($stored->password)['algoName']);
            $this->assertTrue(Hash::check($result->temporaryPassword, $stored->password));

            // Forced first-login change is flagged.
            $this->assertTrue($stored->must_change_password);
            $this->assertSame(1, $stored->status);
        });
    }

    public function test_temporary_password_is_never_written_to_the_log(): void
    {
        $company = $this->provisionCompany();

        $spy = Log::spy();

        $temp = $company->run(function () {
            return $this->action()->execute(
                new CreateInitialWorkspaceAdminData('Acme Admin', 'admin@acme.test')
            )->temporaryPassword;
        });

        // No log call may contain the plaintext temporary password.
        $spy->shouldNotHaveReceived('log', function (string $level, string $message, array $context = []) use ($temp): bool {
            return str_contains($message, $temp)
                || str_contains(json_encode($context), $temp);
        });
    }

    public function test_refuses_to_create_a_second_initial_admin(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            $this->action()->execute(
                new CreateInitialWorkspaceAdminData('First Admin', 'first@acme.test')
            );

            $this->expectException(RuntimeException::class);

            $this->action()->execute(
                new CreateInitialWorkspaceAdminData('Second Admin', 'second@acme.test')
            );
        });

        $company->run(function () {
            $this->assertSame(1, WorkspaceUser::query()->count());
        });
    }

    public function test_validates_email_format(): void
    {
        $company = $this->provisionCompany();

        $company->run(function () {
            $this->expectException(ValidationException::class);

            $this->action()->execute(
                new CreateInitialWorkspaceAdminData('Bad Email', 'not-an-email')
            );
        });
    }
}
