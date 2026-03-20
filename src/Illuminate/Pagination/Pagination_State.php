<?php

declare(strict_types=1);

namespace Illuminate\Pagination;

class PaginationState
{
    /**
     * Bind the pagination state resolvers using the given application container as a base.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    public static function resolveUsing($app): void
    {
        Paginator::viewFactoryResolver(fn () => $app['view']);

        Paginator::currentPathResolver(fn () => $app['request']->url());

        Paginator::currentPageResolver(function ($pageName = 'page') use ($app): int {
            $page = $app['request']->input($pageName);

            if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
                return (int) $page;
            }

            return 1;
        });

        Paginator::queryStringResolver(fn () => $app['request']->query());

        CursorPaginator::currentCursorResolver(fn ($cursorName = 'cursor'): ?\Illuminate\Pagination\Cursor => Cursor::fromEncoded($app['request']->input($cursorName)));
    }
}
