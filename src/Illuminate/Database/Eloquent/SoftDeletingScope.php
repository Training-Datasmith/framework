<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

class Soft_Deleting_Scope implements Scope
{
    /**
     * All of the extensions to be added to the builder.
     *
     * @var string[]
     */
    protected $extensions = ['Restore', 'RestoreOrCreate', 'CreateOrRestore', 'WithTrashed', 'WithoutTrashed', 'OnlyTrashed'];
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where_null($model->get_qualified_deleted_at_column());
    }
    /**
     * Extend the query builder with the needed functions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     */
    public function extend(Builder $builder): void
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }
        $builder->on_delete(function (Builder $builder) {
            $column = $this->get_deleted_at_column($builder);
            return $builder->update([$column => $builder->get_model()->fresh_timestamp_string()]);
        });
    }
    /**
     * Get the "deleted at" column for the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     * @return string
     */
    protected function get_deleted_at_column(Builder $builder)
    {
        if (count((array) $builder->get_query()->joins) > 0) {
            return $builder->get_model()->get_qualified_deleted_at_column();
        }
        return $builder->get_model()->get_deleted_at_column();
    }
    /**
     * Add the restore extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     * @return void
     */
    protected function add_restore(Builder $builder)
    {
        $builder->macro('restore', function (Builder $builder) {
            $builder->with_trashed();
            return $builder->update([$builder->get_model()->get_deleted_at_column() => null]);
        });
    }
    /**
     * Add the restore-or-create extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     * @return void
     */
    protected function add_restore_or_create(Builder $builder)
    {
        $builder->macro('restoreOrCreate', function (Builder $builder, array $attributes = [], array $values = []) {
            $builder->with_trashed();
            return tap($builder->first_or_create($attributes, $values), function ($instance): void {
                $instance->restore();
            });
        });
    }
    /**
     * Add the create-or-restore extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     * @return void
     */
    protected function add_create_or_restore(Builder $builder)
    {
        $builder->macro('createOrRestore', function (Builder $builder, array $attributes = [], array $values = []) {
            $builder->with_trashed();
            return tap($builder->create_or_first($attributes, $values), function ($instance): void {
                $instance->restore();
            });
        });
    }
    /**
     * Add the with-trashed extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     * @return void
     */
    protected function add_with_trashed(Builder $builder)
    {
        $builder->macro('withTrashed', function (Builder $builder, $with_trashed = true) {
            if (!$with_trashed) {
                return $builder->without_trashed();
            }
            return $builder->without_global_scope($this);
        });
    }
    /**
     * Add the without-trashed extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     * @return void
     */
    protected function add_without_trashed(Builder $builder)
    {
        $builder->macro('withoutTrashed', function (Builder $builder): \Illuminate\Database\Eloquent\Builder {
            $model = $builder->get_model();
            $builder->without_global_scope($this)->where_null($model->get_qualified_deleted_at_column());
            return $builder;
        });
    }
    /**
     * Add the only-trashed extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     * @return void
     */
    protected function add_only_trashed(Builder $builder)
    {
        $builder->macro('onlyTrashed', function (Builder $builder): \Illuminate\Database\Eloquent\Builder {
            $model = $builder->get_model();
            $builder->without_global_scope($this)->where_not_null($model->get_qualified_deleted_at_column());
            return $builder;
        });
    }
}