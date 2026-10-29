<?php

declare(strict_types=1);

namespace VtPhp\OpenApi;

use Psr\Http\Message\ResponseInterface;
use VtPhp\Config\Repository;
use VtPhp\Foundation\Application;
use VtPhp\Http\JsonResponse;

/**
 * Serves the Swagger UI page and the OpenAPI document it reads. Registered
 * only when SWAGGER_ENABLED=true.
 */
final class SwaggerController
{
    public function __construct(
        private readonly Application $app,
        private readonly Repository $config,
        private readonly OpenApiGenerator $generator,
    ) {
    }

    public function ui(): ResponseInterface
    {
        $cdn = rtrim((string) $this->config->get('swagger.ui_cdn'), '/');
        $title = htmlspecialchars((string) $this->config->get('swagger.info.title', 'API'), ENT_QUOTES);
        $specUrl = json_encode(self::jsonPath((string) $this->config->get('swagger.path', '/api-docs')), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
        $nonce = base64_encode(random_bytes(16));
        $cdnOrigin = (string) preg_replace('#^(https?://[^/]+).*$#', '$1', $cdn);

        $html = <<<HTML
            <!doctype html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>{$title}</title>
                <link rel="stylesheet" href="{$cdn}/swagger-ui.css">
            </head>
            <body>
                <div id="swagger-ui"></div>
                <script src="{$cdn}/swagger-ui-bundle.js"></script>
                <script nonce="{$nonce}">
                    window.ui = SwaggerUIBundle({
                        url: {$specUrl},
                        dom_id: '#swagger-ui',
                        deepLinking: true,
                        tryItOutEnabled: false,
                        displayRequestDuration: true,
                        filter: true,
                        // Writes must be JSON (see PreventRequestForgery).
                        requestInterceptor: (request) => {
                            request.headers['X-Requested-With'] = 'XMLHttpRequest';
                            return request;
                        },
                    });
                </script>
            </body>
            </html>
            HTML;

        return response()->make($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            // Replaces the API's default `default-src 'none'`, allowing just
            // the Swagger UI files and this page's own script.
            'Content-Security-Policy' => "default-src 'none'; script-src {$cdnOrigin} 'nonce-{$nonce}'; "
                ."style-src {$cdnOrigin} 'unsafe-inline'; img-src 'self' data: {$cdnOrigin}; "
                ."connect-src 'self'; font-src {$cdnOrigin}; frame-ancestors 'none'; base-uri 'none'; form-action 'none'",
            'Cache-Control' => 'no-store',
        ]);
    }

    public function json(): JsonResponse
    {
        $file = $this->app->basePath((string) $this->config->get('swagger.output', 'storage/api-docs/openapi.json'));

        if (!$this->config->get('swagger.generate_always', true) && is_file($file)) {
            $document = json_decode((string) file_get_contents($file), true);

            if (is_array($document)) {
                return new JsonResponse($document);
            }
        }

        return new JsonResponse($this->generator->generate());
    }

    public static function jsonPath(string $path): string
    {
        return rtrim($path, '/').'/openapi.json';
    }
}
