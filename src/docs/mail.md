# Mail

VtPhp sends email with mailables. A mailable is a small class describing one
kind of email: who it goes to, its subject, and which template draws its
body. While you develop, emails are written to a log file instead of being
sent, so you don't need a mail server to get started.

## The quick version

The app already has a welcome email:

```php
// app/Mail/WelcomeEmail.php
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
```

Send it from a controller by asking for the `Mailer`:

```php
// app/Http/Controllers/UserController.php (example)
use App\Mail\WelcomeEmail;
use VtPhp\Mail\Mailer;

public function store(StoreUserRequest $request, Mailer $mailer): UserResource
{
    $user = User::query()->create($request->validated());

    $mailer->send(new WelcomeEmail($user));

    return UserResource::make($user)->status(201);
}
```

With the default settings, open `storage/logs/app.log` and you'll see the
email's recipient, subject and HTML body.

## Step 1: create a mailable

```bash
php forge make:mail OrderShipped
```

This creates `app/Mail/OrderShipped.php`:

```php
// app/Mail/OrderShipped.php
namespace App\Mail;

use VtPhp\Mail\Mailable;

final class OrderShipped extends Mailable
{
    public function build(): void
    {
        $this->subject('OrderShipped')
            ->view('emails.order_shipped');
    }
}
```

Pass in whatever the email needs through the constructor, and fill in
`build()`:

```php
// app/Mail/OrderShipped.php
public function __construct(
    private readonly User $user,
    private readonly string $orderNumber,
) {
}

public function build(): void
{
    $this->to($this->user->email)
        ->subject("Order {$this->orderNumber} has shipped")
        ->view('emails.order_shipped', [
            'user' => $this->user,
            'orderNumber' => $this->orderNumber,
        ]);
}
```

- `to()` adds a recipient. Call it more than once for several recipients.
- `subject()` sets the subject line.
- `view()` picks the template and the data it can use.

## Step 2: create the template

`make:mail` doesn't create the template, so add it yourself. The name
`emails.order_shipped` means the file
`resources/views/emails/order_shipped.blade.php`:

```blade
{{-- resources/views/emails/order_shipped.blade.php --}}
<!DOCTYPE html>
<html>
<body>
    <h1>Hi {{ $user->name }},</h1>
    <p>Your order {{ $orderNumber }} is on its way.</p>
</body>
</html>
```

`{{ ... }}` prints a value safely: special characters such as `<` are
escaped, so user input can't inject HTML. Templates are explained in
[Views](views.md).

## Step 3: send it

Ask for `VtPhp\Mail\Mailer` in a controller method or constructor, and call
`send()`:

```php
$mailer->send(new OrderShipped($user, 'A-1001'));
```

Outside a controller you can fetch it with `app(\VtPhp\Mail\Mailer::class)`.

The email is sent right away, during the request. Sending in the background
(queued mail) isn't available yet.

## Sending real email

When you're ready to deliver real email, switch from the log to SMTP (the
standard protocol mail servers use). Your email provider gives you these
values:

```dotenv
# .env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_TIMEOUT=10
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME="My App"
```

| Setting | Meaning |
| --- | --- |
| `MAIL_MAILER` | `log` (write to the log, the default) or `smtp` (send). |
| `MAIL_ENCRYPTION` | `ssl` for encrypted-from-the-start connections (usually port 465), `tls` to require an upgrade to encryption (usually port 587), or `null` to encrypt whenever the server offers it. |
| `MAIL_TIMEOUT` | Seconds to wait for the mail server before giving up (default 10). Without a limit, a stuck server could hold a request for a minute. |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Who the email appears to come from. |

## Going further

### When sending fails

`send()` throws an exception if the mail server can't be reached. That
usually turns the request into a `500` error.

For emails the request shouldn't fail over, use `trySend()`. It returns
`true` or `false` and writes any failure to the log instead of throwing:

```php
if (!$mailer->trySend(new OrderShipped($user, 'A-1001'))) {
    // The failure is already logged. Carry on, or tell the user to retry.
}
```

The built-in password-reset and verification emails use `trySend()`. That
way, a mail outage can't reveal which addresses are registered (registered
and unknown emails get the same reply).

### Links in the built-in emails

- **Password reset** links point at your frontend app:
  `FRONTEND_URL/reset-password?token=...&email=...`. Build a page there that
  asks for the new password and sends it to `POST /api/v1/password/reset`.
  `FRONTEND_URL` defaults to `APP_URL`.
- **Email verification** links point at the API itself
  (`/api/v1/email/verify/{user}/{hash}?expires=...&signature=...`). They're
  signed with your `APP_KEY` and expire after 60 minutes, so they can't be
  forged. Run `php forge key:generate` if `APP_KEY` is empty.

### What isn't supported yet

Emails are HTML only. Plain-text versions, attachments, and CC/BCC aren't
available yet.

## Common mistakes

- **"Template not found: emails.order_shipped" when sending.** `make:mail`
  doesn't create the template. Create `resources/views/emails/order_shipped.blade.php`.
- **Nothing arrives in your inbox.** `MAIL_MAILER` is still `log`. Check
  `storage/logs/app.log`, or switch to `smtp`.
- **A request hangs or fails while sending.** The mail server is slow or
  unreachable. Check `MAIL_HOST`, `MAIL_PORT` and `MAIL_ENCRYPTION`, and use
  `trySend()` for emails that shouldn't break the request.
- **Reset links point at the wrong site.** Set `FRONTEND_URL` to your
  frontend's address.

## Next steps

- [Views](views.md): writing the email templates.
- [Authentication](authentication.md): the password reset and verification flows.
- [Deploying](deploying.md): production mail settings.
