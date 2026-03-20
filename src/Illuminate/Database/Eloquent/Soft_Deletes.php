<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as BaseCollection;
/**
 * @method static \Illuminate\Database\Eloquent\Builder<static> withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static> onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTrashed()
 * @method static static restoreOrCreate(array<string, mixed> $attributes = [], array<string, mixed> $values = [])
 * @method static static createOrRestore(array<string, mixed> $attributes = [], array<string, mixed> $values = [])
 */
trait Soft_Deletes
{
    /**
     * Indicates if the model is currently force deleting.
     *
     * @var bool
     */
    protected $force_deleting = false;
    /**
     * Boot the soft deleting trait for a model.
     */
    public static function boot_soft_deletes(): void
    {
        static::add_global_scope(new Soft_Deleting_Scope());
    }
    /**
     * Initialize the soft deleting trait for an instance.
     */
    public function initialize_soft_deletes(): void
    {
        if (!isset($this->casts[$this->get_deleted_at_column()])) {
            $this->casts[$this->get_deleted_at_column()] = 'datetime';
        }
    }
    /**
     * Force a hard delete on a soft deleted model.
     *
     * @return bool|null
     */
    public function force_delete()
    {
        if ($this->fire_model_event('forceDeleting') === false) {
            return false;
        }
        $this->force_deleting = true;
        return tap($this->delete(), function ($deleted): void {
            $this->force_deleting = false;
            if ($deleted) {
                $this->fire_model_event('forceDeleted', false);
            }
        });
    }
    /**
     * Force a hard delete on a soft deleted model without raising any events.
     *
     * @return bool|null
     */
    public function force_delete_quietly()
    {
        return static::without_events(fn() => $this->force_delete());
    }
    /**
     * Destroy the models for the given IDs.
     *
     * @param  \Illuminate\Support\Collection|array|int|string  $ids
     */
    public static function force_destroy($ids): int
    {
        if ($ids instanceof Eloquent_Collection) {
            $ids = $ids->model_keys();
        }
        if ($ids instanceof Base_Collection) {
            $ids = $ids->all();
        }
        $ids = is_array($ids) ? $ids : func_get_args();
        if (count($ids) === 0) {
            return 0;
        }
        // We will actually pull the models from the database table and call delete on
        // each of them individually so that their events get fired properly with a
        // correct set of attributes in case the developers wants to check these.
        $key = ($instance = new static())->get_key_name();
        $count = 0;
        foreach ($instance->with_trashed()->where_in($key, $ids)->get() as $model) {
            if ($model->force_delete()) {
                $count++;
            }
        }
        return $count;
    }
    /**
     * Perform the actual delete query on this model instance.
     *
     * @return mixed
     */
    protected function perform_delete_on_model()
    {
        if ($this->force_deleting) {
            return tap($this->set_keys_for_save_query($this->new_model_query())->force_delete(), function (): void {
                $this->exists = false;
            });
        }
        return $this->run_soft_delete();
    }
    /**
     * Perform the actual delete query on this model instance.
     *
     * @return void
     */
    protected function run_soft_delete()
    {
        $query = $this->set_keys_for_save_query($this->new_model_query());
        $time = $this->fresh_timestamp();
        $columns = [$this->get_deleted_at_column() => $this->from_date_time($time)];
        $this->{$this->get_deleted_at_column()} = $time;
        if ($this->uses_timestamps() && !is_null($this->get_updated_at_column())) {
            $this->{$this->get_updated_at_column()} = $time;
            $columns[$this->get_updated_at_column()] = $this->from_date_time($time);
        }
        $query->update($columns);
        $this->sync_original_attributes(array_keys($columns));
        $this->fire_model_event('trashed', false);
    }
    /**
     * Restore a soft-deleted model instance.
     *
     * @return bool
     */
    public function restore()
    {
        // If the restoring event does not return false, we will proceed with this
        // restore operation. Otherwise, we bail out so the developer will stop
        // the restore totally. We will clear the deleted timestamp and save.
        if ($this->fire_model_event('restoring') === false) {
            return false;
        }
        $this->{$this->get_deleted_at_column()} = null;
        // Once we have saved the model, we will fire the "restored" event so this
        // developer will do anything they need to after a restore operation is
        // totally finished. Then we will return the result of the save call.
        $this->exists = true;
        $result = $this->save();
        $this->fire_model_event('restored', false);
        return $result;
    }
    /**
     * Restore a soft-deleted model instance without raising any events.
     *
     * @return bool
     */
    public function restore_quietly()
    {
        return static::without_events(fn() => $this->restore());
    }
    /**
     * Determine if the model instance has been soft-deleted.
     */
    public function trashed(): bool
    {
        return !is_null($this->{$this->get_deleted_at_column()});
    }
    /**
     * Register a "softDeleted" model event callback with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|class-string  $callback
     */
    public static function soft_deleted($callback): void
    {
        static::register_model_event('trashed', $callback);
    }
    /**
     * Register a "restoring" model event callback with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|class-string  $callback
     */
    public static function restoring($callback): void
    {
        static::register_model_event('restoring', $callback);
    }
    /**
     * Register a "restored" model event callback with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|class-string  $callback
     */
    public static function restored($callback): void
    {
        static::register_model_event('restored', $callback);
    }
    /**
     * Register a "forceDeleting" model event callback with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|class-string  $callback
     */
    public static function force_deleting($callback): void
    {
        static::register_model_event('forceDeleting', $callback);
    }
    /**
     * Register a "forceDeleted" model event callback with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|class-string  $callback
     */
    public static function force_deleted($callback): void
    {
        static::register_model_event('forceDeleted', $callback);
    }
    /**
     * Determine if the model is currently force deleting.
     *
     * @return bool
     */
    public function is_force_deleting()
    {
        return $this->force_deleting;
    }
    /**
     * Get the name of the "deleted at" column.
     *
     * @return string
     */
    public function get_deleted_at_column()
    {
        return defined(static::class . '::DELETED_AT') ? static::DELETED_AT : 'deleted_at';
    }
    /**
     * Get the fully-qualified "deleted at" column.
     *
     * @return string
     */
    public function get_qualified_deleted_at_column()
    {
        return $this->qualify_column($this->get_deleted_at_column());
    }
}