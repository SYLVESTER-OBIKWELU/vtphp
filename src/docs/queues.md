# Queues: doing slow work in the background

Some work is too slow to make a user wait for: sending emails, resizing
images, calling another company's API, building a report. A **queue** lets
your API answer straight away and do that work a moment later, in a separate
process called a **worker**.

The work is packaged as a **job**: a small class with a `handle()` method.

## The quick version

1. Create the tables (once): `php forge migrate`.
2. Switch the queue on in `.env`:

   ```dotenv
   QUEUE_CONNECTION=database
   ```

3. Create a job:

   ```bash
   php forge make:job SendInvoice
   ```

4. Fill in `app/Jobs/SendInvoice.php` and send it to the queue from your code:

   ```php
   SendInvoice::dispatch($order);
   ```

5. Start a worker in another terminal. It runs the jobs as they arrive:

   ```bash
   php forge queue:work
   ```

## Two ways to run jobs

| `QUEUE_CONNECTION` | What happens | Use it for |
| --- | --- | --- |
| `sync` (the default) | The job runs straight away, during the request. No worker needed. | development and tests |
| `database` | The job is saved in the `jobs` table. A worker picks it up and runs it. | production |

You write the same code either way. Switching is just the `.env` setting.

## Writing a job

`php forge make:job SendInvoice` creates this:

```php
// app/Jobs/SendInvoice.php
namespace App\Jobs;

use App\Mail\InvoiceEmail;
use App\Models\Order;
use VtPhp\Mail\Mailer;
use VtPhp\Queue\Job;

final class SendInvoice extends Job
{
    public ?int $tries = 3;      // try up to 3 times
    public int|array $backoff = 10; // wait 10 seconds between tries

    public function __construct(public Order $order)
    {
    }

    public function handle(Mailer $mailer): void
    {
        $mailer->send(new InvoiceEmail($this->order));
    }

    public function failed(\Throwable $exception): void
    {
        // Runs once if every try failed, e.g. tell your team.
    }
}
```

- **The constructor** stores what the job needs. It's saved with the job.
- **`handle()`** does the work. Any class you type in its brackets (here a
  `Mailer`) is passed in for you.
- **If `handle()` throws an exception**, the job is tried again later, up to
  `$tries` times. After the last try it's moved to the failed jobs list and
  `failed()` runs.

### Models are saved by id

When a job holds an Eloquent model, like `$order` above, only its id goes
into the queue. The worker loads the order fresh from the database just
before `handle()` runs, so the job always sees the latest data.

If the record was deleted in the meantime, the job fails straight away
without retrying.

Store models, ids, strings, numbers and arrays in jobs. Don't store things
that can't be saved, such as database connections, open files or closures.

## Sending jobs to the queue

```php
use App\Jobs\SendInvoice;

SendInvoice::dispatch($order);                       // as soon as possible
SendInvoice::dispatch($order)->delay(600);           // in 10 minutes
SendInvoice::dispatch($order)->delay(now()->modify('+1 day'));
SendInvoice::dispatch($order)->onQueue('emails');    // on a named queue
SendInvoice::dispatchSync($order);                   // right now, skipping the queue

dispatch(new SendInvoice($order));                   // the helper works too
```

The arguments to `dispatch()` go to the job's constructor.

### Named queues

Queues let you keep urgent work from waiting behind slow work. Put jobs on a
named queue with `onQueue('emails')`, and tell the worker which queues to
check, most important first:

```bash
php forge queue:work --queue=emails,default
```

The worker always takes a job from `emails` if there is one, and only then
from `default`. Jobs without `onQueue()` go to `default`.

## Retries and waiting between them

| Property | Meaning |
| --- | --- |
| `$tries` | How many times to try. Leave it `null` to use the worker's `--tries` (1 unless you say otherwise). |
| `$backoff` | Seconds to wait before trying again. A list waits a different time before each retry: `[10, 60, 300]` waits 10 s, then 1 minute, then 5 minutes. |

## Queued email

Send any email in the background with `queue()` instead of `send()`:

```php
$mailer->queue(new OrderShipped($user, 'A-1001'));
$mailer->queue(new OrderShipped($user, 'A-1001'), 'emails'); // on a named queue
```

On the `database` connection the request returns at once, and the worker
sends the email, trying up to 3 times if the mail server is down. On `sync`
it's sent right away like `trySend()`: if sending fails, the failure is
logged and the request carries on.

The sample app's password-reset and verification emails use `queue()`.

## Running the worker

```bash
php forge queue:work
```

It runs until you stop it with Ctrl+C. Each job prints a line:

```
2026-10-31 09:12:04 App\Jobs\SendInvoice DONE (184 ms)
2026-10-31 09:12:05 App\Jobs\SyncStock WILL RETRY (2003 ms)
2026-10-31 09:12:09 App\Jobs\SyncStock FAILED (2001 ms)
```

| Option | What it does |
| --- | --- |
| `--queue=emails,default` | Which queues to work, most important first |
| `--tries=3` | Tries for jobs that don't set `$tries` |
| `--sleep=3` | Seconds to wait when the queue is empty |
| `--once` | Run one job, then stop |
| `--stop-when-empty` | Run jobs until there are none left, then stop |
| `--max-jobs=500` | Stop after this many jobs |
| `--max-time=3600` | Stop after this many seconds |

**The worker loads your code once, when it starts.** After you change your
code (or deploy), restart the worker or it keeps running the old version.

## When jobs fail

Jobs that failed every try are kept in the `failed_jobs` table, with the
error:

```bash
php forge queue:failed          # list them
php forge queue:retry 5         # put job 5 back on the queue
php forge queue:retry all       # put them all back
php forge queue:forget 5        # delete job 5
php forge queue:flush           # delete all failed jobs
```

A retried job gets a fresh set of tries. To empty a queue of jobs that
haven't run yet, use `php forge queue:clear` (add `--queue=emails` for a
named queue).

## In production

Run the worker all the time with a process manager, which starts it when the
server boots and restarts it if it stops. With systemd on Linux:

```ini
# /etc/systemd/system/myapi-worker.service
[Unit]
Description=MyApi queue worker
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/myapi
ExecStart=/usr/bin/php forge queue:work --tries=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now myapi-worker
sudo systemctl restart myapi-worker   # after every deploy
```

`--max-time=3600` makes the worker exit every hour and systemd starts a fresh
one, which keeps memory use in check. When stopped, the worker finishes the
job it's on first (on Linux and macOS).

Need more speed? Run several workers. Each job is only ever given to one of
them.

### How long a job may take

If a worker dies in the middle of a job (the server restarts, say), the job
is handed out again after `retry_after` seconds: 90 by default. If your
slowest job takes longer than that, raise it in `.env`, or a second worker
may start the same job while the first is still on it:

```dotenv
QUEUE_RETRY_AFTER=300
```

## Is it safe?

The `jobs` table holds your jobs as PHP data. Each one is signed with your
`APP_KEY`, and a job whose signature doesn't match is moved to the failed
list without being run. So even someone who can write to your database can't
make the worker run code they choose.

This also means **changing `APP_KEY` invalidates jobs already waiting.** Let
the queue empty before you change it.

## Common mistakes

- **Nothing happens.** No worker is running. Start `php forge queue:work`,
  or use `QUEUE_CONNECTION=sync` while developing.
- **The worker runs old code.** Restart it after every change or deploy.
- **"There is nothing for a worker to do."** `QUEUE_CONNECTION` is `sync`,
  which runs jobs during the request. Set it to `database`.
- **"Table jobs doesn't exist".** Run `php forge migrate`.
- **"Serialization of 'Closure' is not allowed".** A job (or something it
  holds) contains a closure or a connection. Store ids and plain values
  instead.
- **A job runs twice.** It took longer than `retry_after`. Raise
  `QUEUE_RETRY_AFTER`.

## Next steps

- [Mail](mail.md): the emails you'll most often queue.
- [Deploying](deploying.md): running the worker on a server.
- [The forge CLI](cli.md): every `queue:` command.
