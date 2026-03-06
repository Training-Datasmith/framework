<?php

declare(strict_types=1);

namespace Illuminate\Database\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinLateralClause;
use RuntimeException;

class MariaDbGrammar extends MySqlGrammar
{
    /**
     * Compile a "lateral join" clause.
     *
     *
     * @throws \RuntimeException
     */
    public function compileJoinLateral(JoinLateralClause $join, string $expression): string
    {
        throw new RuntimeException('This database engine does not support lateral joins.');
    }

    /**
     * Compile a "JSON value cast" statement into SQL.
     *
     * @param  string  $value
     */
    public function compileJsonValueCast($value): string
    {
        return "json_query({$value}, '$')";
    }

    /**
     * Compile a query to get the number of open connections for a database.
     */
    public function compileThreadCount(): string
    {
        return 'select variable_value as `Value` from information_schema.global_status where variable_name = \'THREADS_CONNECTED\'';
    }

    /**
     * Determine whether to use a legacy group limit clause for MySQL < 8.0.
     */
    public function useLegacyGroupLimit(Builder $query): bool
    {
        return false;
    }

    /**
     * Wrap the given JSON selector.
     *
     * @param  string  $value
     */
    protected function wrapJsonSelector($value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($value);

        return 'json_value('.$field.$path.')';
    }
}
