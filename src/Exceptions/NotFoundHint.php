<?php

declare(strict_types=1);

namespace VtPhp\Exceptions;

use Psr\Http\Message\ServerRequestInterface;
use VtPhp\Config\Repository;
use VtPhp\Routing\Router;

/**
 * Debug-mode tips for a 404: the API docs being switched off, or routes
 * whose URL is close to the one asked for ("Did you mean ...?"). Never used
 * outside debug mode, because it reveals the app's routes.
 */
final class NotFoundHint
{
    public function __construct(
        private readonly Router $router,
        private readonly Repository $config,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): string
    {
        $path = '/'.trim($request->getUri()->getPath(), '/');
        $method = strtoupper($request->getMethod());

        $docsPath = '/'.trim((string) $this->config->get('swagger.path', '/api-docs'), '/');

        if (!$this->config->get('swagger.enabled', false) && ($path === $docsPath || $this->similar($path, $docsPath, alsoPrefix: true))) {
            return "The API docs are switched off. Set SWAGGER_ENABLED=true in .env, then open {$docsPath}.";
        }

        $suggestions = [];

        foreach ($this->router->routes() as $route) {
            $candidate = $route->getPath();
            $methods = $route->getMethods() ?: ['GET'];

            // A route with parameters is compared by shape: /userz/5 and
            // /users/{user} both become /user?/{}.
            [$asked, $known] = str_contains($candidate, '{')
                ? [preg_replace('#/(\d+|[0-9a-f-]{20,})(?=/|$)#i', '/{}', $path) ?? $path, preg_replace('#\{\w+\}#', '{}', $candidate) ?? $candidate]
                : [$path, $candidate];

            // Several routes can share a URL (GET/PATCH/DELETE /users/{user});
            // suggest it with the method that was asked for when one matches.
            $matchesMethod = in_array($method, $methods, true);

            if ($this->similar($asked, $known) && ($matchesMethod || !isset($suggestions[$candidate]))) {
                $suggestions[$candidate] = ($matchesMethod ? $method : $methods[0]).' '.$candidate;
            }
        }

        if ($suggestions !== []) {
            return 'Did you mean '.implode(' or ', array_slice(array_values($suggestions), 0, 3)).'?';
        }

        return 'Run `php forge route:list` to see every URL this app answers.';
    }

    private function similar(string $path, string $candidate, bool $alsoPrefix = false): bool
    {
        if ($path === $candidate) {
            return false;
        }

        if ($alsoPrefix && (str_starts_with($path, $candidate.'/') || str_starts_with($candidate, $path) && strlen($path) > 1)) {
            return true;
        }

        $a = strtolower($path);
        $b = strtolower($candidate);
        $allowed = max(2, intdiv(max(strlen($a), strlen($b)), 6));

        return abs(strlen($a) - strlen($b)) <= $allowed && levenshtein($a, $b) <= $allowed;
    }
}
