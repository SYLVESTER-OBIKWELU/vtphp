<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Mail\ResetPasswordEmail;
use App\Models\PasswordResetToken;
use App\Models\User;
use VtPhp\Exceptions\ValidationException;
use VtPhp\Http\JsonResponse;
use VtPhp\Mail\Mailer;

final class PasswordResetController
{
    /**
     * Send a password-reset link.
     *
     * The reply is the same whether or not the address is registered.
     */
    public function forgot(ForgotPasswordRequest $request, Mailer $mailer): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        // Respond identically whether or not the email is registered, so the
        // endpoint can't be used to discover accounts. queue() keeps that
        // true: with a queue worker the response doesn't wait for the mail
        // server, and without one a delivery failure is logged, not thrown.
        if ($user !== null) {
            $mailer->queue(new ResetPasswordEmail($user, PasswordResetToken::issueFor($user)));
        }

        return response()->json(['message' => 'If that email address is registered, a password reset link has been sent.']);
    }

    /**
     * Set a new password with the token from the reset email.
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        /** @var array{email: string, token: string, password: string} $data */
        $data = $request->validated();

        /** @var PasswordResetToken|null $record */
        $record = PasswordResetToken::query()->find($data['email']);

        if ($record === null || !$record->matches($data['token'])) {
            throw new ValidationException(['token' => ['This password reset token is invalid.']]);
        }

        if ($record->isExpired()) {
            $record->delete();

            throw new ValidationException(['token' => ['This password reset token has expired.']]);
        }

        $user = User::query()->where('email', $data['email'])->first()
            ?? throw new ValidationException(['email' => ["We can't find a user with that email address."]]);

        $user->update(['password' => $data['password']]);
        $record->delete();

        return response()->json(['message' => 'Your password has been reset.']);
    }
}
