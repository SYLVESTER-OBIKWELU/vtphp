<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\ResetPasswordEmail;
use App\Models\PasswordResetToken;
use App\Repositories\UserRepositoryInterface;
use VtPhp\Exceptions\ValidationException;
use VtPhp\Mail\Mailer;

final class PasswordResetService
{
    private const TOKEN_TTL_MINUTES = 60;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly Mailer $mailer,
    ) {
    }

    public function forgot(string $email): void
    {
        $user = $this->users->findByEmail($email);

        // Always behave the same way whether or not the email is registered,
        // to avoid leaking account existence (user enumeration).
        if ($user === null) {
            return;
        }

        $token = bin2hex(random_bytes(32));

        PasswordResetToken::query()->updateOrCreate(
            ['email' => $user->email],
            ['token' => hash('sha256', $token), 'created_at' => now()],
        );

        $this->mailer->send(new ResetPasswordEmail($user, $token));
    }

    public function reset(string $email, string $token, string $password): void
    {
        /** @var PasswordResetToken|null $record */
        $record = PasswordResetToken::query()->find($email);

        if ($record === null || !hash_equals($record->token, hash('sha256', $token))) {
            throw new ValidationException(['token' => ['This password reset token is invalid.']]);
        }

        if ($record->created_at === null || (now()->getTimestamp() - $record->created_at->getTimestamp()) > self::TOKEN_TTL_MINUTES * 60) {
            $record->delete();

            throw new ValidationException(['token' => ['This password reset token has expired.']]);
        }

        $user = $this->users->findByEmail($email);

        if ($user === null) {
            throw new ValidationException(['email' => ["We can't find a user with that email address."]]);
        }

        $this->users->update($user, ['password' => password_hash($password, PASSWORD_BCRYPT)]);

        $record->delete();
    }
}
