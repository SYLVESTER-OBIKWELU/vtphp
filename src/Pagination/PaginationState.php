<?php

declare(strict_types=1);

namespace VtPhp\Pagination;

use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Psr\Http\Message\ServerRequestInterface;
use VtPhp\Container\Container;

/**
 * Tells Eloquent's paginate()/simplePaginate() where the current request is:
 * which page was asked for (?page=2), the path to build links from, and the
 * query string for ->withQueryString().
 */
final class PaginationState
{
    public static function resolveUsing(Container $app): void
    {
        $request = static fn (): ?ServerRequestInterface => $app->has(ServerRequestInterface::class)
            ? $app->make(ServerRequestInterface::class)
            : null;

        // Only the path, never the Host header, so a forged Host can't put
        // another site's address into the links.
        Paginator::currentPathResolver(static fn (): string => $request()?->getUri()->getPath() ?: '/');

        Paginator::currentPageResolver(static function (string $pageName = 'page') use ($request): int {
            $page = $request()?->getQueryParams()[$pageName] ?? null;

            return is_scalar($page) && filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1
                ? (int) $page
                : 1;
        });

        Paginator::queryStringResolver(static fn (): array => $request()?->getQueryParams() ?? []);

        CursorPaginator::currentCursorResolver(static function (string $cursorName = 'cursor') use ($request): ?Cursor {
            $cursor = $request()?->getQueryParams()[$cursorName] ?? null;

            return is_string($cursor) ? Cursor::fromEncoded($cursor) : null;
        });
    }
}
