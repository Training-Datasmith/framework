<?php

declare(strict_types=1);

namespace Illuminate\Database\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinLateralClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MySqlGrammar extends Grammar
{
    /**
     * The grammar specific operators.
     *
     * @var string[]
     */
    protected $operators = ['sounds like'];

    /**
     * Compile a select query into SQL.
     *
     * @return string
     */
    public function compileSelect(Builder $query)
    {
        $sql = parent::compileSelect($query);

        if ($query->timeout === null) {
            return $sql;
        }

        $milliseconds = $query->timeout * 1000;

        return preg_replace(
            '/^select\b/i',
            'select /*+ MAX_EXECUTION_TIME('.$milliseconds.') */',
            $sql,
            1
        );
    }

    /**
     * Compile a "where like" clause.
     *
     * @param  array  $where
     */
    protected function whereLike(Builder $query, $where): string
    {
        $where['operator'] = $where['not'] ? 'not ' : '';

        $where['operator'] .= $where['caseSensitive'] ? 'like binary' : 'like';

        return $this->whereBasic($query, $where);
    }

    /**
     * Compile a "where null safe equals" clause.
     *
     * @param  array  $where
     */
    protected function whereNullSafeEquals(Builder $query, $where): string
    {
        return $this->wrap($where['column']).' <=> '.$this->parameter($where['value']);
    }

    /**
     * Add a "where null" clause to the query.
     */
    protected function whereNull(Builder $query, array $where): string
    {
        $columnValue = (string) $this->getValue($where['column']);

        if ($this->isJsonSelector($columnValue)) {
            [$field, $path] = $this->wrapJsonFieldAndPath($columnValue);

            return '(json_extract('.$field.$path.') is null OR json_type(json_extract('.$field.$path.')) = \'NULL\')';
        }

        return parent::whereNull($query, $where);
    }

    /**
     * Add a "where not null" clause to the query.
     */
    protected function whereNotNull(Builder $query, array $where): string
    {
        $columnValue = (string) $this->getValue($where['column']);

        if ($this->isJsonSelector($columnValue)) {
            [$field, $path] = $this->wrapJsonFieldAndPath($columnValue);

            return '(json_extract('.$field.$path.') is not null AND json_type(json_extract('.$field.$path.')) != \'NULL\')';
        }

        return parent::whereNotNull($query, $where);
    }

    /**
     * Compile a "where fulltext" clause.
     *
     * @param  array  $where
     */
    public function whereFullText(Builder $query, $where): string
    {
        $columns = $this->columnize($where['columns']);

        $value = $this->parameter($where['value']);

        $mode = ($where['options']['mode'] ?? []) === 'boolean'
            ? ' in boolean mode'
            : ' in natural language mode';

        $expanded = ($where['options']['expanded'] ?? []) && ($where['options']['mode'] ?? []) !== 'boolean'
            ? ' with query expansion'
            : '';

        return "match ({$columns}) against (".$value."{$mode}{$expanded})";
    }

    /**
     * Compile the index hints for the query.
     *
     * @param  \Illuminate\Database\Query\IndexHint  $indexHint
     *
     * @throws \InvalidArgumentException
     */
    protected function compileIndexHint(Builder $query, $indexHint): string
    {
        $index = $indexHint->index;

        $indexes = array_map(trim(...), explode(',', $index));

        foreach ($indexes as $i) {
            if (! preg_match('/^[a-zA-Z0-9_$]+$/', $i)) {
                throw new InvalidArgumentException('Index name contains invalid characters.');
            }
        }

        return match ($indexHint->type) {
            'hint' => "use index ({$index})",
            'force' => "force index ({$index})",
            default => "ignore index ({$index})",
        };
    }

    /**
     * Compile a group limit clause.
     */
    protected function compileGroupLimit(Builder $query): string
    {
        return $this->useLegacyGroupLimit($query)
            ? $this->compileLegacyGroupLimit($query)
            : parent::compileGroupLimit($query);
    }

    /**
     * Determine whether to use a legacy group limit clause for MySQL < 8.0.
     */
    public function useLegacyGroupLimit(Builder $query): bool
    {
        $version = $query->getConnection()->getServerVersion();

        return ! $query->getConnection()->isMaria() && version_compare($version, '8.0.11', '<');
    }

    /**
     * Compile a group limit clause for MySQL < 8.0.
     *
     * Derived from https://softonsofa.com/tweaking-eloquent-relations-how-to-get-n-related-models-per-parent/.
     */
    protected function compileLegacyGroupLimit(Builder $query): string
    {
        $limit = (int) $query->groupLimit['value'];
        $offset = $query->offset;

        if (isset($offset)) {
            $offset = (int) $offset;
            $limit += $offset;

            $query->offset = null;
        }

        $column = last(explode('.', (string) $query->groupLimit['column']));
        $column = $this->wrap($column);

        $partition = ', @laravel_row := if(@laravel_group = '.$column.', @laravel_row + 1, 1) as `laravel_row`';
        $partition .= ', @laravel_group := '.$column;

        $orders = (array) $query->orders;

        array_unshift($orders, [
            'column' => $query->groupLimit['column'],
            'direction' => 'asc',
        ]);

        $query->orders = $orders;

        $components = $this->compileComponents($query);

        $sql = $this->concatenate($components);

        $from = '(select @laravel_row := 0, @laravel_group := 0) as `laravel_vars`, ('.$sql.') as `laravel_table`';

        $sql = 'select `laravel_table`.*'.$partition.' from '.$from.' having `laravel_row` <= '.$limit;

        if (isset($offset)) {
            $sql .= ' and `laravel_row` > '.$offset;
        }

        return $sql.' order by `laravel_row`';
    }

    /**
     * Compile an insert ignore statement into SQL.
     *
     * @return string
     */
    public function compileInsertOrIgnore(Builder $query, array $values)
    {
        return Str::replaceFirst('insert', 'insert ignore', $this->compileInsert($query, $values));
    }

    /**
     * Compile an insert ignore statement using a subquery into SQL.
     *
     * @return string
     */
    public function compileInsertOrIgnoreUsing(Builder $query, array $columns, string $sql)
    {
        return Str::replaceFirst('insert', 'insert ignore', $this->compileInsertUsing($query, $columns, $sql));
    }

    /**
     * Compile a "JSON contains" statement into SQL.
     *
     * @param  string  $column
     * @param  string  $value
     */
    protected function compileJsonContains($column, $value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($column);

        return 'json_contains('.$field.', '.$value.$path.')';
    }

    /**
     * Compile a "JSON overlaps" statement into SQL.
     *
     * @param  string  $column
     * @param  string  $value
     */
    protected function compileJsonOverlaps($column, $value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($column);

        return 'json_overlaps('.$field.', '.$value.$path.')';
    }

    /**
     * Compile a "JSON contains key" statement into SQL.
     *
     * @param  string  $column
     */
    protected function compileJsonContainsKey($column): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($column);

        return 'ifnull(json_contains_path('.$field.', \'one\''.$path.'), 0)';
    }

    /**
     * Compile a "JSON length" statement into SQL.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  string  $value
     */
    protected function compileJsonLength($column, $operator, $value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($column);

        return 'json_length('.$field.$path.') '.$operator.' '.$value;
    }

    /**
     * Compile a "JSON value cast" statement into SQL.
     *
     * @param  string  $value
     */
    public function compileJsonValueCast($value): string
    {
        return 'cast('.$value.' as json)';
    }

    /**
     * Compile the random statement into SQL.
     *
     * @param  string|int  $seed
     *
     * @throws \InvalidArgumentException
     */
    public function compileRandom($seed): string
    {
        if ($seed === '' || $seed === null) {
            return 'RAND()';
        }

        if (! is_numeric($seed)) {
            throw new InvalidArgumentException('The seed value must be numeric.');
        }

        return 'RAND('.(int) $seed.')';
    }

    /**
     * Compile the lock into SQL.
     *
     * @param  bool|string  $value
     */
    protected function compileLock(Builder $query, $value): string
    {
        if (! is_string($value)) {
            return $value ? 'for update' : 'lock in share mode';
        }

        return $value;
    }

    /**
     * Compile an insert statement into SQL.
     */
    public function compileInsert(Builder $query, array $values): string
    {
        if (empty($values)) {
            $values = [[]];
        }

        return parent::compileInsert($query, $values);
    }

    /**
     * Compile the columns for an update statement.
     */
    protected function compileUpdateColumns(Builder $query, array $values): string
    {
        return (new Collection($values))->map(function ($value, $key): string {
            if ($this->isJsonSelector($key)) {
                return $this->compileJsonUpdateColumn($key, $value);
            }

            return $this->wrap($key).' = '.$this->parameter($value);
        })->implode(', ');
    }

    /**
     * Compile an "upsert" statement into SQL.
     */
    public function compileUpsert(Builder $query, array $values, array $uniqueBy, array $update): string
    {
        $useUpsertAlias = $query->connection->getConfig('use_upsert_alias');

        $sql = $this->compileInsert($query, $values);

        if ($useUpsertAlias) {
            $sql .= ' as laravel_upsert_alias';
        }

        $sql .= ' on duplicate key update ';

        $columns = (new Collection($update))->map(function ($value, $key) use ($useUpsertAlias): string {
            if (! is_numeric($key)) {
                return $this->wrap($key).' = '.$this->parameter($value);
            }

            return $useUpsertAlias
                ? $this->wrap($value).' = '.$this->wrap('laravel_upsert_alias').'.'.$this->wrap($value)
                : $this->wrap($value).' = values('.$this->wrap($value).')';
        })->implode(', ');

        return $sql.$columns;
    }

    /**
     * Compile a "lateral join" clause.
     */
    public function compileJoinLateral(JoinLateralClause $join, string $expression): string
    {
        return trim("{$join->type} join lateral {$expression} on true");
    }

    /**
     * Prepare a JSON column being updated using the JSON_SET function.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    protected function compileJsonUpdateColumn($key, $value): string
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_array($value)) {
            $value = 'cast(? as json)';
        } else {
            $value = $this->parameter($value);
        }

        [$field, $path] = $this->wrapJsonFieldAndPath($key);

        return "{$field} = json_set({$field}{$path}, {$value})";
    }

    /**
     * Compile an update statement without joins into SQL.
     *
     * @param  string  $table
     * @param  string  $columns
     * @param  string  $where
     */
    protected function compileUpdateWithoutJoins(Builder $query, $table, $columns, $where): string
    {
        $sql = parent::compileUpdateWithoutJoins($query, $table, $columns, $where);

        if (! empty($query->orders)) {
            $sql .= ' '.$this->compileOrders($query, $query->orders);
        }

        if (isset($query->limit)) {
            $sql .= ' '.$this->compileLimit($query, $query->limit);
        }

        return $sql;
    }

    /**
     * Prepare the bindings for an update statement.
     *
     * Booleans, integers, and doubles are inserted into JSON updates as raw values.
     */
    #[\Override]
    public function prepareBindingsForUpdate(array $bindings, array $values): array
    {
        $values = (new Collection($values))
            ->reject(fn ($value, $column): bool => $this->isJsonSelector($column) && is_bool($value))
            ->map(fn ($value) => is_array($value) ? json_encode($value) : $value)
            ->all();

        return parent::prepareBindingsForUpdate($bindings, $values);
    }

    /**
     * Compile a delete query that does not use joins.
     *
     * @param  string  $table
     * @param  string  $where
     */
    protected function compileDeleteWithoutJoins(Builder $query, $table, $where): string
    {
        $sql = parent::compileDeleteWithoutJoins($query, $table, $where);

        // When using MySQL, delete statements may contain order by statements and limits
        // so we will compile both of those here. Once we have finished compiling this
        // we will return the completed SQL statement so it will be executed for us.
        if (! empty($query->orders)) {
            $sql .= ' '.$this->compileOrders($query, $query->orders);
        }

        if (isset($query->limit)) {
            $sql .= ' '.$this->compileLimit($query, $query->limit);
        }

        return $sql;
    }

    /**
     * Compile a query to get the number of open connections for a database.
     */
    public function compileThreadCount(): string
    {
        return 'select variable_value as `Value` from performance_schema.session_status where variable_name = \'threads_connected\'';
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     */
    protected function wrapValue($value): string
    {
        return $value === '*' ? $value : '`'.str_replace('`', '``', $value).'`';
    }

    /**
     * Wrap the given JSON selector.
     *
     * @param  string  $value
     */
    protected function wrapJsonSelector($value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($value);

        return 'json_unquote(json_extract('.$field.$path.'))';
    }

    /**
     * Wrap the given JSON selector for boolean values.
     *
     * @param  string  $value
     */
    protected function wrapJsonBooleanSelector($value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($value);

        return 'json_extract('.$field.$path.')';
    }
}
