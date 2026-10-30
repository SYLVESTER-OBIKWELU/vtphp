<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

/**
 * The HTML version of an error, for people who open an API URL in a
 * browser. Self-contained: no scripts, no external files.
 */
final class ErrorPage
{
    /**
     * @param array<string, mixed> $error The same `error` object the JSON response carries.
     * @param array<string, string> $headers
     */
    public static function render(int $status, array $error, array $headers = []): ResponseInterface
    {
        $nonce = base64_encode(random_bytes(16));
        $e = static fn (mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $title = HttpStatus::title($status);
        $extra = '';

        if (isset($error['errors']) && is_array($error['errors'])) {
            $items = '';

            foreach ($error['errors'] as $field => $messages) {
                foreach ((array) $messages as $message) {
                    $items .= '<li><code>'.$e($field).'</code> '.$e($message).'</li>';
                }
            }

            $extra .= "<ul class=\"list\">{$items}</ul>";
        }

        if (isset($error['allowed_methods']) && is_array($error['allowed_methods'])) {
            $extra .= '<p>Allowed: <code>'.$e(implode(', ', $error['allowed_methods'])).'</code></p>';
        }

        if (isset($error['retry_after'])) {
            $extra .= '<p>Try again in '.$e($error['retry_after']).' seconds.</p>';
        }

        if (isset($error['hint'])) {
            $extra .= '<p class="hint">'.$e($error['hint']).'</p>';
        }

        $debug = '';

        if (isset($error['debug']) && is_array($error['debug'])) {
            $trace = isset($error['debug']['trace']) && is_array($error['debug']['trace'])
                ? '<pre>'.$e(implode("\n", $error['debug']['trace'])).'</pre>'
                : '';
            $debug = '<details open><summary>Debug details (APP_DEBUG=true)</summary>'
                .'<p><code>'.$e($error['debug']['exception'] ?? '').'</code><br>at <code>'.$e($error['debug']['location'] ?? '').'</code></p>'
                .$trace.'</details>';
        }

        $requestId = isset($error['request_id']) ? '<p class="meta">Request ID: <code>'.$e($error['request_id']).'</code></p>' : '';
        $code = $e($error['code'] ?? HttpStatus::code($status));
        $message = $e($error['message'] ?? HttpStatus::message($status));

        $html = <<<HTML
            <!doctype html>
            <html lang="en">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex">
            <title>{$status} {$e($title)}</title>
            <style nonce="{$nonce}">
                :root { --bg: #f6f7f9; --card: #fff; --text: #1d2330; --muted: #5d6779; --accent: #c2410c; --code: #eef0f4; --border: #e2e5eb; }
                @media (prefers-color-scheme: dark) {
                    :root { --bg: #111318; --card: #1a1d24; --text: #e6e8ee; --muted: #9aa3b2; --accent: #fb923c; --code: #252a33; --border: #2c313b; }
                }
                * { box-sizing: border-box; }
                body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px;
                       background: var(--bg); color: var(--text); font: 16px/1.55 system-ui, -apple-system, "Segoe UI", sans-serif; }
                main { width: 100%; max-width: 720px; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 32px; }
                .status { font-size: 56px; font-weight: 700; line-height: 1; color: var(--accent); margin: 0; }
                h1 { font-size: 22px; margin: 8px 0 4px; }
                .code { color: var(--muted); font-size: 13px; letter-spacing: .04em; margin: 0 0 16px; }
                p { margin: 12px 0; }
                code, pre { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 13px; background: var(--code); border-radius: 4px; }
                code { padding: 1px 5px; }
                pre { padding: 12px; overflow-x: auto; white-space: pre; line-height: 1.5; }
                .list { padding-left: 20px; }
                .hint { border-left: 3px solid var(--accent); padding-left: 12px; }
                .meta { color: var(--muted); font-size: 14px; }
                details { margin-top: 20px; border-top: 1px solid var(--border); padding-top: 12px; }
                summary { cursor: pointer; color: var(--muted); font-size: 14px; }
            </style>
            </head>
            <body>
            <main>
                <p class="status">{$status}</p>
                <h1>{$e($title)}</h1>
                <p class="code">{$code}</p>
                <p>{$message}</p>
                {$extra}
                {$requestId}
                {$debug}
            </main>
            </body>
            </html>
            HTML;

        $factory = new Psr17Factory();
        $response = $factory->createResponse($status)->withBody($factory->createStream($html));

        foreach ($headers + [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
            // Only this page's own styles; no scripts at all.
            'Content-Security-Policy' => "default-src 'none'; style-src 'nonce-{$nonce}'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'",
        ] as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
