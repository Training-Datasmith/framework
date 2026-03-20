<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Concerns;

use BadMethodCallException;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relation_Not_Found_Exception;
use Illuminate\Database\Eloquent\Relations\Belongs_To;
use Illuminate\Database\Eloquent\Relations\Belongs_To_Many;
use Illuminate\Database\Eloquent\Relations\Morph_To;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection as BaseCollection;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Str;
use InvalidArgumentException;
/** @mixin \Illuminate\Database\Eloquent\Builder */
trait Queries_Relationships
{
    /**
     * Add a relationship count / exists condition to the query.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<TRelatedModel, *, *>|string  $relation
     * @param  string  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|int  $count
     * @param  string  $boolean
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>): mixed)|null  $callback
     * @return $this
     *
     * @throws \RuntimeException
     */
    public function has($relation, $operator = '>=', $count = 1, $boolean = 'and', ?Closure $callback = null)
    {
        if (is_string($relation)) {
            if (str_contains($relation, '.')) {
                return $this->has_nested($relation, $operator, $count, $boolean, $callback);
            }
            $relation = $this->get_relation_without_constraints($relation);
        }
        if ($relation instanceof Morph_To) {
            return $this->has_morph($relation, ['*'], $operator, $count, $boolean, $callback);
        }
        // If we only need to check for the existence of the relation, then we can optimize
        // the subquery to only run a "where exists" clause instead of this full "count"
        // clause. This will make these queries run much faster compared with a count.
        $method = $this->can_use_exists_for_existence_check($operator, $count) ? 'getRelationExistenceQuery' : 'getRelationExistenceCountQuery';
        $has_query = $relation->{$method}($relation->get_related()->new_query_without_relationships(), $this);
        // Next we will call any given callback as an "anonymous" scope so they can get the
        // proper logical grouping of the where clauses if needed by this Eloquent query
        // builder. Then, we will be ready to finalize and return this query instance.
        if ($callback) {
            $has_query->call_scope($callback);
        }
        return $this->add_has_where($has_query, $relation, $operator, $count, $boolean);
    }
    /**
     * Add nested relationship count / exists conditions to the query.
     *
     * Sets up recursive call to whereHas until we finish the nested relation.
     *
     * @param  string  $relations
     * @param  string  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|int  $count
     * @param  string  $boolean
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<*>): mixed)|null  $callback
     * @return $this
     */
    protected function has_nested($relations, $operator = '>=', $count = 1, $boolean = 'and', $callback = null)
    {
        $relations = explode('.', $relations);
        $initial_relations = [...$relations];
        $doesnt_have = $operator === '<' && $count === 1;
        if ($doesnt_have) {
            $operator = '>=';
            $count = 1;
        }
        $closure = function ($q) use (&$closure, &$relations, $operator, $count, $callback, $initial_relations): void {
            // If the same closure is called multiple times, reset the relation array to loop through them again...
            if ($count === 1 && empty($relations)) {
                $relations = [...$initial_relations];
                array_shift($relations);
            }
            // In order to nest "has", we need to add count relation constraints on the
            // callback Closure. We'll do this by simply passing the Closure its own
            // reference to itself so it calls itself recursively on each segment.
            count($relations) > 1 ? $q->where_has(array_shift($relations), $closure) : $q->has(array_shift($relations), $operator, $count, 'and', $callback);
        };
        return $this->has(array_shift($relations), $doesnt_have ? '<' : '>=', 1, $boolean, $closure);
    }
    /**
     * Add a relationship count / exists condition to the query with an "or".
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<*, *, *>|string  $relation
     * @param  string  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|int  $count
     * @return $this
     */
    public function or_has($relation, $operator = '>=', $count = 1)
    {
        return $this->has($relation, $operator, $count, 'or');
    }
    /**
     * Add a relationship count / exists condition to the query.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<TRelatedModel, *, *>|string  $relation
     * @param  string  $boolean
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>): mixed)|null  $callback
     * @return $this
     */
    public function doesnt_have($relation, $boolean = 'and', ?Closure $callback = null)
    {
        return $this->has($relation, '<', 1, $boolean, $callback);
    }
    /**
     * Add a relationship count / exists condition to the query with an "or".
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<*, *, *>|string  $relation
     * @return $this
     */
    public function or_doesnt_have($relation)
    {
        return $this->doesnt_have($relation, 'or');
    }
    /**
     * Add a relationship count / exists condition to the query with where clauses.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<TRelatedModel, *, *>|string  $relation
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>): mixed)|null  $callback
     * @param  string  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|int  $count
     * @return $this
     */
    public function where_has($relation, ?Closure $callback = null, $operator = '>=', $count = 1)
    {
        return $this->has($relation, $operator, $count, 'and', $callback);
    }
    /**
     * Add a relationship count / exists condition to the query with where clauses.
     *
     * Also load the relationship with the same condition.
     *
     * @param  string  $relation
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>): mixed)|null  $callback
     * @param  string  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|int  $count
     * @return $this
     */
    public function with_where_has($relation, ?Closure $callback = null, $operator = '>=', $count = 1)
    {
        return $this->where_has(Str::before($relation, ':'), $callback, $operator, $count)->with($callback ? [$relation => fn($query) => $callback($query)] : $relation);
    }
    /**
     * Add a relationship count / exists condition to the query with where clauses and an "or".
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<TRelatedModel, *, *>|string  $relation
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>): mixed)|null  $callback
     * @param  string  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|int  $count
     * @return $this
     */
    public function or_where_has($relation, ?Closure $callback = null, $operator = '>=', $count = 1)
    {
        return $this->has($relation, $operator, $count, 'or', $callback);
    }
    /**
     * Add a relationship count / exists condition to the query with where clauses.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<TRelatedModel, *, *>|string  $relation
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>): mixed)|null  $callback
     * @return $this
     */
    public function where_doesnt_have($relation, ?Closure $callback = null)
    {
        return $this->doesnt_have($relation, 'and', $callback);
    }
    /**
     * Add a relationship count / exists condition to the query with where clauses and an "or".
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<TRelatedModel, *, *>|string  $relation
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>): mixed)|null  $callback
     * @return $this
     */
    public function or_where_doesnt_have($relation, ?Closure $callback = null)
    {
        return $this->doesnt_have($relation, 'or', $callback);
    }
    /**
     * Add a polymorphic relationship count / exists condition to the query.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\MorphTo<TRelatedModel, *>|string  $relation
     * @param  string|array<int, string>  $types
     * @param  string  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|int  $count
     * @param  string  $boolean
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>, string): mixed)|null  $callback
     * @return $this
     */
    public function has_morph($relation, $types, $operator = '>=', $count = 1, $boolean = 'and', ?Closure $callback = null)
    {
        if (is_string($relation)) {
            $relation = $this->get_relation_without_constraints($relation);
        }
        $types = (array) $types;
        $check_morph_null = $types === ['*'] && ($operator === '<' && $count >= 1 || $operator === '<=' && $count >= 0 || $operator === '=' && $count === 0 || $operator === '!=' && $count >= 1);
        if ($types === ['*']) {
            $types = $this->model->new_model_query()->distinct()->pluck($relation->get_morph_type())->filter()->map(fn($item) => enum_value($item))->all();
        }
        if (empty($types)) {
            return $this->where(new Expression('0'), $operator, $count, $boolean);
        }
        foreach ($types as &$type) {
            $type = Relation::get_morphed_model($type) ?? $type;
        }
        return $this->where(function ($query) use ($relation, $callback, $operator, $count, $types, $check_morph_null): void {
            foreach ($types as $type) {
                $query->or_where(function ($query) use ($relation, $callback, $operator, $count, $type): void {
                    $belongs_to = $this->get_belongs_to_relation($relation, $type);
                    if ($callback) {
                        $callback = fn($query) => $callback($query, $type);
                    }
                    $query->where($this->qualify_column($relation->get_morph_type()), '=', (new $type())->get_morph_class())->where_has($belongs_to, $callback, $operator, $count);
                });
            }
            $query->when($check_morph_null, fn(self $query) => $query->or_where_morphed_to($relation, null));
        }, null, null, $boolean);
    }
    /**
     * Get the BelongsTo relationship for a single polymorphic type.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\MorphTo<*, TDeclaringModel>  $relation
     * @param  class-string<TRelatedModel>  $type
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<TRelatedModel, TDeclaringModel>
     */
    protected function get_belongs_to_relation(Morph_To $relation, $type)
    {
        $belongs_to = Relation::no_constraints(fn() => $this->model->belongs_to($type, $relation->get_foreign_key_name(), $relation->get_owner_key_name()));
        $belongs_to->get_query()->merge_constraints_from($relation->get_query());
        return $belongs_to;
    }
    /**
     * Add a polymorphic relationship count / exists condition to the query with an "or".
     *
     * @param  \Illuminate\Database\Eloquent\Relations\MorphTo<*, *>|string  $relation
     * @param  string|array<int, string>  $types
     * @param  string  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|int  $count
     * @return $this
     */
    public function or_has_morph($relation, $types, $operator = '>=', $count = 1)
    {
        return $this->has_morph($relation, $types, $operator, $count, 'or');
    }
    /**
     * Add a polymorphic relationship count / exists condition to the query.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\MorphTo<TRelatedModel, *>|string  $relation
     * @param  string|array<int, string>  $types
     * @param  string  $boolean
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>, string): mixed)|null  $callback
     * @return $this
     */
    public function doesnt_have_morph($relation, $types, $boolean = 'and', ?Closure $callback = null)
    {
        return $this->has_morph($relation, $types, '<', 1, $boolean, $callback);
    }
    /**
     * Add a polymorphic relationship count / exists condition to the query with an "or".
     *
     * @param  \Illuminate\Database\Eloquent\Relations\MorphTo<*, *>|string  $relation
     * @param  string|array<int, string>  $types
     * @return $this
     */
    public function or_doesnt_have_morph($relation, $types)
    {
        return $this->doesnt_have_morph($relation, $types, 'or');
    }
    /**
     * Add a polymorphic relationship count / exists condition to the query with where clauses.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\MorphTo<TRelatedModel, *>|string  $relation
     * @param  string|array<int, string>  $types
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>, string): mixed)|null  $callback
     * @param  string  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|int  $count
     * @return $this
     */
    public function where_has_morph($relation, $types, ?Closure $callback = null, $operator = '>=', $count = 1)
    {
        return $this->has_morph($relation, $types, $operator, $count, 'and', $callback);
    }
    /**
     * Add a polymorphic relationship count / exists condition to the query with where clauses and an "or".
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\MorphTo<TRelatedModel, *>|string  $relation
     * @param  string|array<int, string>  $types
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>, string): mixed)|null  $callback
     * @param  string  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|int  $count
     * @return $this
     */
    public function or_where_has_morph($relation, $types, ?Closure $callback = null, $operator = '>=', $count = 1)
    {
        return $this->has_morph($relation, $types, $operator, $count, 'or', $callback);
    }
    /**
     * Add a polymorphic relationship count / exists condition to the query with where clauses.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\MorphTo<TRelatedModel, *>|string  $relation
     * @param  string|array<int, string>  $types
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>, string): mixed)|null  $callback
     * @return $this
     */
    public function where_doesnt_have_morph($relation, $types, ?Closure $callback = null)
    {
        return $this->doesnt_have_morph($relation, $types, 'and', $callback);
    }
    /**
     * Add a polymorphic relationship count / exists condition to the query with where clauses and an "or".
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\MorphTo<TRelatedModel, *>|string  $relation
     * @param  string|array<int, string>  $types
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>, string): mixed)|null  $callback
     * @return $this
     */
    public function or_where_doesnt_have_morph($relation, $types, ?Closure $callback = null)
    {
        return $this->doesnt_have_morph($relation, $types, 'or', $callback);
    }
    /**
     * Add a basic where clause to a relationship query.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<TRelatedModel, *, *>|string  $relation
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>): mixed)|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function where_relation($relation, $column, $operator = null, $value = null)
    {
        return $this->where_has($relation, function ($query) use ($column, $operator, $value): void {
            if ($column instanceof Closure) {
                $column($query);
            } else {
                $query->where($column, $operator, $value);
            }
        });
    }
    /**
     * Add a basic where clause to a relationship query and eager-load the relationship with the same conditions.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<*, *, *>|string  $relation
     * @param  \Closure|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function with_where_relation($relation, $column, $operator = null, $value = null)
    {
        return $this->where_relation($relation, $column, $operator, $value)->with([$relation => fn($query) => $column instanceof Closure ? $column($query) : $query->where($column, $operator, $value)]);
    }
    /**
     * Add an "or where" clause to a relationship query.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<TRelatedModel, *, *>|string  $relation
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>): mixed)|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function or_where_relation($relation, $column, $operator = null, $value = null)
    {
        return $this->or_where_has($relation, function ($query) use ($column, $operator, $value): void {
            if ($column instanceof Closure) {
                $column($query);
            } else {
                $query->where($column, $operator, $value);
            }
        });
    }
    /**
     * Add a basic count / exists condition to a relationship query.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<TRelatedModel, *, *>|string  $relation
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>): mixed)|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function where_doesnt_have_relation($relation, $column, $operator = null, $value = null)
    {
        return $this->where_doesnt_have($relation, function ($query) use ($column, $operator, $value): void {
            if ($column instanceof Closure) {
                $column($query);
            } else {
                $query->where($column, $operator, $value);
            }
        });
    }
    /**
     * Add an "or where" clause to a relationship query.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<TRelatedModel, *, *>|string  $relation
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>): mixed)|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function or_where_doesnt_have_relation($relation, $column, $operator = null, $value = null)
    {
        return $this->or_where_doesnt_have($relation, function ($query) use ($column, $operator, $value): void {
            if ($column instanceof Closure) {
                $column($query);
            } else {
                $query->where($column, $operator, $value);
            }
        });
    }
    /**
     * Add a polymorphic relationship condition to the query with a where clause.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\MorphTo<TRelatedModel, *>|string  $relation
     * @param  string|array<int, string>  $types
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>): mixed)|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function where_morph_relation($relation, $types, $column, $operator = null, $value = null)
    {
        return $this->where_has_morph($relation, $types, function ($query) use ($column, $operator, $value): void {
            $query->where($column, $operator, $value);
        });
    }
    /**
     * Add a polymorphic relationship condition to the query with an "or where" clause.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\MorphTo<TRelatedModel, *>|string  $relation
     * @param  string|array<int, string>  $types
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>): mixed)|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function or_where_morph_relation($relation, $types, $column, $operator = null, $value = null)
    {
        return $this->or_where_has_morph($relation, $types, function ($query) use ($column, $operator, $value): void {
            $query->where($column, $operator, $value);
        });
    }
    /**
     * Add a polymorphic relationship condition to the query with a doesn't have clause.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\MorphTo<TRelatedModel, *>|string  $relation
     * @param  string|array<int, string>  $types
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>): mixed)|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function where_morph_doesnt_have_relation($relation, $types, $column, $operator = null, $value = null)
    {
        return $this->where_doesnt_have_morph($relation, $types, function ($query) use ($column, $operator, $value): void {
            $query->where($column, $operator, $value);
        });
    }
    /**
     * Add a polymorphic relationship condition to the query with an "or doesn't have" clause.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Relations\MorphTo<TRelatedModel, *>|string  $relation
     * @param  string|array<int, string>  $types
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder<TRelatedModel>): mixed)|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function or_where_morph_doesnt_have_relation($relation, $types, $column, $operator = null, $value = null)
    {
        return $this->or_where_doesnt_have_morph($relation, $types, function ($query) use ($column, $operator, $value): void {
            $query->where($column, $operator, $value);
        });
    }
    /**
     * Add a morph-to relationship condition to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\MorphTo<*, *>|string  $relation
     * @param  \Illuminate\Database\Eloquent\Model|iterable<int, \Illuminate\Database\Eloquent\Model>|string|null  $model
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function where_morphed_to($relation, $model, $boolean = 'and')
    {
        if (is_string($relation)) {
            $relation = $this->get_relation_without_constraints($relation);
        }
        if (is_null($model)) {
            return $this->where_null($relation->qualify_column($relation->get_morph_type()), $boolean);
        }
        if (is_string($model)) {
            $morph_map = Relation::morph_map();
            if (!empty($morph_map) && in_array($model, $morph_map)) {
                $model = array_search($model, $morph_map, true);
            }
            return $this->where($relation->qualify_column($relation->get_morph_type()), $model, null, $boolean);
        }
        $models = Base_Collection::wrap($model);
        if ($models->is_empty()) {
            throw new InvalidArgumentException('Collection given to whereMorphedTo method may not be empty.');
        }
        return $this->where(function ($query) use ($relation, $models): void {
            $models->group_by(fn($model): int|string|false => $model->get_morph_class())->each(function ($models) use ($query, $relation): void {
                $query->or_where(function ($query) use ($relation, $models): void {
                    $query->where($relation->qualify_column($relation->get_morph_type()), $models->first()->get_morph_class())->where_in($relation->qualify_column($relation->get_foreign_key_name()), $models->map->get_key());
                });
            });
        }, null, null, $boolean);
    }
    /**
     * Add a not morph-to relationship condition to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\MorphTo<*, *>|string  $relation
     * @param  \Illuminate\Database\Eloquent\Model|iterable<int, \Illuminate\Database\Eloquent\Model>|string  $model
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function where_not_morphed_to($relation, $model, $boolean = 'and')
    {
        if (is_string($relation)) {
            $relation = $this->get_relation_without_constraints($relation);
        }
        if (is_string($model)) {
            $morph_map = Relation::morph_map();
            if (!empty($morph_map) && in_array($model, $morph_map)) {
                $model = array_search($model, $morph_map, true);
            }
            return $this->where_not(fn($query) => $query->where_null_safe_equals($relation->qualify_column($relation->get_morph_type()), $model), null, null, $boolean);
        }
        $models = Base_Collection::wrap($model);
        if ($models->is_empty()) {
            throw new InvalidArgumentException('Collection given to whereNotMorphedTo method may not be empty.');
        }
        return $this->where_not(function ($query) use ($relation, $models): void {
            $models->group_by(fn($model): int|string|false => $model->get_morph_class())->each(function ($models) use ($query, $relation): void {
                $query->or_where(function ($query) use ($relation, $models): void {
                    $query->where_null_safe_equals($relation->qualify_column($relation->get_morph_type()), $models->first()->get_morph_class())->where_in($relation->qualify_column($relation->get_foreign_key_name()), $models->map->get_key());
                });
            });
        }, null, null, $boolean);
    }
    /**
     * Add a morph-to relationship condition to the query with an "or where" clause.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\MorphTo<*, *>|string  $relation
     * @param  \Illuminate\Database\Eloquent\Model|iterable<int, \Illuminate\Database\Eloquent\Model>|string|null  $model
     * @return $this
     */
    public function or_where_morphed_to($relation, $model)
    {
        return $this->where_morphed_to($relation, $model, 'or');
    }
    /**
     * Add a not morph-to relationship condition to the query with an "or where" clause.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\MorphTo<*, *>|string  $relation
     * @param  \Illuminate\Database\Eloquent\Model|iterable<int, \Illuminate\Database\Eloquent\Model>|string  $model
     * @return $this
     */
    public function or_where_not_morphed_to($relation, $model)
    {
        return $this->where_not_morphed_to($relation, $model, 'or');
    }
    /**
     * Add a "belongs to" relationship where clause to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>  $related
     * @param  string|null  $relationshipName
     * @param  string  $boolean
     * @return $this
     *
     * @throws \Illuminate\Database\Eloquent\RelationNotFoundException
     */
    public function where_belongs_to($related, $relationship_name = null, $boolean = 'and')
    {
        if (!$related instanceof Eloquent_Collection) {
            $related_collection = $related->new_collection([$related]);
        } else {
            $related_collection = $related;
            $related = $related_collection->first();
        }
        if ($related_collection->is_empty()) {
            throw new InvalidArgumentException('Collection given to whereBelongsTo method may not be empty.');
        }
        if ($relationship_name === null) {
            $relationship_name = Str::camel(class_basename($related));
        }
        try {
            $relationship = $this->model->{$relationship_name}();
        } catch (BadMethodCallException) {
            throw Relation_Not_Found_Exception::make($this->model, $relationship_name);
        }
        if (!$relationship instanceof Belongs_To) {
            throw Relation_Not_Found_Exception::make($this->model, $relationship_name, Belongs_To::class);
        }
        $this->where_in($relationship->get_qualified_foreign_key_name(), $related_collection->pluck($relationship->get_owner_key_name())->to_array(), $boolean);
        return $this;
    }
    /**
     * Add a "BelongsTo" relationship with an "or where" clause to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $related
     * @param  string|null  $relationshipName
     * @return $this
     *
     * @throws \RuntimeException
     */
    public function or_where_belongs_to($related, $relationship_name = null)
    {
        return $this->where_belongs_to($related, $relationship_name, 'or');
    }
    /**
     * Add a "belongs to many" relationship where clause to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>  $related
     * @param  string|null  $relationshipName
     * @param  string  $boolean
     * @return $this
     *
     * @throws \Illuminate\Database\Eloquent\RelationNotFoundException
     */
    public function where_attached_to($related, $relationship_name = null, $boolean = 'and')
    {
        $related_collection = $related instanceof Eloquent_Collection ? $related : $related->new_collection([$related]);
        $related = $related_collection->first();
        if ($related_collection->is_empty()) {
            throw new InvalidArgumentException('Collection given to whereAttachedTo method may not be empty.');
        }
        if ($relationship_name === null) {
            $relationship_name = Str::plural(Str::camel(class_basename($related)));
        }
        try {
            $relationship = $this->model->{$relationship_name}();
        } catch (BadMethodCallException) {
            throw Relation_Not_Found_Exception::make($this->model, $relationship_name);
        }
        if (!$relationship instanceof Belongs_To_Many) {
            throw Relation_Not_Found_Exception::make($this->model, $relationship_name, Belongs_To_Many::class);
        }
        $this->has($relationship_name, boolean: $boolean, callback: fn(Builder $query) => $query->where_key($related_collection->pluck($related->get_key_name())));
        return $this;
    }
    /**
     * Add a "belongs to many" relationship with an "or where" clause to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $related
     * @param  string|null  $relationshipName
     * @return $this
     *
     * @throws \RuntimeException
     */
    public function or_where_attached_to($related, $relationship_name = null)
    {
        return $this->where_attached_to($related, $relationship_name, 'or');
    }
    /**
     * Add subselect queries to include an aggregate value for a relationship.
     *
     * @param  mixed  $relations
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  string|null  $function
     * @return $this
     */
    public function with_aggregate($relations, $column, $function = null)
    {
        if (empty($relations)) {
            return $this;
        }
        if (is_null($this->query->columns)) {
            $this->query->select([$this->query->from . '.*']);
        }
        $relations = is_array($relations) ? $relations : [$relations];
        foreach ($this->parse_with_relations($relations) as $name => $constraints) {
            // First we will determine if the name has been aliased using an "as" clause on the name
            // and if it has we will extract the actual relationship name and the desired name of
            // the resulting column. This allows multiple aggregates on the same relationships.
            $segments = explode(' ', (string) $name);
            unset($alias);
            if (count($segments) === 3 && Str::lower($segments[1]) === 'as') {
                [$name, $alias] = [$segments[0], $segments[2]];
            }
            $relation = $this->get_relation_without_constraints($name);
            if ($function) {
                if ($this->get_query()->get_grammar()->is_expression($column)) {
                    $aggregate_column = $this->get_query()->get_grammar()->get_value($column);
                } else {
                    $hashed_column = $this->get_relation_hashed_column($column, $relation);
                    $aggregate_column = $this->get_query()->get_grammar()->wrap($column === '*' ? $column : $relation->get_related()->qualify_column($hashed_column));
                }
                $expression = $function === 'exists' ? $aggregate_column : sprintf('%s(%s)', $function, $aggregate_column);
            } else {
                $expression = $this->get_query()->get_grammar()->get_value($column);
            }
            // Here, we will grab the relationship sub-query and prepare to add it to the main query
            // as a sub-select. First, we'll get the "has" query and use that to get the relation
            // sub-query. We'll format this relationship name and append this column if needed.
            $query = $relation->get_relation_existence_query($relation->get_related()->new_query(), $this, new Expression($expression))->set_bindings([], 'select');
            $query->call_scope($constraints);
            $query = $query->merge_constraints_from($relation->get_query())->to_base();
            // If the query contains certain elements like orderings / more than one column selected
            // then we will remove those elements from the query so that it will execute properly
            // when given to the database. Otherwise, we may receive SQL errors or poor syntax.
            $query->orders = null;
            $query->set_bindings([], 'order');
            if (count($query->columns) > 1) {
                $query->columns = [$query->columns[0]];
                $query->bindings['select'] = [];
            }
            // Finally, we will make the proper column alias to the query and run this sub-select on
            // the query builder. Then, we will return the builder instance back to the developer
            // for further constraint chaining that needs to take place on the query as needed.
            $alias ??= Str::snake(preg_replace('/[^[:alnum:][:space:]_]/u', '', sprintf('%s %s %s', $name, $function, strtolower($this->get_query()->get_grammar()->get_value($column)))));
            if ($function === 'exists') {
                $this->select_raw(sprintf('exists(%s) as %s', $query->to_sql(), $this->get_query()->grammar->wrap($alias)), $query->get_bindings())->with_casts([$alias => 'bool']);
            } else {
                $this->select_sub($function ? $query : $query->limit(1), $alias);
            }
        }
        return $this;
    }
    /**
     * Get the relation hashed column name for the given column and relation.
     *
     * @param  string  $column
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<*, *, *>  $relation
     * @return string
     */
    protected function get_relation_hashed_column($column, $relation)
    {
        if (str_contains($column, '.')) {
            return $column;
        }
        return $this->get_query()->from === $relation->get_query()->get_query()->from ? "{$relation->get_relation_count_hash(false)}.{$column}" : $column;
    }
    /**
     * Add subselect queries to count the relations.
     *
     * @param  mixed  $relations
     * @return $this
     */
    public function with_count($relations)
    {
        return $this->with_aggregate(is_array($relations) ? $relations : func_get_args(), '*', 'count');
    }
    /**
     * Add subselect queries to include the max of the relation's column.
     *
     * @param  string|array  $relation
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return $this
     */
    public function with_max($relation, $column)
    {
        return $this->with_aggregate($relation, $column, 'max');
    }
    /**
     * Add subselect queries to include the min of the relation's column.
     *
     * @param  string|array  $relation
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return $this
     */
    public function with_min($relation, $column)
    {
        return $this->with_aggregate($relation, $column, 'min');
    }
    /**
     * Add subselect queries to include the sum of the relation's column.
     *
     * @param  string|array  $relation
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return $this
     */
    public function with_sum($relation, $column)
    {
        return $this->with_aggregate($relation, $column, 'sum');
    }
    /**
     * Add subselect queries to include the average of the relation's column.
     *
     * @param  string|array  $relation
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return $this
     */
    public function with_avg($relation, $column)
    {
        return $this->with_aggregate($relation, $column, 'avg');
    }
    /**
     * Add subselect queries to include the existence of related models.
     *
     * @param  string|array  $relation
     * @return $this
     */
    public function with_exists($relation)
    {
        return $this->with_aggregate($relation, '*', 'exists');
    }
    /**
     * Add the "has" condition where clause to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $hasQuery
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<*, *, *>  $relation
     * @param  string  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|int  $count
     * @param  string  $boolean
     * @return $this
     */
    protected function add_has_where(Builder $has_query, Relation $relation, $operator, $count, $boolean)
    {
        $has_query->merge_constraints_from($relation->get_query());
        return $this->can_use_exists_for_existence_check($operator, $count) ? $this->add_where_exists_query($has_query->to_base(), $boolean, $operator === '<' && $count === 1) : $this->add_where_count_query($has_query->to_base(), $operator, $count, $boolean);
    }
    /**
     * Merge the where constraints from another query to the current query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $from
     * @return $this
     */
    public function merge_constraints_from(Builder $from)
    {
        $where_bindings = $from->get_query()->get_raw_bindings()['where'] ?? [];
        $wheres = $from->get_query()->from !== $this->get_query()->from ? $this->requalify_where_tables($from->get_query()->wheres, $from->get_query()->grammar->get_value($from->get_query()->from), $this->get_model()->get_table()) : $from->get_query()->wheres;
        // Here we have some other query that we want to merge the where constraints from. We will
        // copy over any where constraints on the query as well as remove any global scopes the
        // query might have removed. Then we will return ourselves with the finished merging.
        return $this->without_global_scopes($from->removed_scopes())->merge_wheres($wheres, $where_bindings);
    }
    /**
     * Updates the table name for any columns with a new qualified name.
     */
    protected function requalify_where_tables(array $wheres, string $from, string $to): array
    {
        return (new Base_Collection($wheres))->map(fn($where): \Illuminate\Support\Collection => (new Base_Collection($where))->map(fn($value): mixed => is_string($value) && str_starts_with($value, $from . '.') ? $to . '.' . Str::after_last($value, '.') : $value))->to_array();
    }
    /**
     * Add a sub-query count clause to this query.
     *
     * @param  string  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|int  $count
     * @param  string  $boolean
     * @return $this
     */
    protected function add_where_count_query(Query_Builder $query, $operator = '>=', $count = 1, $boolean = 'and')
    {
        $this->query->add_binding($query->get_bindings(), 'where');
        return $this->where(new Expression('(' . $query->to_sql() . ')'), $operator, is_numeric($count) ? new Expression($count) : $count, $boolean);
    }
    /**
     * Get the "has relation" base query instance.
     *
     * @param  string  $relation
     * @return \Illuminate\Database\Eloquent\Relations\Relation<*, *, *>
     */
    protected function get_relation_without_constraints($relation)
    {
        return Relation::no_constraints(fn() => $this->get_model()->{$relation}());
    }
    /**
     * Check if we can run an "exists" query to optimize performance.
     *
     * @param  string  $operator
     * @param  \Illuminate\Contracts\Database\Query\Expression|int  $count
     */
    protected function can_use_exists_for_existence_check($operator, $count): bool
    {
        return ($operator === '>=' || $operator === '<') && $count === 1;
    }
}