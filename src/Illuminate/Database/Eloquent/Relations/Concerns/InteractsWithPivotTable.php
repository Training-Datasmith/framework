<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations\Concerns;

use Backed_Enum;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection as BaseCollection;
trait Interacts_With_Pivot_Table
{
    /**
     * Toggles a model (or models) from the parent.
     *
     * Each existing model is detached, and non existing ones are attached.
     *
     * @param  mixed  $ids
     * @param  bool  $touch
     */
    public function toggle($ids, $touch = true): array
    {
        $changes = ['attached' => [], 'detached' => []];
        $records = $this->format_records_list($this->parse_ids($ids));
        // Next, we will determine which IDs should get removed from the join table by
        // checking which of the given ID/records is in the list of current records
        // and removing all of those rows from this "intermediate" joining table.
        $detach = array_values(array_intersect($this->new_pivot_query()->pluck($this->related_pivot_key)->all(), array_keys($records)));
        if (count($detach) > 0) {
            $this->detach($detach, false);
            $changes['detached'] = $this->cast_keys($detach);
        }
        // Finally, for all of the records which were not "detached", we'll attach the
        // records into the intermediate table. Then, we will add those attaches to
        // this change list and get ready to return these results to the callers.
        $attach = array_diff_key($records, array_flip($detach));
        if (count($attach) > 0) {
            $this->attach($attach, [], false);
            $changes['attached'] = array_keys($attach);
        }
        // Once we have finished attaching or detaching the records, we will see if we
        // have done any attaching or detaching, and if we have we will touch these
        // relationships if they are configured to touch on any database updates.
        if ($touch && (count($changes['attached']) || count($changes['detached']))) {
            $this->touch_if_touching();
        }
        return $changes;
    }
    /**
     * Sync the intermediate tables with a list of IDs without detaching.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Model|array|int|string  $ids
     * @return array{attached: array, detached: array, updated: array}
     */
    public function sync_without_detaching($ids)
    {
        return $this->sync($ids, false);
    }
    /**
     * Sync the intermediate tables with a list of IDs or collection of models.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Model|array|int|string  $ids
     * @param  bool  $detaching
     * @return array{attached: array, detached: array, updated: array}
     */
    public function sync($ids, $detaching = true): array
    {
        $changes = ['attached' => [], 'detached' => [], 'updated' => []];
        $records = $this->format_records_list($this->parse_ids($ids));
        if (empty($records) && !$detaching) {
            return $changes;
        }
        // First we need to attach any of the associated models that are not currently
        // in this joining table. We'll spin through the given IDs, checking to see
        // if they exist in the array of current ones, and if not we will insert.
        $current = $this->get_currently_attached_pivots()->pluck($this->related_pivot_key)->all();
        // Next, we will take the differences of the currents and given IDs and detach
        // all of the entities that exist in the "current" array but are not in the
        // array of the new IDs given to the method which will complete the sync.
        if ($detaching) {
            $detach = array_diff($current, array_keys($records));
            if (count($detach) > 0) {
                $this->detach($detach, false);
                $changes['detached'] = $this->cast_keys($detach);
            }
        }
        // Now we are finally ready to attach the new records. Note that we'll disable
        // touching until after the entire operation is complete so we don't fire a
        // ton of touch operations until we are totally done syncing the records.
        $changes = array_merge($changes, $this->attach_new($records, $current, false));
        // Once we have finished attaching or detaching the records, we will see if we
        // have done any attaching or detaching, and if we have we will touch these
        // relationships if they are configured to touch on any database updates.
        if (count($changes['attached']) || count($changes['updated']) || count($changes['detached'])) {
            $this->touch_if_touching();
        }
        return $changes;
    }
    /**
     * Sync the intermediate tables with a list of IDs or collection of models with the given pivot values.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Model|array|int|string  $ids
     * @return array{attached: array, detached: array, updated: array}
     */
    public function sync_with_pivot_values($ids, array $values, bool $detaching = true)
    {
        return $this->sync((new Base_Collection($this->parse_ids($ids)))->map_with_keys(fn($id): array => [$id => $values]), $detaching);
    }
    /**
     * Format the sync / toggle record list so that it is keyed by ID.
     *
     * @return array
     */
    protected function format_records_list(array $records)
    {
        return (new Base_Collection($records))->map_with_keys(function ($attributes, $id): array {
            if (!is_array($attributes)) {
                [$id, $attributes] = [$attributes, []];
            }
            if ($id instanceof Backed_Enum) {
                $id = $id->value;
            }
            return [$id => $attributes];
        })->all();
    }
    /**
     * Attach all of the records that aren't in the given current records.
     *
     * @param  bool  $touch
     */
    protected function attach_new(array $records, array $current, $touch = true): array
    {
        $changes = ['attached' => [], 'updated' => []];
        foreach ($records as $id => $attributes) {
            // If the ID is not in the list of existing pivot IDs, we will insert a new pivot
            // record, otherwise, we will just update this existing record on this joining
            // table, so that the developers will easily update these records pain free.
            if (!in_array($id, $current)) {
                $this->attach($id, $attributes, $touch);
                $changes['attached'][] = $this->cast_key($id);
            } elseif (count($attributes) > 0 && $this->update_existing_pivot($id, $attributes, $touch)) {
                $changes['updated'][] = $this->cast_key($id);
            }
        }
        return $changes;
    }
    /**
     * Update an existing pivot record on the table.
     *
     * @param  mixed  $id
     * @param  bool  $touch
     * @return int
     */
    public function update_existing_pivot($id, array $attributes, $touch = true)
    {
        if ($this->using) {
            return $this->update_existing_pivot_using_custom_class($id, $attributes, $touch);
        }
        if ($this->has_pivot_column($this->updated_at())) {
            $attributes = $this->add_timestamps_to_attachment($attributes, true);
        }
        $updated = $this->new_pivot_statement_for_id($id)->update($this->cast_attributes($attributes));
        if ($touch) {
            $this->touch_if_touching();
        }
        return $updated;
    }
    /**
     * Update an existing pivot record on the table via a custom class.
     *
     * @param  mixed  $id
     * @param  bool  $touch
     */
    protected function update_existing_pivot_using_custom_class($id, array $attributes, $touch): int
    {
        $pivot = $this->get_currently_attached_pivots_for_ids($id)->first();
        $updated = $pivot ? $pivot->fill($attributes)->is_dirty() : false;
        if ($updated) {
            $pivot->save();
        }
        if ($touch) {
            $this->touch_if_touching();
        }
        return (int) $updated;
    }
    /**
     * Attach a model to the parent.
     *
     * @param  mixed  $ids
     * @param  bool  $touch
     */
    public function attach($ids, array $attributes = [], $touch = true): void
    {
        if ($this->using) {
            $this->attach_using_custom_class($ids, $attributes);
        } else {
            // Here we will insert the attachment records into the pivot table. Once we have
            // inserted the records, we will touch the relationships if necessary and the
            // function will return. We can parse the IDs before inserting the records.
            $this->new_pivot_statement()->insert($this->format_attach_records($this->parse_ids($ids), $attributes));
        }
        if ($touch) {
            $this->touch_if_touching();
        }
    }
    /**
     * Attach a model to the parent using a custom class.
     *
     * @param  mixed  $ids
     * @return void
     */
    protected function attach_using_custom_class($ids, array $attributes)
    {
        $records = $this->format_attach_records($this->parse_ids($ids), $attributes);
        foreach ($records as $record) {
            $this->new_pivot($record, false)->save();
        }
    }
    /**
     * Create an array of records to insert into the pivot table.
     *
     * @param  array  $ids
     */
    protected function format_attach_records($ids, array $attributes): array
    {
        $records = [];
        $has_timestamps = $this->has_pivot_column($this->created_at()) || $this->has_pivot_column($this->updated_at());
        // To create the attachment records, we will simply spin through the IDs given
        // and create a new record to insert for each ID. Each ID may actually be a
        // key in the array, with extra attributes to be placed in other columns.
        foreach ($ids as $key => $value) {
            $records[] = $this->format_attach_record($key, $value, $attributes, $has_timestamps);
        }
        return $records;
    }
    /**
     * Create a full attachment record payload.
     *
     * @param  int  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @param  bool  $hasTimestamps
     */
    protected function format_attach_record($key, $value, $attributes, $has_timestamps): array
    {
        [$id, $attributes] = $this->extract_attach_id_and_attributes($key, $value, $attributes);
        return array_merge($this->base_attach_record($id, $has_timestamps), $this->cast_attributes($attributes));
    }
    /**
     * Get the attach record ID and extra attributes.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     */
    protected function extract_attach_id_and_attributes($key, $value, array $attributes): array
    {
        return is_array($value) ? [$key, array_merge($value, $attributes)] : [$value, $attributes];
    }
    /**
     * Create a new pivot attachment record.
     *
     * @param  int  $id
     * @param  bool  $timed
     * @return array
     */
    protected function base_attach_record($id, $timed)
    {
        $record[$this->related_pivot_key] = $id;
        $record[$this->foreign_pivot_key] = $this->parent->{$this->parent_key};
        // If the record needs to have creation and update timestamps, we will make
        // them by calling the parent model's "freshTimestamp" method which will
        // provide us with a fresh timestamp in this model's preferred format.
        if ($timed) {
            $record = $this->add_timestamps_to_attachment($record);
        }
        foreach ($this->pivot_values as $value) {
            $record[$value['column']] = $value['value'];
        }
        return $record;
    }
    /**
     * Set the creation and update timestamps on an attach record.
     *
     * @param  bool  $exists
     */
    protected function add_timestamps_to_attachment(array $record, $exists = false): array
    {
        $fresh = $this->parent->fresh_timestamp();
        if ($this->using) {
            $pivot_model = new $this->using();
            $fresh = $pivot_model->from_date_time($fresh);
        }
        if (!$exists && $this->has_pivot_column($this->created_at())) {
            $record[$this->created_at()] = $fresh;
        }
        if ($this->has_pivot_column($this->updated_at())) {
            $record[$this->updated_at()] = $fresh;
        }
        return $record;
    }
    /**
     * Determine whether the given column is defined as a pivot column.
     *
     * @param  string  $column
     */
    public function has_pivot_column($column): bool
    {
        return in_array($column, $this->pivot_columns);
    }
    /**
     * Detach models from the relationship.
     *
     * @param  mixed  $ids
     * @param  bool  $touch
     * @return int
     */
    public function detach($ids = null, $touch = true)
    {
        if ($this->using) {
            $results = $this->detach_using_custom_class($ids);
        } else {
            $query = $this->new_pivot_query();
            // If associated IDs were passed to the method we will only delete those
            // associations, otherwise all of the association ties will be broken.
            // We'll return the numbers of affected rows when we do the deletes.
            if (!is_null($ids)) {
                $ids = $this->parse_ids($ids);
                if (empty($ids)) {
                    return 0;
                }
                $query->where_in($this->get_qualified_related_pivot_key_name(), (array) $ids);
            }
            // Once we have all of the conditions set on the statement, we are ready
            // to run the delete on the pivot table. Then, if the touch parameter
            // is true, we will go ahead and touch all related models to sync.
            $results = $query->delete();
        }
        if ($touch) {
            $this->touch_if_touching();
        }
        return $results;
    }
    /**
     * Detach models from the relationship using a custom class.
     *
     * @param  mixed  $ids
     * @return int
     */
    protected function detach_using_custom_class($ids): int|float
    {
        $results = 0;
        $records = $this->get_currently_attached_pivots_for_ids($ids);
        foreach ($records as $record) {
            $results += $record->delete();
        }
        return $results;
    }
    /**
     * Get the pivot models that are currently attached.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function get_currently_attached_pivots()
    {
        return $this->get_currently_attached_pivots_for_ids();
    }
    /**
     * Get the pivot models that are currently attached, filtered by related model keys.
     *
     * @param  mixed  $ids
     * @return \Illuminate\Support\Collection
     */
    protected function get_currently_attached_pivots_for_ids($ids = null)
    {
        return $this->new_pivot_query()->when(!is_null($ids), fn($query) => $query->where_in($this->get_qualified_related_pivot_key_name(), $this->parse_ids($ids)))->get()->map(function ($record) {
            $class = $this->using ?: Pivot::class;
            $pivot = $class::from_raw_attributes($this->parent, (array) $record, $this->get_table(), true);
            return $pivot->set_pivot_keys($this->foreign_pivot_key, $this->related_pivot_key)->set_related_model($this->related);
        });
    }
    /**
     * Create a new pivot model instance.
     *
     * @param  bool  $exists
     * @return \Illuminate\Database\Eloquent\Relations\Pivot
     */
    public function new_pivot(array $attributes = [], $exists = false)
    {
        $attributes = array_merge(array_column($this->pivot_values, 'value', 'column'), $attributes);
        $pivot = $this->related->new_pivot($this->parent, $attributes, $this->table, $exists, $this->using);
        return $pivot->set_pivot_keys($this->foreign_pivot_key, $this->related_pivot_key)->set_related_model($this->related);
    }
    /**
     * Create a new existing pivot model instance.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Pivot
     */
    public function new_existing_pivot(array $attributes = [])
    {
        return $this->new_pivot($attributes, true);
    }
    /**
     * Get a new plain query builder for the pivot table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function new_pivot_statement()
    {
        return $this->query->get_query()->new_query()->from($this->table);
    }
    /**
     * Get a new pivot statement for a given "other" ID.
     *
     * @param  mixed  $id
     * @return \Illuminate\Database\Query\Builder
     */
    public function new_pivot_statement_for_id($id)
    {
        return $this->new_pivot_query()->where_in($this->get_qualified_related_pivot_key_name(), $this->parse_ids($id));
    }
    /**
     * Create a new query builder for the pivot table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function new_pivot_query()
    {
        $query = $this->new_pivot_statement();
        foreach ($this->pivot_wheres as $arguments) {
            $query->where(...$arguments);
        }
        foreach ($this->pivot_where_ins as $arguments) {
            $query->where_in(...$arguments);
        }
        foreach ($this->pivot_where_nulls as $arguments) {
            $query->where_null(...$arguments);
        }
        return $query->where($this->get_qualified_foreign_pivot_key_name(), $this->parent->{$this->parent_key});
    }
    /**
     * Set the columns on the pivot table to retrieve.
     *
     * @param  mixed  $columns
     * @return $this
     */
    public function with_pivot($columns)
    {
        $this->pivot_columns = array_merge($this->pivot_columns, is_array($columns) ? $columns : func_get_args());
        return $this;
    }
    /**
     * Get all of the IDs from the given mixed value.
     *
     * @param  mixed  $value
     * @return array
     */
    protected function parse_ids($value)
    {
        if ($value instanceof Model) {
            return [$value->{$this->related_key}];
        }
        if ($value instanceof Eloquent_Collection) {
            return $value->pluck($this->related_key)->all();
        }
        if ($value instanceof Base_Collection || is_array($value)) {
            return (new Base_Collection($value))->map(fn($item) => $item instanceof Model ? $item->{$this->related_key} : $item)->all();
        }
        return (array) $value;
    }
    /**
     * Get the ID from the given mixed value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function parse_id($value)
    {
        return $value instanceof Model ? $value->{$this->related_key} : $value;
    }
    /**
     * Cast the given keys to integers if they are numeric and string otherwise.
     */
    protected function cast_keys(array $keys): array
    {
        return array_map(fn($v) => $this->cast_key($v), $keys);
    }
    /**
     * Cast the given key to convert to primary key type.
     *
     * @param  mixed  $key
     * @return mixed
     */
    protected function cast_key($key)
    {
        return $this->get_type_swap_value($this->related->get_key_type(), $key);
    }
    /**
     * Cast the given pivot attributes.
     *
     * @param  array  $attributes
     * @return array
     */
    protected function cast_attributes($attributes)
    {
        return $this->using ? $this->new_pivot()->fill($attributes)->get_attributes() : $attributes;
    }
    /**
     * Converts a given value to a given type value.
     *
     * @param  string  $type
     * @param  mixed  $value
     * @return mixed
     */
    protected function get_type_swap_value($type, $value)
    {
        return match (strtolower($type)) {
            'int', 'integer' => (int) $value,
            'real', 'float', 'double' => (float) $value,
            'string' => (string) $value,
            default => $value,
        };
    }
}