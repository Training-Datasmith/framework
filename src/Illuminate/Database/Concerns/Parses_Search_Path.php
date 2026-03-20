<?php

declare (strict_types=1);
namespace Illuminate\Database\Concerns;

trait Parses_Search_Path
{
    /**
     * Parse the Postgres "search_path" configuration value into an array.
     *
     * @param  string|array|null  $searchPath
     */
    protected function parse_search_path($search_path): array
    {
        if (is_string($search_path)) {
            preg_match_all('/[^\s,"\']+/', $search_path, $matches);
            $search_path = $matches[0];
        }
        return array_map(fn($schema): string => trim((string) $schema, '\'"'), $search_path ?? []);
    }
}