<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\VerifyEmail;
use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use VtPhp\Exceptions\AuthorizationException;
use VtPhp\Exceptions\NotFoundHttpException;
use VtPhp\Mail\Mailer;

final class EmailVerificationService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly Mailer $mailer,
    ) {
    }

    public function sendVerificationEmail(string $email): void
    {
        $user = $this->users->findByEmail($email);

        // Always behave the same way whether or not the email is registered
        // or already verified, to avoid leaking account state.
        if ($user === null || $user->hasVerifiedEmail()) {
            return;
        }

        $url = rtrim((string) config('app.url'), '/')."/api/v1/email/verify/{$user->id}/".$this->hash($user);

        $this->mailer->send(new VerifyEmail($user, $url));
    }

    public function verify(int $id, string $hash): User
    {
        $user = $this->users->find($id) ?? throw new NotFoundHttpException('User not found.');

        if (!hash_equals($this->hash($user), $hash)) {
            throw new AuthorizationException('Invalid or expired email verification link.');
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return $user;
    }

    private function hash(User $user): string
    {
        return sha1((string) $user->email);
    }
}
