<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use VtPhp\Mail\Mailable;

final class VerifyEmail extends Mailable
{
    public function __construct(
        private readonly User $user,
        private readonly string $verificationUrl,
    ) {
    }

    public function build(): void
    {
        $this->to((string) $this->user->email)
            ->subject('Verify your email address')
            ->view('emails.verify-email', [
                'user' => $this->user,
                'url' => $this->verificationUrl,
            ]);
    }
}
