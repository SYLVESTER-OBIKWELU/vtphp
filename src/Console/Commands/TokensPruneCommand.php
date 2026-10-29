<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use VtPhp\Auth\PersonalAccessToken;
use VtPhp\Console\Command;

/**
 * Deletes API tokens that can no longer be used: past their own expires_at,
 * or older than `auth.tokens.expiration` minutes when that is set.
 */
#[AsCommand(name: 'tokens:prune', description: 'Delete expired API tokens')]
final class TokensPruneCommand extends Command
{
    protected function handle(): int
    {
        $now = now();
        $query = PersonalAccessToken::query()->where('expires_at', '<=', $now);

        $maxMinutes = $this->app->make('config')->get('auth.tokens.expiration');

        if (is_numeric($maxMinutes) && (int) $maxMinutes > 0) {
            $query->orWhere('created_at', '<=', $now->modify('-'.(int) $maxMinutes.' minutes'));
        }

        $deleted = $query->delete();

        $this->info("Deleted {$deleted} expired token(s).");

        return self::SUCCESS;
    }
}
