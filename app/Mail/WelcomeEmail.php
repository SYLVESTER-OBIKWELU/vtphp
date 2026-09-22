<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use VtPhp\Mail\Mailable;

final class WelcomeEmail extends Mailable
{
    public function __construct(private readonly User $user)
    {
    }

    public function build(): void
    {
        $this->to((string) $this->user->email)
            ->subject('Welcome to VtPhp!')
            ->view('emails.welcome', ['user' => $this->user]);
    }
}
