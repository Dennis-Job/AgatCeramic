<?php

namespace App\Console\Commands;

use App\Services\BootstrapSuperAdminService;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Validation\Rules\Password;
use Throwable;

class BootstrapSuperAdminCommand extends Command
{
    protected $signature = 'admin:bootstrap
        {--name=Super Admin : Display name of the initial administrator}
        {--email= : Email address of the initial administrator}
        {--password= : Password (prefer the hidden interactive prompt instead)}
        {--force : Permit execution in the production environment}';

    protected $description = 'Create the first Super Admin account when no staff accounts exist';

    public function __construct(private readonly BootstrapSuperAdminService $bootstrapService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->components->error('Refusing to bootstrap an administrator in production without --force.');

            return self::FAILURE;
        }

        $name = trim((string) $this->option('name'));
        $email = trim((string) ($this->option('email') ?: $this->ask('Email')));
        $password = (string) ($this->option('password') ?: $this->secret('Password'));

        $validator = validator(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email:rfc', 'max:255'],
                'password' => ['required', 'string', Password::min(8)],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        try {
            $user = $this->bootstrapService->create($name, $email, $password);
        } catch (DomainException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error('The initial administrator could not be created.');

            return self::FAILURE;
        }

        $this->components->info("Initial Super Admin created for {$user->email}.");

        return self::SUCCESS;
    }
}
