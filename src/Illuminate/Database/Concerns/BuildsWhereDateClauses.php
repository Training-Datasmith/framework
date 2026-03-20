<?php

declare (strict_types=1);
namespace Illuminate\Database\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Arr;
trait Builds_Where_Date_Clauses
{
    /**
     * Add a where clause to determine if a "date" column is in the past to the query.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function where_past($columns)
    {
        return $this->where_past_or_future($columns, '<', 'and');
    }
    /**
     * Add a where clause to determine if a "date" column is in the past or now to the query.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function where_now_or_past($columns)
    {
        return $this->where_past_or_future($columns, '<=', 'and');
    }
    /**
     * Add an "or where" clause to determine if a "date" column is in the past to the query.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function or_where_past($columns)
    {
        return $this->where_past_or_future($columns, '<', 'or');
    }
    /**
     * Add a where clause to determine if a "date" column is in the past or now to the query.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function or_where_now_or_past($columns)
    {
        return $this->where_past_or_future($columns, '<=', 'or');
    }
    /**
     * Add a where clause to determine if a "date" column is in the future to the query.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function where_future($columns)
    {
        return $this->where_past_or_future($columns, '>', 'and');
    }
    /**
     * Add a where clause to determine if a "date" column is in the future or now to the query.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function where_now_or_future($columns)
    {
        return $this->where_past_or_future($columns, '>=', 'and');
    }
    /**
     * Add an "or where" clause to determine if a "date" column is in the future to the query.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function or_where_future($columns)
    {
        return $this->where_past_or_future($columns, '>', 'or');
    }
    /**
     * Add an "or where" clause to determine if a "date" column is in the future or now to the query.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function or_where_now_or_future($columns)
    {
        return $this->where_past_or_future($columns, '>=', 'or');
    }
    /**
     * Add an "where" clause to determine if a "date" column is in the past or future.
     *
     * @param  array|string  $columns
     * @param  string  $operator
     * @param  string  $boolean
     * @return $this
     */
    protected function where_past_or_future($columns, $operator, $boolean)
    {
        $type = 'Basic';
        $value = Carbon::now();
        foreach (Arr::wrap($columns) as $column) {
            $this->wheres[] = compact('type', 'column', 'boolean', 'operator', 'value');
            $this->add_binding($value);
        }
        return $this;
    }
    /**
     * Add a "where date" clause to determine if a "date" column is today to the query.
     *
     * @param  array|string  $columns
     * @param  string  $boolean
     * @return $this
     */
    public function where_today($columns, $boolean = 'and')
    {
        return $this->where_today_before_or_after($columns, '=', $boolean);
    }
    /**
     * Add a "where date" clause to determine if a "date" column is before today.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function where_before_today($columns)
    {
        return $this->where_today_before_or_after($columns, '<', 'and');
    }
    /**
     * Add a "where date" clause to determine if a "date" column is today or before to the query.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function where_today_or_before($columns)
    {
        return $this->where_today_before_or_after($columns, '<=', 'and');
    }
    /**
     * Add a "where date" clause to determine if a "date" column is after today.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function where_after_today($columns)
    {
        return $this->where_today_before_or_after($columns, '>', 'and');
    }
    /**
     * Add a "where date" clause to determine if a "date" column is today or after to the query.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function where_today_or_after($columns)
    {
        return $this->where_today_before_or_after($columns, '>=', 'and');
    }
    /**
     * Add an "or where date" clause to determine if a "date" column is today to the query.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function or_where_today($columns)
    {
        return $this->where_today($columns, 'or');
    }
    /**
     * Add an "or where date" clause to determine if a "date" column is before today.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function or_where_before_today($columns)
    {
        return $this->where_today_before_or_after($columns, '<', 'or');
    }
    /**
     * Add an "or where date" clause to determine if a "date" column is today or before to the query.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function or_where_today_or_before($columns)
    {
        return $this->where_today_before_or_after($columns, '<=', 'or');
    }
    /**
     * Add an "or where date" clause to determine if a "date" column is after today.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function or_where_after_today($columns)
    {
        return $this->where_today_before_or_after($columns, '>', 'or');
    }
    /**
     * Add an "or where date" clause to determine if a "date" column is today or after to the query.
     *
     * @param  array|string  $columns
     * @return $this
     */
    public function or_where_today_or_after($columns)
    {
        return $this->where_today_before_or_after($columns, '>=', 'or');
    }
    /**
     * Add a "where date" clause to determine if a "date" column is today or after to the query.
     *
     * @param  array|string  $columns
     * @param  string  $operator
     * @param  string  $boolean
     * @return $this
     */
    protected function where_today_before_or_after($columns, $operator, $boolean)
    {
        $value = Carbon::today()->format('Y-m-d');
        foreach (Arr::wrap($columns) as $column) {
            $this->add_date_based_where('Date', $column, $operator, $value, $boolean);
        }
        return $this;
    }
}