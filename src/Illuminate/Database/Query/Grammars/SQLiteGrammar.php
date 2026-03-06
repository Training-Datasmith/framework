<?php

declare(strict_types=1);

namespace Illuminate\Database\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SQLiteGrammar extends Grammar
{
    /**
     * All of the available clause operators.
     *
     * @var string[]
     */
    protected $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like', 'ilike',
        '&', '|', '<<', '>>',
    ];

    /**
     * Compile the lock into SQL.
     *
     * @param  bool|string  $value
     */
    protected function compileLock(Builder $query, $value): string
    {
        return '';
    }

    /**
     * Wrap a union subquery in parentheses.
     *
     * @param  string  $sql
     */
    protected function wrapUnion($sql): string
    {
        return 'select * from ('.$sql.')';
    }

    /**
     * Compile a "where like" clause.
     *
     * @return string
     */
    protected function whereLike(Builder $query, array $where)
    {
        if ($where['caseSensitive'] == false) {
            return parent::whereLike($query, $where);
        }
        $where['operator'] = $where['not'] ? 'not glob' : 'glob';

        return $this->whereBasic($query, $where);
    }

    /**
     * Convert a LIKE pattern to a GLOB pattern using simple string replacement.
     *
     * @param  string  $value
     * @param  bool  $caseSensitive
     * @return string
     */
    public function prepareWhereLikeBinding($value, $caseSensitive)
    {
        return $caseSensitive === false ? $value : str_replace(
            ['*', '?', '%', '_'],
            ['[*]', '[?]', '*', '?'],
            $value
        );
    }

    /**
     * Compile a "where null safe equals" clause.
     *
     * @param  array  $where
     */
    protected function whereNullSafeEquals(Builder $query, $where): string
    {
        return $this->wrap($where['column']).' is '.$this->parameter($where['value']);
    }

    /**
     * Compile a "where date" clause.
     *
     * @param  array  $where
     */
    protected function whereDate(Builder $query, $where): string
    {
        return $this->dateBasedWhere('%Y-%m-%d', $query, $where);
    }

    /**
     * Compile a "where day" clause.
     *
     * @param  array  $where
     */
    protected function whereDay(Builder $query, $where): string
    {
        return $this->dateBasedWhere('%d', $query, $where);
    }

    /**
     * Compile a "where month" clause.
     *
     * @param  array  $where
     */
    protected function whereMonth(Builder $query, $where): string
    {
        return $this->dateBasedWhere('%m', $query, $where);
    }

    /**
     * Compile a "where year" clause.
     *
     * @param  array  $where
     */
    protected function whereYear(Builder $query, $where): string
    {
        return $this->dateBasedWhere('%Y', $query, $where);
    }

    /**
     * Compile a "where time" clause.
     *
     * @param  array  $where
     */
    protected function whereTime(Builder $query, $where): string
    {
        return $this->dateBasedWhere('%H:%M:%S', $query, $where);
    }

    /**
     * Compile a date based where clause.
     *
     * @param  string  $type
     * @param  array  $where
     */
    protected function dateBasedWhere($type, Builder $query, $where): string
    {
        $value = $this->parameter($where['value']);

        return "strftime('{$type}', {$this->wrap($where['column'])}) {$where['operator']} cast({$value} as text)";
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
        if ($indexHint->type !== 'force') {
            return '';
        }

        $index = $indexHint->index;

        if (! preg_match('/^[a-zA-Z0-9_$]+$/', $index)) {
            throw new InvalidArgumentException('Index name contains invalid characters.');
        }

        return "indexed by {$index}";
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

        return 'json_array_length('.$field.$path.') '.$operator.' '.$value;
    }

    /**
     * Compile a "JSON contains" statement into SQL.
     *
     * @param  string  $column
     * @param  mixed  $value
     */
    protected function compileJsonContains($column, $value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($column);

        return 'exists (select 1 from json_each('.$field.$path.') where '.$this->wrap('json_each.value').' is '.$value.')';
    }

    /**
     * Prepare the binding for a "JSON contains" statement.
     *
     * @param  mixed  $binding
     * @return mixed
     */
    public function prepareBindingForJsonContains($binding)
    {
        return $binding;
    }

    /**
     * Compile a "JSON contains key" statement into SQL.
     *
     * @param  string  $column
     */
    protected function compileJsonContainsKey($column): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($column);

        return 'json_type('.$field.$path.') is not null';
    }

    /**
     * Compile a group limit clause.
     */
    protected function compileGroupLimit(Builder $query): string
    {
        $version = $query->getConnection()->getServerVersion();

        if (version_compare($version, '3.25.0', '>=')) {
            return parent::compileGroupLimit($query);
        }

        $query->groupLimit = null;

        return $this->compileSelect($query);
    }

    /**
     * Compile an update statement into SQL.
     */
    public function compileUpdate(Builder $query, array $values): string
    {
        if (isset($query->joins) || isset($query->limit)) {
            return $this->compileUpdateWithJoinsOrLimit($query, $values);
        }

        return parent::compileUpdate($query, $values);
    }

    /**
     * Compile an insert ignore statement into SQL.
     *
     * @return string
     */
    public function compileInsertOrIgnore(Builder $query, array $values)
    {
        return Str::replaceFirst('insert', 'insert or ignore', $this->compileInsert($query, $values));
    }

    /**
     * Compile an insert ignore statement using a subquery into SQL.
     *
     * @return string
     */
    public function compileInsertOrIgnoreUsing(Builder $query, array $columns, string $sql)
    {
        return Str::replaceFirst('insert', 'insert or ignore', $this->compileInsertUsing($query, $columns, $sql));
    }

    /**
     * Compile the columns for an update statement.
     */
    protected function compileUpdateColumns(Builder $query, array $values): string
    {
        $jsonGroups = $this->groupJsonColumnsForUpdate($values);

        return (new Collection($values))
            ->reject(fn ($value, $key) => $this->isJsonSelector($key))
            ->merge($jsonGroups)
            ->map(function ($value, $key) use ($jsonGroups): string {
                $column = last(explode('.', $key));

                $value = isset($jsonGroups[$key]) ? $this->compileJsonPatch($column, $value) : $this->parameter($value);

                return $this->wrap($column).' = '.$value;
            })
            ->implode(', ');
    }

    /**
     * Compile an "upsert" statement into SQL.
     */
    public function compileUpsert(Builder $query, array $values, array $uniqueBy, array $update): string
    {
        $sql = $this->compileInsert($query, $values);

        $sql .= ' on conflict ('.$this->columnize($uniqueBy).') do update set ';

        $columns = (new Collection($update))->map(fn ($value, $key): string => is_numeric($key)
            ? $this->wrap($value).' = '.$this->wrapValue('excluded').'.'.$this->wrap($value)
            : $this->wrap($key).' = '.$this->parameter($value))->implode(', ');

        return $sql.$columns;
    }

    /**
     * Group the nested JSON columns.
     */
    protected function groupJsonColumnsForUpdate(array $values): array
    {
        $groups = [];

        foreach ($values as $key => $value) {
            if ($this->isJsonSelector($key)) {
                Arr::set($groups, str_replace('->', '.', Str::after($key, '.')), $value);
            }
        }

        return $groups;
    }

    /**
     * Compile a "JSON" patch statement into SQL.
     *
     * @param  string  $column
     * @param  mixed  $value
     */
    protected function compileJsonPatch($column, $value): string
    {
        return "json_patch(ifnull({$this->wrap($column)}, json('{}')), json({$this->parameter($value)}))";
    }

    /**
     * Compile an update statement with joins or limit into SQL.
     */
    protected function compileUpdateWithJoinsOrLimit(Builder $query, array $values): string
    {
        $table = $this->wrapTable($query->from);

        $columns = $this->compileUpdateColumns($query, $values);

        $alias = last(preg_split('/\s+as\s+/i', $query->from));

        $selectSql = $this->compileSelect($query->select($alias.'.rowid'));

        return "update {$table} set {$columns} where {$this->wrap('rowid')} in ({$selectSql})";
    }

    /**
     * Prepare the bindings for an update statement.
     */
    #[\Override]
    public function prepareBindingsForUpdate(array $bindings, array $values): array
    {
        $groups = $this->groupJsonColumnsForUpdate($values);

        $values = (new Collection($values))
            ->reject(fn ($value, $key) => $this->isJsonSelector($key))
            ->merge($groups)
            ->map(fn ($value) => is_array($value) ? json_encode($value) : $value)
            ->all();

        $cleanBindings = Arr::except($bindings, 'select');

        $values = Arr::flatten(array_map(fn ($value) => value($value), $values));

        return array_values(
            array_merge($values, Arr::flatten($cleanBindings))
        );
    }

    /**
     * Compile a delete statement into SQL.
     */
    public function compileDelete(Builder $query): string
    {
        if (isset($query->joins) || isset($query->limit)) {
            return $this->compileDeleteWithJoinsOrLimit($query);
        }

        return parent::compileDelete($query);
    }

    /**
     * Compile a delete statement with joins or limit into SQL.
     */
    protected function compileDeleteWithJoinsOrLimit(Builder $query): string
    {
        $table = $this->wrapTable($query->from);

        $alias = last(preg_split('/\s+as\s+/i', $query->from));

        $selectSql = $this->compileSelect($query->select($alias.'.rowid'));

        return "delete from {$table} where {$this->wrap('rowid')} in ({$selectSql})";
    }

    /**
     * Compile a truncate table statement into SQL.
     */
    public function compileTruncate(Builder $query): array
    {
        [$schema, $table] = $query->getConnection()->getSchemaBuilder()->parseSchemaAndTable($query->from);

        $schema = $schema ? $this->wrapValue($schema).'.' : '';

        return [
            'delete from '.$schema.'sqlite_sequence where name = ?' => [$query->getConnection()->getTablePrefix().$table],
            'delete from '.$this->wrapTable($query->from) => [],
        ];
    }

    /**
     * Wrap the given JSON selector.
     *
     * @param  string  $value
     */
    protected function wrapJsonSelector($value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($value);

        return 'json_extract('.$field.$path.')';
    }
}
