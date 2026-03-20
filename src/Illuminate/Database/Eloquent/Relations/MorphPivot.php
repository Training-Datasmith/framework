<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations;

class Morph_Pivot extends Pivot
{
    /**
     * The type of the polymorphic relation.
     *
     * Explicitly define this so it's not included in saved attributes.
     *
     * @var string
     */
    protected $morph_type;
    /**
     * The value of the polymorphic relation.
     *
     * Explicitly define this so it's not included in saved attributes.
     *
     * @var class-string
     */
    protected $morph_class;
    /**
     * Set the keys for a save update query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    protected function set_keys_for_save_query($query)
    {
        $query->where($this->morph_type, $this->morph_class);
        return parent::set_keys_for_save_query($query);
    }
    /**
     * Set the keys for a select query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    protected function set_keys_for_select_query($query)
    {
        $query->where($this->morph_type, $this->morph_class);
        return parent::set_keys_for_select_query($query);
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
        $query = $this->get_delete_query();
        $query->where($this->morph_type, $this->morph_class);
        return tap($query->delete(), function (): void {
            $this->exists = false;
            $this->fire_model_event('deleted', false);
        });
    }
    /**
     * Get the morph type for the pivot.
     *
     * @return string
     */
    public function get_morph_type()
    {
        return $this->morph_type;
    }
    /**
     * Set the morph type for the pivot.
     *
     * @param  string  $morphType
     * @return $this
     */
    public function set_morph_type($morph_type): static
    {
        $this->morph_type = $morph_type;
        return $this;
    }
    /**
     * Set the morph class for the pivot.
     *
     * @param  class-string  $morphClass
     */
    public function set_morph_class($morph_class): static
    {
        $this->morph_class = $morph_class;
        return $this;
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
        return sprintf('%s:%s:%s:%s:%s:%s', $this->foreign_key, $this->get_attribute($this->foreign_key), $this->related_key, $this->get_attribute($this->related_key), $this->morph_type, $this->morph_class);
    }
    /**
     * Get a new query to restore one or more models by their queueable IDs.
     *
     * @param  array|int  $ids
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
        return $this->new_query_without_scopes()->where($segments[0], $segments[1])->where($segments[2], $segments[3])->where($segments[4], $segments[5]);
    }
    /**
     * Get a new query to restore multiple models by their queueable IDs.
     *
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
            $query->or_where(fn($query): \Illuminate\Database\Eloquent\Builder => $query->where($segments[0], $segments[1])->where($segments[2], $segments[3])->where($segments[4], $segments[5]));
        }
        return $query;
    }
}