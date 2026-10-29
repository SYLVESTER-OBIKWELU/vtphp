<?php

declare(strict_types=1);

return [
    // Serve interactive API docs (Swagger UI) built from your routes,
    // controllers and form requests. Off by default: anyone who can reach
    // the page sees every endpoint, so think before enabling it in production.
    'enabled' => (bool) env('SWAGGER_ENABLED', false),

    // The docs page. The OpenAPI JSON is served at "{path}/openapi.json".
    'path' => env('SWAGGER_PATH', '/api-docs'),

    // Middleware for the docs routes, e.g. [Authenticate::class] to show
    // them only to logged-in users.
    'middleware' => [],

    // true: rebuild the spec on every request (best while developing).
    // false: serve the file written by `php forge swagger:generate`, and
    // fall back to building it if that file doesn't exist yet.
    'generate_always' => (bool) env('SWAGGER_GENERATE_ALWAYS', true),

    'output' => 'storage/api-docs/openapi.json',

    'info' => [
        'title' => env('SWAGGER_TITLE', env('APP_NAME', 'VtPhp').' API'),
        'version' => env('SWAGGER_VERSION', '1.0.0'),
        'description' => env('SWAGGER_DESCRIPTION', ''),
    ],

    // Leave empty to call the API on the same address the docs are served
    // from. Otherwise e.g. [['url' => 'https://api.example.com', 'description' => 'Production']].
    'servers' => [],

    // Optional descriptions for the sections set with $router->group(['tag' => ...]).
    'tags' => [
        // 'Users' => 'Sign up and manage accounts.',
    ],

    // Routes left out of the docs, by path prefix.
    'exclude' => [],

    // Swagger UI is loaded from this CDN (a pinned version).
    'ui_cdn' => 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14',
];
