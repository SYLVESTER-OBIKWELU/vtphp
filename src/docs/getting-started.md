# Getting started

This page takes you from nothing to a running VtPhp app that answers
requests. It takes about ten minutes. No framework experience is needed.

**What is VtPhp?** It's a PHP framework for building APIs. An **API** is a
program that other programs talk to over the web. A mobile app or a website
sends it a request ("give me my notes"), and it answers with data in JSON
format. VtPhp handles the plumbing (URLs, security, database access, errors)
so you can focus on what your API does. If you know Laravel, most of it will
feel familiar.

## The quick version

```bash
composer install
cp .env.example .env          # PowerShell: Copy-Item .env.example .env
php forge key:generate
php forge migrate
php forge serve
```

Open <http://127.0.0.1:8000/health> in your browser. You should see:

```json
{"status":"ok","version":"0.1.0"}
```

That's a running API. The rest of this page explains each step.

## 1. Install what you need

You need two programs:

- **PHP 8.5 or newer.** PHP runs your code. Check with:

  ```bash
  php -v
  ```

  It should print `PHP 8.5...` or higher. On Windows, an easy way to get PHP
  is [XAMPP](https://www.apachefriends.org/), which installs it in
  `C:\xampp\php`.

- **Composer.** Composer downloads the libraries VtPhp is built on. Get it
  from [getcomposer.org](https://getcomposer.org/), then check with:

  ```bash
  composer --version
  ```

PHP also needs a few **extensions** (optional add-ons that come with PHP):
`mbstring`, `pdo` and `pdo_sqlite`. List the ones you have with `php -m`.
If one is missing, enable it in your `php.ini` file by removing the `;` in
front of its `extension=` line.

## 2. Install the libraries

From the project folder, run:

```bash
composer install
```

This creates a `vendor/` folder with everything VtPhp needs. You only do this
once (and again whenever `composer.json` changes).

## 3. Create your settings file

Your app's settings, such as the database to use or whether to show detailed
errors, live in a file called `.env`. The project comes with an example to
copy:

```bash
cp .env.example .env
```

On Windows PowerShell, use `Copy-Item .env.example .env` instead.

Then create the app's secret key:

```bash
php forge key:generate
```

This writes a random `APP_KEY` into `.env`. VtPhp uses it to sign links, such
as email-verification links, so nobody can fake them. Never share it, and
never commit `.env` to version control (it's already in `.gitignore`).

## 4. Set up the database

A **database** is where your app keeps data permanently. By default VtPhp
uses **SQLite**, a database stored in a single file, so there's no database
server to install. Create the tables:

```bash
php forge migrate
```

You'll see each table being created:

```text
Migrated: 2026_09_22_000001_create_users_table
Migrated: 2026_09_23_000001_create_password_reset_tokens_table
Migrated: 2026_10_29_000001_create_sessions_table
Migrated: 2026_10_29_000002_create_cache_table
```

The database file is `database/database.sqlite`. Optionally, add two sample
users to play with:

```bash
php forge db:seed
```

This creates `ada@example.com` and `grace@example.com`, both with the
password `password`. They're for local testing only; the seeder never runs in
production.

## 5. Start the app

```bash
php forge serve
```

```text
Server running: http://127.0.0.1:8000
```

Leave this window open while you work; press Ctrl+C to stop the server.
Visit <http://127.0.0.1:8000/health> and you'll see
`{"status":"ok","version":"0.1.0"}`.

## 6. Send your first API request

Browsers can only easily send simple `GET` requests. To send other kinds,
such as a login with a JSON body, use an **API client**. Good free ones are
[Postman](https://www.postman.com/), [Insomnia](https://insomnia.rest/), or
the *Thunder Client* extension for VS Code.

In these docs, a request looks like this:

```http
POST /api/v1/login
Content-Type: application/json

{"email": "ada@example.com", "password": "password"}
```

That means: send a `POST` request to `http://127.0.0.1:8000/api/v1/login`,
with the header `Content-Type: application/json`, and the JSON shown as the
body. In your API client, pick `POST`, paste the URL, choose a JSON body, and
paste the JSON.

You'll get back the logged-in user:

```json
{
  "data": {
    "id": 1,
    "name": "Ada Lovelace",
    "email": "ada@example.com",
    "email_verified": false,
    "created_at": "2026-10-29T19:49:02+00:00"
  }
}
```

Prefer the command line? The same request with `curl` (macOS, Linux or Git
Bash on Windows):

```bash
curl -X POST http://127.0.0.1:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email": "ada@example.com", "password": "password"}'
```

Or in Windows PowerShell:

```powershell
Invoke-RestMethod -Method Post -Uri http://127.0.0.1:8000/api/v1/login `
  -ContentType 'application/json' `
  -Body '{"email": "ada@example.com", "password": "password"}'
```

## A tour of the project

| Folder or file | What's in it |
| --- | --- |
| `app/` | **Your code.** Controllers, models, form requests, emails. |
| `routes/api.php` | The list of URLs your API answers. |
| `config/` | Settings files. Most read their values from `.env`. |
| `database/` | Migrations (table definitions) and seeders (sample data). |
| `resources/views/` | Templates for emails. |
| `public/index.php` | The single entry point every web request goes through. |
| `storage/` | Logs, cache files and uploaded files. Must be writable. |
| `tests/` | Automated tests. |
| `src/` | The framework itself. You'll rarely need to change it. |
| `forge` | The command-line tool you've been running. |
| `.env` | Your local settings. Private. |

## Troubleshooting

- **`php` is not recognized as a command.** PHP isn't on your PATH (the list
  of folders your terminal searches for programs). Add PHP's folder (e.g.
  `C:\xampp\php`) to PATH, then close and reopen your terminal or editor:
  terminals opened earlier don't see the change.
- **`could not find driver`.** The `pdo_sqlite` extension is off. Enable it in
  `php.ini`.
- **`no such table: sessions`** (or `cache`, `users`). You haven't created the
  tables yet. Run `php forge migrate`.
- **`No application key is set`.** Run `php forge key:generate`.
- **`Address already in use`.** Something else uses port 8000. Pick another:
  `php forge serve --port=8080`.
- **Error responses contain a long `trace`.** That's normal locally:
  `.env.example` turns on `APP_DEBUG=true` to help you find problems.
  Production hides these details (see [Deploying](deploying.md)).

## Next steps

- [Your first API](your-first-api.md): build a small notes API, step by step.
- [How a request works](request-lifecycle.md): what happens between a request
  arriving and your code running.
- [The forge CLI](cli.md): every command `php forge` offers.
