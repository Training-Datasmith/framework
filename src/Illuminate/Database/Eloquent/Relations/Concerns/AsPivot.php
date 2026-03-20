<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
trait As_Pivot
{
    /**
     * The parent model of the relationship.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $pivot_parent;
    /**
     * The related model of the relationship.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $pivot_related;
    /**
     * The name of the foreign key column.
     *
     * @var string
     */
    protected $foreign_key;
    /**
     * The name of the "other key" column.
     *
     * @var string
     */
    protected $related_key;
    /**
     * Create a new pivot model instance.
     *
     * @param  array  $attributes
     * @param  string  $table
     * @param  bool  $exists
     */
    public static function from_attributes(Model $parent, $attributes, $table, $exists = false): static
    {
        $instance = new static();
        $instance->timestamps = $instance->has_timestamp_attributes($attributes);
        // The pivot model is a "dynamic" model since we will set the tables dynamically
        // for the instance. This allows it work for any intermediate tables for the
        // many to many relationship that are defined by this developer's classes.
        $instance->set_connection($parent->get_connection_name())->set_table($table)->force_fill($attributes)->sync_original();
        // We store off the parent instance so we will access the timestamp column names
        // for the model, since the pivot model timestamps aren't easily configurable
        // from the developer's point of view. We can use the parents to get these.
        $instance->pivot_parent = $parent;
        $instance->exists = $exists;
        return $instance;
    }
    /**
     * Create a new pivot model from raw values returned from a query.
     *
     * @param  array  $attributes
     * @param  string  $table
     * @param  bool  $exists
     */
    public static function from_raw_attributes(Model $parent, $attributes, $table, $exists = false): static
    {
        $instance = static::from_attributes($parent, [], $table, $exists);
        $instance->timestamps = $instance->has_timestamp_attributes($attributes);
        $instance->set_raw_attributes(array_merge($instance->get_raw_original(), $attributes), $exists);
        return $instance;
    }
    /**
     * Set the keys for a select query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    protected function set_keys_for_select_query($query)
    {
        if (isset($this->attributes[$this->get_key_name()])) {
            return parent::set_keys_for_select_query($query);
        }
        $query->where($this->foreign_key, $this->get_original($this->foreign_key, $this->get_attribute($this->foreign_key)));
        return $query->where($this->related_key, $this->get_original($this->related_key, $this->get_attribute($this->related_key)));
    }
    /**
     * Set the keys for a save update query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    protected function set_keys_for_save_query($query)
    {
        return $this->set_keys_for_select_query($query);
    }
    /**
     * Delete the pivot model record from the database.
     *
     * @return int
     */
    public function delete()
    {
        if (isset($this->attributes[$this->get_key_name()])) {
            return (int) parent::delete();
        }
        if ($this->fire_model_event('deleting') === false) {
            return 0;
        }
        $this->touch_owners();
        return tap($this->get_delete_query()->delete(), function (): void {
            $this->exists = false;
            $this->fire_model_event('deleted', false);
        });
    }
    /**
     * Get the query builder for a delete operation on the pivot.
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    protected function get_delete_query()
    {
        return $this->new_query_without_relationships()->where([$this->foreign_key => $this->get_original($this->foreign_key, $this->get_attribute($this->foreign_key)), $this->related_key => $this->get_original($this->related_key, $this->get_attribute($this->related_key))]);
    }
    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function get_table()
    {
        if (!isset($this->table)) {
            $this->set_table(str_replace('\\', '', Str::snake(Str::singular(class_basename($this)))));
        }
        return $this->table;
    }
    /**
     * Get the foreign key column name.
     *
     * @return string
     */
    public function get_foreign_key()
    {
        return $this->foreign_key;
    }
    /**
     * Get the "related key" column name.
     *
     * @return string
     */
    public function get_related_key()
    {
        return $this->related_key;
    }
    /**
     * Get the "related key" column name.
     *
     * @return string
     */
    public function get_other_key()
    {
        return $this->get_related_key();
    }
    /**
     * Set the key names for the pivot model instance.
     *
     * @param  string  $foreignKey
     * @param  string  $relatedKey
     * @return $this
     */
    public function set_pivot_keys($foreign_key, $related_key)
    {
        $this->foreign_key = $foreign_key;
        $this->related_key = $related_key;
        return $this;
    }
    /**
     * Set the related model of the relationship.
     *
     * @return $this
     */
    public function set_related_model(?Model $related = null)
    {
        $this->pivot_related = $related;
        return $this;
    }
    /**
     * Determine if the pivot model or given attributes has timestamp attributes.
     *
     * @param  array|null  $attributes
     */
    public function has_timestamp_attributes($attributes = null): bool
    {
        return ($created_at = $this->get_created_at_column()) !== null && array_key_exists($created_at, $attributes ?? $this->attributes);
    }
    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function get_created_at_column()
    {
        return $this->pivot_parent ? $this->pivot_parent->get_created_at_column() : parent::get_created_at_column();
    }
    /**
     * Get the name of the "updated at" column.
     *
     * @return string
     */
    public function get_updated_at_column()
    {
        return $this->pivot_parent ? $this->pivot_parent->get_updated_at_column() : parent::get_updated_at_column();
    }
    /**
     * Get the queueable identity for the entity.
     *
     * @return mixed
     */
    public function get_queueable_id()
    {
        if (isset($this->attributes[$this->get_key_name()])) {
            return $this->get_key();
        }
        return sprintf('%s:%s:%s:%s', $this->foreign_key, $this->get_attribute($this->foreign_key), $this->related_key, $this->get_attribute($this->related_key));
    }
    /**
     * Get a new query to restore one or more models by their queueable IDs.
     *
     * @param  int[]|string[]|string  $ids
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function new_query_for_restoration($ids)
    {
        if (is_array($ids)) {
            return $this->new_query_for_collection_restoration($ids);
        }
        if (!str_contains($ids, ':')) {
            return parent::new_query_for_restoration($ids);
        }
        $segments = explode(':', $ids);
        return $this->new_query_without_scopes()->where($segments[0], $segments[1])->where($segments[2], $segments[3]);
    }
    /**
     * Get a new query to restore multiple models by their queueable IDs.
     *
     * @param  int[]|string[]  $ids
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    protected function new_query_for_collection_restoration(array $ids)
    {
        $ids = array_values($ids);
        if (!str_contains((string) $ids[0], ':')) {
            return parent::new_query_for_restoration($ids);
        }
        $query = $this->new_query_without_scopes();
        foreach ($ids as $id) {
            $segments = explode(':', (string) $id);
            $query->or_where(fn($query) => $query->where($segments[0], $segments[1])->where($segments[2], $segments[3]));
        }
        return $query;
    }
    /**
     * Unset all the loaded relations for the instance.
     *
     * @return $this
     */
    public function unset_relations()
    {
        $this->pivot_parent = null;
        $this->pivot_related = null;
        $this->relations = [];
        return $this;
    }
}