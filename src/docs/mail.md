# Mail

Mail is sent through `symfony/mailer` behind `VtPhp\Mail\Mailer`, using a
Laravel-style `Mailable` base class.

## Defining a Mailable

```powershell
php forge make:mail OrderShipped
```

```php
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
        $this->to($this->user->email)
            ->subject('Welcome to VtPhp!')
            ->view('emails.welcome', ['user' => $this->user]);
    }
}
```

`to()` can be called multiple times to add recipients. `view()` points at a
Blade template (see [Views](views.md)) that renders the HTML body.

## Sending

```php
app(\VtPhp\Mail\Mailer::class)->send(new WelcomeEmail($user));
```

`Mailer::send()` calls `$mailable->build()` exactly once, renders the Blade
view, and dispatches through the configured `MAIL_MAILER` transport.

## Local development

`MAIL_MAILER` defaults to `log`, which writes the rendered subject/body to
`storage/logs/app.log` instead of sending real email — handy for reading
password-reset/verification links during local testing without an SMTP
server. Switch to `smtp` and set `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`,
`MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` in
`.env` for real delivery.

## Links in the sample emails

- **Password reset** links point at your client app:
  `{FRONTEND_URL}/reset-password?token=…&email=…`. That page should ask for
  the new password and POST `email`, `token`, `password` and
  `password_confirmation` to `/api/v1/password/reset`. `FRONTEND_URL`
  defaults to `APP_URL`.
- **Email verification** links point at the API itself
  (`/api/v1/email/verify/{user}/{hash}?expires=…&signature=…`). They are
  signed with `APP_KEY` and expire after 60 minutes.
