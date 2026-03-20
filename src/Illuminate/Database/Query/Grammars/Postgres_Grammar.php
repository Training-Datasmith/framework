<?php

declare (strict_types=1);
namespace Illuminate\Database\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Join_Lateral_Clause;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
class Postgres_Grammar extends Grammar
{
    /**
     * All of the available clause operators.
     *
     * @var string[]
     */
    protected $operators = ['=', '<', '>', '<=', '>=', '<>', '!=', 'like', 'not like', 'between', 'ilike', 'not ilike', '~', '&', '|', '#', '<<', '>>', '<<=', '>>=', '&&', '@>', '<@', '?', '?|', '?&', '||', '-', '@?', '@@', '#-', 'is distinct from', 'is not distinct from'];
    /**
     * The Postgres grammar specific custom operators.
     *
     * @var array
     */
    protected static $custom_operators = [];
    /**
     * The grammar specific bitwise operators.
     *
     * @var array
     */
    protected $bitwise_operators = ['~', '&', '|', '#', '<<', '>>', '<<=', '>>='];
    /**
     * Indicates if the cascade option should be used when truncating.
     *
     * @var bool
     */
    protected static $cascade_truncate = true;
    /**
     * Compile a basic where clause.
     */
    protected function where_basic(Builder $query, array $where): string
    {
        if (str_contains(strtolower((string) $where['operator']), 'like')) {
            return sprintf('%s::text %s %s', $this->wrap($where['column']), $where['operator'], $this->parameter($where['value']));
        }
        return parent::where_basic($query, $where);
    }
    /**
     * Compile a bitwise operator where clause.
     *
     * @param  array  $where
     */
    protected function where_bitwise(Builder $query, $where): string
    {
        $value = $this->parameter($where['value']);
        $operator = str_replace('?', '??', $where['operator']);
        return '(' . $this->wrap($where['column']) . ' ' . $operator . ' ' . $value . ')::bool';
    }
    /**
     * Compile a "where like" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function where_like(Builder $query, $where)
    {
        $where['operator'] = $where['not'] ? 'not ' : '';
        $where['operator'] .= $where['caseSensitive'] ? 'like' : 'ilike';
        return $this->where_basic($query, $where);
    }
    /**
     * Compile a "where date" clause.
     *
     * @param  array  $where
     */
    protected function where_date(Builder $query, $where): string
    {
        $column = $this->wrap($where['column']);
        $value = $this->parameter($where['value']);
        if ($this->is_json_selector($where['column'])) {
            $column = '(' . $column . ')';
        }
        return $column . '::date ' . $where['operator'] . ' ' . $value;
    }
    /**
     * Compile a "where time" clause.
     *
     * @param  array  $where
     */
    protected function where_time(Builder $query, $where): string
    {
        $column = $this->wrap($where['column']);
        $value = $this->parameter($where['value']);
        if ($this->is_json_selector($where['column'])) {
            $column = '(' . $column . ')';
        }
        return $column . '::time ' . $where['operator'] . ' ' . $value;
    }
    /**
     * Compile a date based where clause.
     *
     * @param  string  $type
     * @param  array  $where
     */
    protected function date_based_where($type, Builder $query, $where): string
    {
        $value = $this->parameter($where['value']);
        return 'extract(' . $type . ' from ' . $this->wrap($where['column']) . ') ' . $where['operator'] . ' ' . $value;
    }
    /**
     * Compile a "where fulltext" clause.
     *
     * @param  array  $where
     */
    public function where_full_text(Builder $query, $where): string
    {
        $language = $where['options']['language'] ?? 'english';
        if (!in_array($language, $this->valid_full_text_languages())) {
            $language = 'english';
        }
        $is_vector = $where['options']['vector'] ?? false;
        $columns = (new Collection($where['columns']))->map(fn($column) => $is_vector ? $this->wrap($column) : "to_tsvector('{$language}', {$this->wrap($column)})")->implode(' || ');
        $mode = 'plainto_tsquery';
        if (($where['options']['mode'] ?? []) === 'phrase') {
            $mode = 'phraseto_tsquery';
        }
        if (($where['options']['mode'] ?? []) === 'websearch') {
            $mode = 'websearch_to_tsquery';
        }
        if (($where['options']['mode'] ?? []) === 'raw') {
            $mode = 'to_tsquery';
        }
        return "({$columns}) @@ {$mode}('{$language}', {$this->parameter($where['value'])})";
    }
    /**
     * Get an array of valid full text languages.
     */
    protected function valid_full_text_languages(): array
    {
        return ['simple', 'arabic', 'danish', 'dutch', 'english', 'finnish', 'french', 'german', 'hungarian', 'indonesian', 'irish', 'italian', 'lithuanian', 'nepali', 'norwegian', 'portuguese', 'romanian', 'russian', 'spanish', 'swedish', 'tamil', 'turkish'];
    }
    /**
     * Compile the "select *" portion of the query.
     *
     * @param  array  $columns
     * @return string|null
     */
    protected function compile_columns(Builder $query, $columns)
    {
        // If the query is actually performing an aggregating select, we will let that
        // compiler handle the building of the select clauses, as it will need some
        // more syntax that is best handled by that function to keep things neat.
        if (!is_null($query->aggregate)) {
            return;
        }
        if (is_array($query->distinct)) {
            $select = 'select distinct on (' . $this->columnize($query->distinct) . ') ';
        } elseif ($query->distinct) {
            $select = 'select distinct ';
        } else {
            $select = 'select ';
        }
        return $select . $this->columnize($columns);
    }
    /**
     * Compile a "JSON contains" statement into SQL.
     *
     * @param  string  $column
     * @param  string  $value
     */
    protected function compile_json_contains($column, $value): string
    {
        $column = str_replace('->>', '->', $this->wrap($column));
        return '(' . $column . ')::jsonb @> ' . $value;
    }
    /**
     * Compile a "JSON contains key" statement into SQL.
     *
     * @param  string  $column
     */
    protected function compile_json_contains_key($column): string
    {
        $segments = explode('->', $column);
        $last_segment = array_pop($segments);
        if (filter_var($last_segment, FILTER_VALIDATE_INT) !== false) {
            $i = $last_segment;
        } elseif (preg_match('/\[(-?[0-9]+)\]$/', $last_segment, $matches)) {
            $segments[] = Str::before_last($last_segment, $matches[0]);
            $i = $matches[1];
        }
        $column = str_replace('->>', '->', $this->wrap(implode('->', $segments)));
        if (isset($i)) {
            return vsprintf('case when %s then %s else false end', ['jsonb_typeof((' . $column . ")::jsonb) = 'array'", 'jsonb_array_length((' . $column . ')::jsonb) >= ' . ($i < 0 ? abs($i) : $i + 1)]);
        }
        $key = "'" . str_replace("'", "''", $last_segment) . "'";
        return 'coalesce((' . $column . ')::jsonb ?? ' . $key . ', false)';
    }
    /**
     * Compile a "JSON length" statement into SQL.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  string  $value
     */
    protected function compile_json_length($column, $operator, $value): string
    {
        $column = str_replace('->>', '->', $this->wrap($column));
        return 'jsonb_array_length((' . $column . ')::jsonb) ' . $operator . ' ' . $value;
    }
    /**
     * Compile a single having clause.
     *
     * @return string
     */
    protected function compile_having(array $having)
    {
        if ($having['type'] === 'Bitwise') {
            return $this->compile_having_bitwise($having);
        }
        return parent::compile_having($having);
    }
    /**
     * Compile a having clause involving a bitwise operator.
     */
    protected function compile_having_bitwise(array $having): string
    {
        $column = $this->wrap($having['column']);
        $parameter = $this->parameter($having['value']);
        return '(' . $column . ' ' . $having['operator'] . ' ' . $parameter . ')::bool';
    }
    /**
     * Compile the lock into SQL.
     *
     * @param  bool|string  $value
     */
    protected function compile_lock(Builder $query, $value): string
    {
        if (!is_string($value)) {
            return $value ? 'for update' : 'for share';
        }
        return $value;
    }
    /**
     * Compile an insert ignore statement into SQL.
     */
    public function compile_insert_or_ignore(Builder $query, array $values): string
    {
        return $this->compile_insert($query, $values) . ' on conflict do nothing';
    }
    /**
     * Compile an insert ignore statement using a subquery into SQL.
     */
    public function compile_insert_or_ignore_using(Builder $query, array $columns, string $sql): string
    {
        return $this->compile_insert_using($query, $columns, $sql) . ' on conflict do nothing';
    }
    /**
     * Compile an insert and get ID statement into SQL.
     *
     * @param  array  $values
     * @param  string|null  $sequence
     */
    public function compile_insert_get_id(Builder $query, $values, $sequence): string
    {
        return $this->compile_insert($query, $values) . ' returning ' . $this->wrap($sequence ?: 'id');
    }
    /**
     * Compile an update statement into SQL.
     */
    public function compile_update(Builder $query, array $values): string
    {
        if (isset($query->joins) || isset($query->limit)) {
            return $this->compile_update_with_joins_or_limit($query, $values);
        }
        return parent::compile_update($query, $values);
    }
    /**
     * Compile the columns for an update statement.
     */
    protected function compile_update_columns(Builder $query, array $values): string
    {
        return (new Collection($values))->map(function ($value, $key): string {
            $column = last(explode('.', $key));
            if ($this->is_json_selector($key)) {
                return $this->compile_json_update_column($column, $value);
            }
            return $this->wrap($column) . ' = ' . $this->parameter($value);
        })->implode(', ');
    }
    /**
     * Compile an "upsert" statement into SQL.
     */
    public function compile_upsert(Builder $query, array $values, array $unique_by, array $update): string
    {
        $sql = $this->compile_insert($query, $values);
        $sql .= ' on conflict (' . $this->columnize($unique_by) . ') do update set ';
        $columns = (new Collection($update))->map(fn($value, $key): string => is_numeric($key) ? $this->wrap($value) . ' = ' . $this->wrap_value('excluded') . '.' . $this->wrap($value) : $this->wrap($key) . ' = ' . $this->parameter($value))->implode(', ');
        return $sql . $columns;
    }
    /**
     * Compile a "lateral join" clause.
     */
    public function compile_join_lateral(Join_Lateral_Clause $join, string $expression): string
    {
        return trim("{$join->type} join lateral {$expression} on true");
    }
    /**
     * Prepares a JSON column being updated using the JSONB_SET function.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    protected function compile_json_update_column($key, $value): string
    {
        $segments = explode('->', $key);
        $field = $this->wrap(array_shift($segments));
        $path = "'{" . implode(',', $this->wrap_json_path_attributes($segments, '"')) . "}'";
        return "{$field} = jsonb_set({$field}::jsonb, {$path}, {$this->parameter($value)})";
    }
    /**
     * Compile an update from statement into SQL.
     */
    public function compile_update_from(Builder $query, array $values): string
    {
        $table = $this->wrap_table($query->from);
        // Each one of the columns in the update statements needs to be wrapped in the
        // keyword identifiers, also a place-holder needs to be created for each of
        // the values in the list of bindings so we can make the sets statements.
        $columns = $this->compile_update_columns($query, $values);
        $from = '';
        if (isset($query->joins)) {
            // When using Postgres, updates with joins list the joined tables in the from
            // clause, which is different than other systems like MySQL. Here, we will
            // compile out the tables that are joined and add them to a from clause.
            $froms = (new Collection($query->joins))->map(fn($join) => $this->wrap_table($join->table))->all();
            if (count($froms) > 0) {
                $from = ' from ' . implode(', ', $froms);
            }
        }
        $where = $this->compile_update_wheres($query);
        return trim("update {$table} set {$columns}{$from} {$where}");
    }
    /**
     * Compile the additional where clauses for updates with joins.
     *
     * @return string
     */
    protected function compile_update_wheres(Builder $query)
    {
        $base_wheres = $this->compile_wheres($query);
        if (!isset($query->joins)) {
            return $base_wheres;
        }
        // Once we compile the join constraints, we will either use them as the where
        // clause or append them to the existing base where clauses. If we need to
        // strip the leading boolean we will do so when using as the only where.
        $join_wheres = $this->compile_update_join_wheres($query);
        if (trim($base_wheres) == '') {
            return 'where ' . $this->remove_leading_boolean($join_wheres);
        }
        return $base_wheres . ' ' . $join_wheres;
    }
    /**
     * Compile the "join" clause where clauses for an update.
     */
    protected function compile_update_join_wheres(Builder $query): string
    {
        $join_wheres = [];
        // Here we will just loop through all of the join constraints and compile them
        // all out then implode them. This should give us "where" like syntax after
        // everything has been built and then we will join it to the real wheres.
        foreach ($query->joins as $join) {
            foreach ($join->wheres as $where) {
                $method = "where{$where['type']}";
                $join_wheres[] = $where['boolean'] . ' ' . $this->{$method}($query, $where);
            }
        }
        return implode(' ', $join_wheres);
    }
    /**
     * Prepare the bindings for an update statement.
     */
    public function prepare_bindings_for_update_from(array $bindings, array $values): array
    {
        $values = (new Collection($values))->map(fn($value, $column) => is_array($value) || $this->is_json_selector($column) && !$this->is_expression($value) ? json_encode($value) : $value)->all();
        $bindings_without_where = Arr::except($bindings, ['select', 'where']);
        return array_values(array_merge($values, $bindings['where'], Arr::flatten($bindings_without_where)));
    }
    /**
     * Compile an update statement with joins or limit into SQL.
     */
    protected function compile_update_with_joins_or_limit(Builder $query, array $values): string
    {
        $table = $this->wrap_table($query->from);
        $columns = $this->compile_update_columns($query, $values);
        $alias = last(preg_split('/\s+as\s+/i', $query->from));
        $select_sql = $this->compile_select($query->select($alias . '.ctid'));
        return "update {$table} set {$columns} where {$this->wrap('ctid')} in ({$select_sql})";
    }
    /**
     * Prepare the bindings for an update statement.
     */
    #[\Override]
    public function prepare_bindings_for_update(array $bindings, array $values): array
    {
        $values = (new Collection($values))->map(fn($value, $column) => is_array($value) || $this->is_json_selector($column) && !$this->is_expression($value) ? json_encode($value) : $value)->all();
        $clean_bindings = Arr::except($bindings, 'select');
        $values = Arr::flatten(array_map(fn($value) => value($value), $values));
        return array_values(array_merge($values, Arr::flatten($clean_bindings)));
    }
    /**
     * Compile a delete statement into SQL.
     */
    public function compile_delete(Builder $query): string
    {
        if (isset($query->joins) || isset($query->limit)) {
            return $this->compile_delete_with_joins_or_limit($query);
        }
        return parent::compile_delete($query);
    }
    /**
     * Compile a delete statement with joins or limit into SQL.
     */
    protected function compile_delete_with_joins_or_limit(Builder $query): string
    {
        $table = $this->wrap_table($query->from);
        $alias = last(preg_split('/\s+as\s+/i', $query->from));
        $select_sql = $this->compile_select($query->select($alias . '.ctid'));
        return "delete from {$table} where {$this->wrap('ctid')} in ({$select_sql})";
    }
    /**
     * Compile a truncate table statement into SQL.
     */
    public function compile_truncate(Builder $query): array
    {
        return ['truncate ' . $this->wrap_table($query->from) . ' restart identity' . (static::$cascade_truncate ? ' cascade' : '') => []];
    }
    /**
     * Compile a query to get the number of open connections for a database.
     */
    public function compile_thread_count(): string
    {
        return 'select count(*) as "Value" from pg_stat_activity';
    }
    /**
     * Wrap the given JSON selector.
     *
     * @param  string  $value
     */
    protected function wrap_json_selector($value): string
    {
        $path = explode('->', $value);
        $field = $this->wrap_segments(explode('.', array_shift($path)));
        $wrapped_path = $this->wrap_json_path_attributes($path);
        $attribute = array_pop($wrapped_path);
        if (!empty($wrapped_path)) {
            return $field . '->' . implode('->', $wrapped_path) . '->>' . $attribute;
        }
        return $field . '->>' . $attribute;
    }
    /**
     * Wrap the given JSON selector for boolean values.
     *
     * @param  string  $value
     */
    protected function wrap_json_boolean_selector($value): string
    {
        $selector = str_replace('->>', '->', $this->wrap_json_selector($value));
        return '(' . $selector . ')::jsonb';
    }
    /**
     * Wrap the given JSON boolean value.
     *
     * @param  string  $value
     */
    protected function wrap_json_boolean_value($value): string
    {
        return "'" . $value . "'::jsonb";
    }
    /**
     * Wrap the attributes of the given JSON path.
     *
     * @param  array  $path
     * @return array
     */
    protected function wrap_json_path_attributes($path)
    {
        $quote = func_num_args() === 2 ? func_get_arg(1) : "'";
        return (new Collection($path))->map(fn($attribute) => $this->parse_json_path_array_keys($attribute))->collapse()->map(fn($attribute) => filter_var($attribute, FILTER_VALIDATE_INT) !== false ? $attribute : $quote . $attribute . $quote)->all();
    }
    /**
     * Parse the given JSON path attribute for array keys.
     *
     * @param  string  $attribute
     * @return array
     */
    protected function parse_json_path_array_keys($attribute)
    {
        if (preg_match('/(\[[^\]]+\])+$/', $attribute, $parts)) {
            $key = Str::before_last($attribute, $parts[0]);
            preg_match_all('/\[([^\]]+)\]/', $parts[0], $keys);
            return (new Collection([$key]))->merge($keys[1])->diff('')->values()->all();
        }
        return [$attribute];
    }
    /**
     * Substitute the given bindings into the given raw SQL query.
     *
     * @param  string  $sql
     * @param  array  $bindings
     */
    public function substitute_bindings_into_raw_sql($sql, $bindings): string
    {
        $query = parent::substitute_bindings_into_raw_sql($sql, $bindings);
        foreach ($this->operators as $operator) {
            if (!str_contains($operator, '?')) {
                continue;
            }
            $query = str_replace(str_replace('?', '??', $operator), $operator, $query);
        }
        return $query;
    }
    /**
     * Get the Postgres grammar specific operators.
     */
    public function get_operators(): array
    {
        return array_values(array_unique(array_merge(parent::get_operators(), static::$custom_operators)));
    }
    /**
     * Set any Postgres grammar specific custom operators.
     */
    public static function custom_operators(array $operators): void
    {
        static::$custom_operators = array_values(array_merge(static::$custom_operators, array_filter(array_filter($operators, is_string(...)))));
    }
    /**
     * Enable or disable the "cascade" option when compiling the truncate statement.
     */
    public static function cascade_on_truncate(bool $value = true): void
    {
        static::$cascade_truncate = $value;
    }
    /**
     * @deprecated use cascadeOnTruncate
     */
    public static function cascade_on_trucate(bool $value = true): void
    {
        self::cascade_on_truncate($value);
    }
}