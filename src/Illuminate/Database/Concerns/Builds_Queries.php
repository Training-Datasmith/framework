<?php

declare (strict_types=1);
namespace Illuminate\Database\Concerns;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Multiple_Records_Found_Exception;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Record_Not_Found_Exception;
use Illuminate\Database\Records_Not_Found_Exception;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\Cursor_Paginator;
use Illuminate\Pagination\Length_Aware_Paginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Lazy_Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use InvalidArgumentException;
use RuntimeException;
/**
 * @template TValue
 *
 * @mixin \Illuminate\Database\Query\Builder
 */
trait Builds_Queries
{
    use Conditionable;
    /**
     * Chunk the results of the query.
     *
     * @param  int  $count
     * @param  callable(\Illuminate\Support\Collection<int, TValue>, int): mixed  $callback
     */
    public function chunk($count, callable $callback): bool
    {
        $this->enforce_order_by();
        $skip = $this->get_offset();
        $remaining = $this->get_limit();
        $page = 1;
        do {
            $offset = ($page - 1) * $count + (int) $skip;
            $limit = is_null($remaining) ? $count : min($count, $remaining);
            if ($limit == 0) {
                break;
            }
            $results = $this->offset($offset)->limit($limit)->get();
            $count_results = $results->count();
            if ($count_results == 0) {
                break;
            }
            if (!is_null($remaining)) {
                $remaining = max($remaining - $count_results, 0);
            }
            if ($callback($results, $page) === false) {
                return false;
            }
            unset($results);
            $page++;
        } while ($count_results == $count);
        return true;
    }
    /**
     * Run a map over each item while chunking.
     *
     * @template TReturn
     *
     * @param  callable(TValue): TReturn  $callback
     * @param  int  $count
     * @return \Illuminate\Support\Collection<int, TReturn>
     */
    public function chunk_map(callable $callback, $count = 1000): \Illuminate\Support\Collection
    {
        $collection = new Collection();
        $this->chunk($count, function ($items) use ($collection, $callback): void {
            $items->each(function ($item) use ($collection, $callback): void {
                $collection->push($callback($item));
            });
        });
        return $collection;
    }
    /**
     * Execute a callback over each item while chunking.
     *
     * @param  callable(TValue, int): mixed  $callback
     * @param  int  $count
     * @return bool
     *
     * @throws \RuntimeException
     */
    public function each(callable $callback, $count = 1000)
    {
        return $this->chunk($count, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
        });
    }
    /**
     * Chunk the results of a query by comparing IDs.
     *
     * @param  int  $count
     * @param  callable(\Illuminate\Support\Collection<int, TValue>, int): mixed  $callback
     * @param  string|null  $column
     * @param  string|null  $alias
     * @return bool
     */
    public function chunk_by_id($count, callable $callback, $column = null, $alias = null)
    {
        return $this->ordered_chunk_by_id($count, $callback, $column, $alias);
    }
    /**
     * Chunk the results of a query by comparing IDs in descending order.
     *
     * @param  int  $count
     * @param  callable(\Illuminate\Support\Collection<int, TValue>, int): mixed  $callback
     * @param  string|null  $column
     * @param  string|null  $alias
     * @return bool
     */
    public function chunk_by_id_desc($count, callable $callback, $column = null, $alias = null)
    {
        return $this->ordered_chunk_by_id($count, $callback, $column, $alias, descending: true);
    }
    /**
     * Chunk the results of a query by comparing IDs in a given order.
     *
     * @param  int  $count
     * @param  callable(\Illuminate\Support\Collection<int, TValue>, int): mixed  $callback
     * @param  string|null  $column
     * @param  string|null  $alias
     * @param  bool  $descending
     *
     * @throws \RuntimeException
     */
    public function ordered_chunk_by_id($count, callable $callback, $column = null, $alias = null, $descending = false): bool
    {
        $column ??= $this->default_key_name();
        $alias ??= $column;
        $last_id = null;
        $skip = $this->get_offset();
        $remaining = $this->get_limit();
        $page = 1;
        do {
            $clone = clone $this;
            if ($skip && $page > 1) {
                $clone->offset(0);
            }
            $limit = is_null($remaining) ? $count : min($count, $remaining);
            if ($limit == 0) {
                break;
            }
            // We'll execute the query for the given page and get the results. If there are
            // no results we can just break and return from here. When there are results
            // we will call the callback with the current chunk of these results here.
            if ($descending) {
                $results = $clone->for_page_before_id($limit, $last_id, $column)->get();
            } else {
                $results = $clone->for_page_after_id($limit, $last_id, $column)->get();
            }
            $count_results = $results->count();
            if ($count_results == 0) {
                break;
            }
            if (!is_null($remaining)) {
                $remaining = max($remaining - $count_results, 0);
            }
            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if ($callback($results, $page) === false) {
                return false;
            }
            $last_id = data_get($results->last(), $alias);
            if ($last_id === null) {
                throw new RuntimeException("The chunkById operation was aborted because the [{$alias}] column is not present in the query result.");
            }
            unset($results);
            $page++;
        } while ($count_results == $count);
        return true;
    }
    /**
     * Execute a callback over each item while chunking by ID.
     *
     * @param  callable(TValue, int): mixed  $callback
     * @param  int  $count
     * @param  string|null  $column
     * @param  string|null  $alias
     * @return bool
     */
    public function each_by_id(callable $callback, $count = 1000, $column = null, $alias = null)
    {
        return $this->chunk_by_id($count, function ($results, $page) use ($callback, $count) {
            foreach ($results as $key => $value) {
                if ($callback($value, ($page - 1) * $count + $key) === false) {
                    return false;
                }
            }
        }, $column, $alias);
    }
    /**
     * Query lazily, by chunks of the given size.
     *
     * @param  int  $chunkSize
     * @return \Illuminate\Support\LazyCollection<int, TValue>
     *
     * @throws \InvalidArgumentException
     */
    public function lazy($chunk_size = 1000): \Illuminate\Support\Lazy_Collection
    {
        if ($chunk_size < 1) {
            throw new InvalidArgumentException('The chunk size should be at least 1');
        }
        $this->enforce_order_by();
        return new Lazy_Collection(function () use ($chunk_size) {
            $page = 1;
            while (true) {
                $results = $this->for_page($page++, $chunk_size)->get();
                foreach ($results as $result) {
                    yield $result;
                }
                if ($results->count() < $chunk_size) {
                    return;
                }
            }
        });
    }
    /**
     * Query lazily, by chunking the results of a query by comparing IDs.
     *
     * @param  int  $chunkSize
     * @param  string|null  $column
     * @param  string|null  $alias
     * @return \Illuminate\Support\LazyCollection<int, TValue>
     *
     * @throws \InvalidArgumentException
     */
    public function lazy_by_id($chunk_size = 1000, $column = null, $alias = null)
    {
        return $this->ordered_lazy_by_id($chunk_size, $column, $alias);
    }
    /**
     * Query lazily, by chunking the results of a query by comparing IDs in descending order.
     *
     * @param  int  $chunkSize
     * @param  string|null  $column
     * @param  string|null  $alias
     * @return \Illuminate\Support\LazyCollection<int, TValue>
     *
     * @throws \InvalidArgumentException
     */
    public function lazy_by_id_desc($chunk_size = 1000, $column = null, $alias = null)
    {
        return $this->ordered_lazy_by_id($chunk_size, $column, $alias, true);
    }
    /**
     * Query lazily, by chunking the results of a query by comparing IDs in a given order.
     *
     * @param  int  $chunkSize
     * @param  string|null  $column
     * @param  string|null  $alias
     * @param  bool  $descending
     *
     * @throws \InvalidArgumentException
     */
    protected function ordered_lazy_by_id($chunk_size = 1000, $column = null, $alias = null, $descending = false): \Illuminate\Support\Lazy_Collection
    {
        if ($chunk_size < 1) {
            throw new InvalidArgumentException('The chunk size should be at least 1');
        }
        $column ??= $this->default_key_name();
        $alias ??= $column;
        return new Lazy_Collection(function () use ($chunk_size, $column, $alias, $descending) {
            $last_id = null;
            while (true) {
                $clone = clone $this;
                if ($descending) {
                    $results = $clone->for_page_before_id($chunk_size, $last_id, $column)->get();
                } else {
                    $results = $clone->for_page_after_id($chunk_size, $last_id, $column)->get();
                }
                foreach ($results as $result) {
                    yield $result;
                }
                if ($results->count() < $chunk_size) {
                    return;
                }
                $last_id = $results->last()->{$alias};
                if ($last_id === null) {
                    throw new RuntimeException("The lazyById operation was aborted because the [{$alias}] column is not present in the query result.");
                }
            }
        });
    }
    /**
     * Execute the query and get the first result.
     *
     * @param  array|string  $columns
     * @return TValue|null
     */
    public function first($columns = ['*'])
    {
        return $this->limit(1)->get($columns)->first();
    }
    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @param  array|string  $columns
     * @param  string|null  $message
     * @return TValue
     *
     * @throws \Illuminate\Database\RecordNotFoundException
     */
    public function first_or_fail($columns = ['*'], $message = null)
    {
        if (!is_null($result = $this->first($columns))) {
            return $result;
        }
        throw new Record_Not_Found_Exception($message ?: 'No record found for the given query.');
    }
    /**
     * Execute the query and get the first result if it's the sole matching record.
     *
     * @param  array|string  $columns
     * @return TValue
     *
     * @throws \Illuminate\Database\RecordsNotFoundException
     * @throws \Illuminate\Database\MultipleRecordsFoundException
     */
    public function sole($columns = ['*'])
    {
        $result = $this->limit(2)->get($columns);
        $count = $result->count();
        if ($count === 0) {
            throw new Records_Not_Found_Exception();
        }
        if ($count > 1) {
            throw new Multiple_Records_Found_Exception($count);
        }
        return $result->first();
    }
    /**
     * Paginate the given query using a cursor paginator.
     *
     * @param  int  $perPage
     * @param  array|string  $columns
     * @param  string  $cursorName
     * @param  \Illuminate\Pagination\Cursor|string|null  $cursor
     * @return \Illuminate\Contracts\Pagination\CursorPaginator
     */
    protected function paginate_using_cursor($per_page, $columns = ['*'], $cursor_name = 'cursor', $cursor = null)
    {
        if (!$cursor instanceof Cursor) {
            $cursor = is_string($cursor) ? Cursor::from_encoded($cursor) : Cursor_Paginator::resolve_current_cursor($cursor_name, $cursor);
        }
        $orders = $this->ensure_order_for_cursor_pagination(!is_null($cursor) && $cursor->points_to_previous_items());
        if (!is_null($cursor)) {
            // Reset the union bindings so we can add the cursor where in the correct position...
            $this->set_bindings([], 'union');
            $add_cursor_conditions = function (self $builder, $previous_column, $original_column, $i) use (&$add_cursor_conditions, $cursor, $orders): void {
                $union_builders = $builder->get_union_builders();
                if (!is_null($previous_column)) {
                    $original_column ??= $this->get_original_column_name_for_cursor_pagination($this, $previous_column);
                    $builder->where(Str::contains($original_column, ['(', ')']) ? new Expression($original_column) : $original_column, '=', $cursor->parameter($previous_column));
                    $union_builders->each(function ($union_builder) use ($previous_column, $cursor): void {
                        $union_builder->where($this->get_original_column_name_for_cursor_pagination($union_builder, $previous_column), '=', $cursor->parameter($previous_column));
                        $this->add_binding($union_builder->get_raw_bindings()['where'], 'union');
                    });
                }
                $builder->where(function (self $second_builder) use ($add_cursor_conditions, $cursor, $orders, $i, $union_builders): void {
                    ['column' => $column, 'direction' => $direction] = $orders[$i];
                    $original_column = $this->get_original_column_name_for_cursor_pagination($this, $column);
                    $second_builder->where(Str::contains($original_column, ['(', ')']) ? new Expression($original_column) : $original_column, $direction === 'asc' ? '>' : '<', $cursor->parameter($column));
                    if ($i < $orders->count() - 1) {
                        $second_builder->or_where(function (self $third_builder) use ($add_cursor_conditions, $column, $original_column, $i): void {
                            $add_cursor_conditions($third_builder, $column, $original_column, $i + 1);
                        });
                    }
                    $union_builders->each(function ($union_builder) use ($column, $direction, $cursor, $i, $orders, $add_cursor_conditions): void {
                        $union_wheres = $union_builder->get_raw_bindings()['where'];
                        $original_column = $this->get_original_column_name_for_cursor_pagination($union_builder, $column);
                        $union_builder->where(function ($union_builder) use ($column, $direction, $cursor, $i, $orders, $add_cursor_conditions, $original_column, $union_wheres): void {
                            $union_builder->where($original_column, $direction === 'asc' ? '>' : '<', $cursor->parameter($column));
                            if ($i < $orders->count() - 1) {
                                $union_builder->or_where(function (self $fourth_builder) use ($add_cursor_conditions, $column, $original_column, $i): void {
                                    $add_cursor_conditions($fourth_builder, $column, $original_column, $i + 1);
                                });
                            }
                            $this->add_binding($union_wheres, 'union');
                            $this->add_binding($union_builder->get_raw_bindings()['where'], 'union');
                        });
                    });
                });
            };
            $add_cursor_conditions($this, null, null, 0);
        }
        $this->limit($per_page + 1);
        return $this->cursor_paginator($this->get($columns), $per_page, $cursor, ['path' => Paginator::resolve_current_path(), 'cursorName' => $cursor_name, 'parameters' => $orders->pluck('column')->to_array()]);
    }
    /**
     * Get the original column name of the given column, without any aliasing.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>  $builder
     */
    protected function get_original_column_name_for_cursor_pagination($builder, string $parameter): string
    {
        $columns = $builder instanceof Builder ? $builder->get_query()->get_columns() : $builder->get_columns();
        if (!is_null($columns)) {
            foreach ($columns as $column) {
                if (($position = strripos($column, ' as ')) !== false) {
                    $original = substr($column, 0, $position);
                    $alias = substr($column, $position + 4);
                    if ($parameter === $alias || $builder->get_grammar()->wrap($parameter) === $alias) {
                        return $original;
                    }
                }
            }
        }
        return $parameter;
    }
    /**
     * Create a new length-aware paginator instance.
     *
     * @param  \Illuminate\Support\Collection  $items
     * @param  int  $total
     * @param  int  $perPage
     * @param  int  $currentPage
     * @param  array  $options
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    protected function paginator($items, $total, $per_page, $current_page, $options)
    {
        return Container::get_instance()->make_with(Length_Aware_Paginator::class, compact('items', 'total', 'perPage', 'currentPage', 'options'));
    }
    /**
     * Create a new simple paginator instance.
     *
     * @param  \Illuminate\Support\Collection  $items
     * @param  int  $perPage
     * @param  int  $currentPage
     * @param  array  $options
     * @return \Illuminate\Pagination\Paginator
     */
    protected function simple_paginator($items, $per_page, $current_page, $options)
    {
        return Container::get_instance()->make_with(Paginator::class, compact('items', 'perPage', 'currentPage', 'options'));
    }
    /**
     * Create a new cursor paginator instance.
     *
     * @param  \Illuminate\Support\Collection  $items
     * @param  int  $perPage
     * @param  \Illuminate\Pagination\Cursor  $cursor
     * @param  array  $options
     * @return \Illuminate\Pagination\CursorPaginator
     */
    protected function cursor_paginator($items, $per_page, $cursor, $options)
    {
        return Container::get_instance()->make_with(Cursor_Paginator::class, compact('items', 'perPage', 'cursor', 'options'));
    }
    /**
     * Pass the query to a given callback and then return it.
     *
     * @param  callable($this): mixed  $callback
     * @return $this
     */
    public function tap($callback)
    {
        $callback($this);
        return $this;
    }
    /**
     * Pass the query to a given callback and return the result.
     *
     * @template TReturn
     *
     * @param  (callable($this): TReturn)  $callback
     * @return (TReturn is null|void ? $this : TReturn)
     */
    public function pipe($callback)
    {
        return $callback($this) ?? $this;
    }
}