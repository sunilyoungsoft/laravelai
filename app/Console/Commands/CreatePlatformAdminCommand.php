<?php

namespace App\Console\Commands;

use App\Services\Platform\CreatePlatformAdminService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreatePlatformAdminCommand extends Command
{
    protected $signature = 'platform:create-admin';

    protected $description = 'Bootstrap the first Platform Admin user (one-time only)';

    public function handle(CreatePlatformAdminService $service): int
    {
        $this->info('Create Platform Admin (bootstrap only)');
        $this->newLine();

        $name = (string) $this->ask('Name');
        $email = (string) $this->ask('Email');
        $phone = (string) $this->ask('Phone');
        $password = (string) $this->secret('Password');
        $passwordConfirmation = (string) $this->secret('Confirm password');

        try {
            $user = $service->create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Platform Admin created successfully.');
        $this->line('ID: '.$user->id);
        $this->line('Email: '.$user->email);

        return self::SUCCESS;
    }
}
