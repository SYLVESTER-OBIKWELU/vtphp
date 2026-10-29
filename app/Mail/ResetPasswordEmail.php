<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use VtPhp\Mail\Mailable;

final class ResetPasswordEmail extends Mailable
{
    public function __construct(
        private readonly User $user,
        private readonly string $token,
    ) {
    }

    public function build(): void
    {
        // The link opens the frontend's reset page, which collects the new
        // password and POSTs it with the token to /api/v1/password/reset.
        $url = rtrim((string) config('app.frontend_url'), '/')
            .'/reset-password?'.http_build_query(['token' => $this->token, 'email' => $this->user->email]);

        $this->to((string) $this->user->email)
            ->subject('Reset your password')
            ->view('emails.reset-password', [
                'user' => $this->user,
                'token' => $this->token,
                'url' => $url,
            ]);
    }
}
