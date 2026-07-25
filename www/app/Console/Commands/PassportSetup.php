<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;
use RuntimeException;

/**
 * Idempotently prepares everything createToken() needs: the encryption keys
 * and a personal access client. Safe to run on every bootstrap and in CI, so
 * the production path and the test path share the exact same setup.
 */
class PassportSetup extends Command
{
    protected $signature = 'passport:setup {--provider=users : The user provider the personal access client is bound to}';

    protected $description = 'Ensure Passport keys and a personal access client exist (idempotent).';

    public function handle(ClientRepository $clients): int
    {
        $this->ensureKeys();
        $this->ensurePersonalAccessClient($clients, (string) $this->option('provider'));

        return self::SUCCESS;
    }

    private function ensureKeys(): void
    {
        if (filled(config('passport.private_key')) && filled(config('passport.public_key'))) {
            $this->info('Passport keys are supplied through configuration.');

            return;
        }

        if (file_exists(storage_path('oauth-private.key'))) {
            $this->info('Passport keys already present.');

            return;
        }

        $this->call('passport:keys', ['--no-interaction' => true]);
    }

    private function ensurePersonalAccessClient(ClientRepository $clients, string $provider): void
    {
        try {
            $clients->personalAccessClient($provider);
            $this->info('Personal access client already present.');
        } catch (RuntimeException) {
            $client = $clients->createPersonalAccessGrantClient(
                config('app.name').' Personal Access Client',
                $provider,
            );

            $this->info("Created personal access client [{$client->getKey()}].");
        }
    }
}
