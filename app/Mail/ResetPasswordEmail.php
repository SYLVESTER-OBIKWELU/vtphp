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
        $url = rtrim((string) config('app.url'), '/')
            .'/api/v1/password/reset?token='.$this->token
            .'&email='.rawurlencode((string) $this->user->email);

        $this->to((string) $this->user->email)
            ->subject('Reset your password')
            ->view('emails.reset-password', [
                'user' => $this->user,
                'token' => $this->token,
                'url' => $url,
            ]);
    }
}
