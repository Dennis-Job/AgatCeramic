<?php

namespace App\Console\Commands;

use App\Services\CompromisedAdminAuthInvalidationService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class InvalidateCompromisedAdminAuthCommand extends Command
{
    public function __construct(private readonly CompromisedAdminAuthInvalidationService $invalidationService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('security:invalidate-compromised-admin-auth')
            ->setDescription('Invalidate all administrator passwords, sessions, reset tokens and remember tokens after an incident')
            ->addOption(
                'force',
                mode: InputOption::VALUE_NONE,
                description: 'Confirm the irreversible invalidation of all administrator credentials',
            );
    }

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->components->error('Refusing to invalidate administrator authentication without --force.');

            return self::FAILURE;
        }

        try {
            $result = $this->invalidationService->invalidate();
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error('Administrator authentication state could not be invalidated.');

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Invalidated %d administrator credential(s), %d session(s), and %d reset token(s).',
            $result['users'],
            $result['sessions'],
            $result['reset_tokens'],
        ));

        return self::SUCCESS;
    }
}
