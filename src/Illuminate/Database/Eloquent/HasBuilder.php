<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

/**
 * @template TBuilder of \Illuminate\Database\Eloquent\Builder
 */
trait Has_Builder
{
    /**
     * Begin querying the model.
     *
     * @return TBuilder
     */
    public static function query()
    {
        return parent::query();
    }
    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return TBuilder
     */
    public function new_eloquent_builder($query)
    {
        return parent::new_eloquent_builder($query);
    }
    /**
     * Get a new query builder for the model's table.
     *
     * @return TBuilder
     */
    public function new_query()
    {
        return parent::new_query();
    }
    /**
     * Get a new query builder that doesn't have any global scopes or eager loading.
     *
     * @return TBuilder
     */
    public function new_model_query()
    {
        return parent::new_model_query();
    }
    /**
     * Get a new query builder with no relationships loaded.
     *
     * @return TBuilder
     */
    public function new_query_without_relationships()
    {
        return parent::new_query_without_relationships();
    }
    /**
     * Get a new query builder that doesn't have any global scopes.
     *
     * @return TBuilder
     */
    public function new_query_without_scopes()
    {
        return parent::new_query_without_scopes();
    }
    /**
     * Get a new query instance without a given scope.
     *
     * @param  \Illuminate\Database\Eloquent\Scope|string  $scope
     * @return TBuilder
     */
    public function new_query_without_scope($scope)
    {
        return parent::new_query_without_scope($scope);
    }
    /**
     * Get a new query to restore one or more models by their queueable IDs.
     *
     * @param  array|int  $ids
     * @return TBuilder
     */
    public function new_query_for_restoration($ids)
    {
        return parent::new_query_for_restoration($ids);
    }
    /**
     * Begin querying the model on a given connection.
     *
     * @param  string|null  $connection
     * @return TBuilder
     */
    public static function on($connection = null)
    {
        return parent::on($connection);
    }
    /**
     * Begin querying the model on the write connection.
     *
     * @return TBuilder
     */
    public static function on_write_connection()
    {
        return parent::on_write_connection();
    }
    /**
     * Begin querying a model with eager loading.
     *
     * @param  array|string  $relations
     * @return TBuilder
     */
    public static function with($relations)
    {
        return parent::with($relations);
    }
}