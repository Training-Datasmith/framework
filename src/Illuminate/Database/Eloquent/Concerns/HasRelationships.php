<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Concerns;

use Closure;
use Illuminate\Database\Class_Morph_Violation_Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Pending_Has_Through_Relationship;
use Illuminate\Database\Eloquent\Relations\Belongs_To;
use Illuminate\Database\Eloquent\Relations\Belongs_To_Many;
use Illuminate\Database\Eloquent\Relations\Has_Many;
use Illuminate\Database\Eloquent\Relations\Has_Many_Through;
use Illuminate\Database\Eloquent\Relations\Has_One;
use Illuminate\Database\Eloquent\Relations\Has_One_Through;
use Illuminate\Database\Eloquent\Relations\Morph_Many;
use Illuminate\Database\Eloquent\Relations\Morph_One;
use Illuminate\Database\Eloquent\Relations\Morph_To;
use Illuminate\Database\Eloquent\Relations\Morph_To_Many;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
trait Has_Relationships
{
    /**
     * The loaded relationships for the model.
     *
     * @var array
     */
    protected $relations = [];
    /**
     * The relationships that should be touched on save.
     *
     * @var array
     */
    protected $touches = [];
    /**
     * The relationship autoloader callback.
     *
     * @var \Closure|null
     */
    protected $relation_autoload_callback;
    /**
     * The relationship autoloader callback context.
     *
     * @var mixed
     */
    protected $relation_autoload_context;
    /**
     * The many to many relationship methods.
     *
     * @var string[]
     */
    public static $many_methods = ['belongsToMany', 'morphToMany', 'morphedByMany'];
    /**
     * The relation resolver callbacks.
     *
     * @var array
     */
    protected static $relation_resolvers = [];
    /**
     * Get the dynamic relation resolver if defined or inherited, or return null.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $class
     * @param  string  $key
     * @return Closure|null
     */
    public function relation_resolver($class, $key)
    {
        if ($resolver = static::$relation_resolvers[$class][$key] ?? null) {
            return $resolver;
        }
        if ($parent = get_parent_class($class)) {
            return $this->relation_resolver($parent, $key);
        }
        return null;
    }
    /**
     * Define a dynamic relation resolver.
     *
     * @param  string  $name
     */
    public static function resolve_relation_using($name, Closure $callback): void
    {
        static::$relation_resolvers = array_replace_recursive(static::$relation_resolvers, [static::class => [$name => $callback]]);
    }
    /**
     * Determine if a relationship autoloader callback has been defined.
     */
    public function has_relation_autoload_callback(): bool
    {
        return !is_null($this->relation_autoload_callback);
    }
    /**
     * Define an automatic relationship autoloader callback for this model and its relations.
     *
     * @param  mixed  $context
     * @return $this
     */
    public function autoload_relations_using(Closure $callback, $context = null)
    {
        // Prevent circular relation autoloading...
        if ($context && $this->relation_autoload_context === $context) {
            return $this;
        }
        $this->relation_autoload_callback = $callback;
        $this->relation_autoload_context = $context;
        foreach ($this->relations as $key => $value) {
            $this->propagate_relation_autoload_callback_to_relation($key, $value);
        }
        return $this;
    }
    /**
     * Attempt to autoload the given relationship using the autoload callback.
     *
     * @param  string  $key
     * @return bool
     */
    protected function attempt_to_autoload_relation($key)
    {
        if (!$this->has_relation_autoload_callback()) {
            return false;
        }
        $this->invoke_relation_autoload_callback_for($key, []);
        return $this->relation_loaded($key);
    }
    /**
     * Invoke the relationship autoloader callback for the given relationships.
     *
     * @param  string  $key
     * @param  array  $tuples
     * @return void
     */
    protected function invoke_relation_autoload_callback_for($key, $tuples)
    {
        $tuples = array_merge([[$key, $this::class]], $tuples);
        call_user_func($this->relation_autoload_callback, $tuples);
    }
    /**
     * Propagate the relationship autoloader callback to the given related models.
     *
     * @param  string  $key
     * @param  mixed  $models
     * @return void
     */
    protected function propagate_relation_autoload_callback_to_relation($key, $models)
    {
        if (!$this->has_relation_autoload_callback() || !$models) {
            return;
        }
        if ($models instanceof Model) {
            $models = [$models];
        }
        if (!is_iterable($models)) {
            return;
        }
        $callback = fn(array $tuples) => $this->invoke_relation_autoload_callback_for($key, $tuples);
        foreach ($models as $model) {
            $model->autoload_relations_using($callback, $this->relation_autoload_context);
        }
    }
    /**
     * Define a one-to-one relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<TRelatedModel, $this>
     */
    public function has_one($related, $foreign_key = null, $local_key = null)
    {
        $instance = $this->new_related_instance($related);
        $foreign_key = $foreign_key ?: $this->get_foreign_key();
        $local_key = $local_key ?: $this->get_key_name();
        return $this->new_has_one($instance->new_query(), $this, $instance->qualify_column($foreign_key), $local_key);
    }
    /**
     * Instantiate a new HasOne relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<TRelatedModel, TDeclaringModel>
     */
    protected function new_has_one(Builder $query, Model $parent, $foreign_key, $local_key): \Illuminate\Database\Eloquent\Relations\Has_One
    {
        return new Has_One($query, $parent, $foreign_key, $local_key);
    }
    /**
     * Define a has-one-through relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TIntermediateModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  class-string<TIntermediateModel>  $through
     * @param  string|null  $firstKey
     * @param  string|null  $secondKey
     * @param  string|null  $localKey
     * @param  string|null  $secondLocalKey
     * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough<TRelatedModel, TIntermediateModel, $this>
     */
    public function has_one_through($related, $through, $first_key = null, $second_key = null, $local_key = null, $second_local_key = null)
    {
        $through = $this->new_related_through_instance($through);
        $first_key = $first_key ?: $this->get_foreign_key();
        $second_key = $second_key ?: $through->get_foreign_key();
        return $this->new_has_one_through($this->new_related_instance($related)->new_query(), $this, $through, $first_key, $second_key, $local_key ?: $this->get_key_name(), $second_local_key ?: $through->get_key_name());
    }
    /**
     * Instantiate a new HasOneThrough relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TIntermediateModel of \Illuminate\Database\Eloquent\Model
     * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $farParent
     * @param  TIntermediateModel  $throughParent
     * @param  string  $firstKey
     * @param  string  $secondKey
     * @param  string  $localKey
     * @param  string  $secondLocalKey
     * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough<TRelatedModel, TIntermediateModel, TDeclaringModel>
     */
    protected function new_has_one_through(Builder $query, Model $far_parent, Model $through_parent, $first_key, $second_key, $local_key, $second_local_key): \Illuminate\Database\Eloquent\Relations\Has_One_Through
    {
        return new Has_One_Through($query, $far_parent, $through_parent, $first_key, $second_key, $local_key, $second_local_key);
    }
    /**
     * Define a polymorphic one-to-one relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  string  $name
     * @param  string|null  $type
     * @param  string|null  $id
     * @param  string|null  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne<TRelatedModel, $this>
     */
    public function morph_one($related, $name, $type = null, $id = null, $local_key = null)
    {
        $instance = $this->new_related_instance($related);
        [$type, $id] = $this->get_morphs($name, $type, $id);
        $local_key = $local_key ?: $this->get_key_name();
        return $this->new_morph_one($instance->new_query(), $this, $instance->qualify_column($type), $instance->qualify_column($id), $local_key);
    }
    /**
     * Instantiate a new MorphOne relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  string  $type
     * @param  string  $id
     * @param  string  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne<TRelatedModel, TDeclaringModel>
     */
    protected function new_morph_one(Builder $query, Model $parent, $type, $id, $local_key): \Illuminate\Database\Eloquent\Relations\Morph_One
    {
        return new Morph_One($query, $parent, $type, $id, $local_key);
    }
    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  string|null  $foreignKey
     * @param  string|null  $ownerKey
     * @param  string|null  $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<TRelatedModel, $this>
     */
    public function belongs_to($related, $foreign_key = null, $owner_key = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation)) {
            $relation = $this->guess_belongs_to_relation();
        }
        $instance = $this->new_related_instance($related);
        // If no foreign key was supplied, we can use a backtrace to guess the proper
        // foreign key name by using the name of the relationship function, which
        // when combined with an "_id" should conventionally match the columns.
        if (is_null($foreign_key)) {
            $foreign_key = Str::snake($relation) . '_' . $instance->get_key_name();
        }
        // Once we have the foreign key names we'll just create a new Eloquent query
        // for the related models and return the relationship instance which will
        // actually be responsible for retrieving and hydrating every relation.
        $owner_key = $owner_key ?: $instance->get_key_name();
        return $this->new_belongs_to($instance->new_query(), $this, $foreign_key, $owner_key, $relation);
    }
    /**
     * Instantiate a new BelongsTo relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $child
     * @param  string  $foreignKey
     * @param  string  $ownerKey
     * @param  string  $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<TRelatedModel, TDeclaringModel>
     */
    protected function new_belongs_to(Builder $query, Model $child, $foreign_key, $owner_key, $relation): \Illuminate\Database\Eloquent\Relations\Belongs_To
    {
        return new Belongs_To($query, $child, $foreign_key, $owner_key, $relation);
    }
    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     *
     * @param  string|null  $name
     * @param  string|null  $type
     * @param  string|null  $id
     * @param  string|null  $ownerKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function morph_to($name = null, $type = null, $id = null, $owner_key = null)
    {
        // If no name is provided, we will use the backtrace to get the function name
        // since that is most likely the name of the polymorphic interface. We can
        // use that to get both the class and foreign key that will be utilized.
        $name = $name ?: $this->guess_belongs_to_relation();
        [$type, $id] = $this->get_morphs(Str::snake($name), $type, $id);
        // If the type value is null it is probably safe to assume we're eager loading
        // the relationship. In this case we'll just pass in a dummy query where we
        // need to remove any eager loads that may already be defined on a model.
        return is_null($class = $this->get_attribute_from_array($type)) || $class === '' ? $this->morph_eager_to($name, $type, $id, $owner_key) : $this->morph_instance_to($class, $name, $type, $id, $owner_key);
    }
    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     *
     * @param  string  $name
     * @param  string  $type
     * @param  string  $id
     * @param  string|null  $ownerKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    protected function morph_eager_to($name, $type, $id, $owner_key)
    {
        return $this->new_morph_to($this->new_query()->set_eager_loads([]), $this, $id, $owner_key, $type, $name);
    }
    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     *
     * @param  string  $target
     * @param  string  $name
     * @param  string  $type
     * @param  string  $id
     * @param  string|null  $ownerKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    protected function morph_instance_to($target, $name, $type, $id, $owner_key)
    {
        $instance = $this->new_related_instance(static::get_actual_class_name_for_morph($target));
        return $this->new_morph_to($instance->new_query(), $this, $id, $owner_key ?? $instance->get_key_name(), $type, $name);
    }
    /**
     * Instantiate a new MorphTo relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  string  $foreignKey
     * @param  string|null  $ownerKey
     * @param  string  $type
     * @param  string  $relation
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo<TRelatedModel, TDeclaringModel>
     */
    protected function new_morph_to(Builder $query, Model $parent, $foreign_key, $owner_key, $type, $relation): \Illuminate\Database\Eloquent\Relations\Morph_To
    {
        return new Morph_To($query, $parent, $foreign_key, $owner_key, $type, $relation);
    }
    /**
     * Retrieve the actual class name for a given morph class.
     *
     * @param  string  $class
     * @return string
     */
    public static function get_actual_class_name_for_morph($class)
    {
        return Arr::get(Relation::morph_map() ?: [], $class, $class);
    }
    /**
     * Guess the "belongs to" relationship name.
     */
    protected function guess_belongs_to_relation(): string
    {
        [, , $caller] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        return $caller['function'];
    }
    /**
     * Create a pending has-many-through or has-one-through relationship.
     *
     * @template TIntermediateModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  string|\Illuminate\Database\Eloquent\Relations\HasMany<TIntermediateModel, covariant $this>|\Illuminate\Database\Eloquent\Relations\HasOne<TIntermediateModel, covariant $this>  $relationship
     * @return (
     *     $relationship is string
     *     ? \Illuminate\Database\Eloquent\PendingHasThroughRelationship<\Illuminate\Database\Eloquent\Model, $this>
     *     : (
     *          $relationship is \Illuminate\Database\Eloquent\Relations\HasMany<TIntermediateModel, $this>
     *          ? \Illuminate\Database\Eloquent\PendingHasThroughRelationship<TIntermediateModel, $this, \Illuminate\Database\Eloquent\Relations\HasMany<TIntermediateModel, $this>>
     *          : \Illuminate\Database\Eloquent\PendingHasThroughRelationship<TIntermediateModel, $this, \Illuminate\Database\Eloquent\Relations\HasOne<TIntermediateModel, $this>>
     *     )
     * )
     */
    public function through($relationship): \Illuminate\Database\Eloquent\Pending_Has_Through_Relationship
    {
        if (is_string($relationship)) {
            $relationship = $this->{$relationship}();
        }
        return new Pending_Has_Through_Relationship($this, $relationship);
    }
    /**
     * Define a one-to-many relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<TRelatedModel, $this>
     */
    public function has_many($related, $foreign_key = null, $local_key = null)
    {
        $instance = $this->new_related_instance($related);
        $foreign_key = $foreign_key ?: $this->get_foreign_key();
        $local_key = $local_key ?: $this->get_key_name();
        return $this->new_has_many($instance->new_query(), $this, $instance->qualify_column($foreign_key), $local_key);
    }
    /**
     * Instantiate a new HasMany relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<TRelatedModel, TDeclaringModel>
     */
    protected function new_has_many(Builder $query, Model $parent, $foreign_key, $local_key): \Illuminate\Database\Eloquent\Relations\Has_Many
    {
        return new Has_Many($query, $parent, $foreign_key, $local_key);
    }
    /**
     * Define a has-many-through relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TIntermediateModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  class-string<TIntermediateModel>  $through
     * @param  string|null  $firstKey
     * @param  string|null  $secondKey
     * @param  string|null  $localKey
     * @param  string|null  $secondLocalKey
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough<TRelatedModel, TIntermediateModel, $this>
     */
    public function has_many_through($related, $through, $first_key = null, $second_key = null, $local_key = null, $second_local_key = null)
    {
        $through = $this->new_related_through_instance($through);
        $first_key = $first_key ?: $this->get_foreign_key();
        $second_key = $second_key ?: $through->get_foreign_key();
        return $this->new_has_many_through($this->new_related_instance($related)->new_query(), $this, $through, $first_key, $second_key, $local_key ?: $this->get_key_name(), $second_local_key ?: $through->get_key_name());
    }
    /**
     * Instantiate a new HasManyThrough relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TIntermediateModel of \Illuminate\Database\Eloquent\Model
     * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $farParent
     * @param  TIntermediateModel  $throughParent
     * @param  string  $firstKey
     * @param  string  $secondKey
     * @param  string  $localKey
     * @param  string  $secondLocalKey
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough<TRelatedModel, TIntermediateModel, TDeclaringModel>
     */
    protected function new_has_many_through(Builder $query, Model $far_parent, Model $through_parent, $first_key, $second_key, $local_key, $second_local_key): \Illuminate\Database\Eloquent\Relations\Has_Many_Through
    {
        return new Has_Many_Through($query, $far_parent, $through_parent, $first_key, $second_key, $local_key, $second_local_key);
    }
    /**
     * Define a polymorphic one-to-many relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  string  $name
     * @param  string|null  $type
     * @param  string|null  $id
     * @param  string|null  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<TRelatedModel, $this>
     */
    public function morph_many($related, $name, $type = null, $id = null, $local_key = null)
    {
        $instance = $this->new_related_instance($related);
        // Here we will gather up the morph type and ID for the relationship so that we
        // can properly query the intermediate table of a relation. Finally, we will
        // get the table and create the relationship instances for the developers.
        [$type, $id] = $this->get_morphs($name, $type, $id);
        $local_key = $local_key ?: $this->get_key_name();
        return $this->new_morph_many($instance->new_query(), $this, $instance->qualify_column($type), $instance->qualify_column($id), $local_key);
    }
    /**
     * Instantiate a new MorphMany relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  string  $type
     * @param  string  $id
     * @param  string  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<TRelatedModel, TDeclaringModel>
     */
    protected function new_morph_many(Builder $query, Model $parent, $type, $id, $local_key): \Illuminate\Database\Eloquent\Relations\Morph_Many
    {
        return new Morph_Many($query, $parent, $type, $id, $local_key);
    }
    /**
     * Define a many-to-many relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  string|class-string<\Illuminate\Database\Eloquent\Model>|null  $table
     * @param  string|null  $foreignPivotKey
     * @param  string|null  $relatedPivotKey
     * @param  string|null  $parentKey
     * @param  string|null  $relatedKey
     * @param  string|null  $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<TRelatedModel, $this, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function belongs_to_many($related, $table = null, $foreign_pivot_key = null, $related_pivot_key = null, $parent_key = null, $related_key = null, $relation = null)
    {
        // If no relationship name was passed, we will pull backtraces to get the
        // name of the calling function. We will use that function name as the
        // title of this relation since that is a great convention to apply.
        if (is_null($relation)) {
            $relation = $this->guess_belongs_to_many_relation();
        }
        // First, we'll need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we'll make the query
        // instances as well as the relationship instances we need for this.
        $instance = $this->new_related_instance($related);
        $foreign_pivot_key = $foreign_pivot_key ?: $this->get_foreign_key();
        $related_pivot_key = $related_pivot_key ?: $instance->get_foreign_key();
        // If no table name was provided, we can guess it by concatenating the two
        // models using underscores in alphabetical order. The two model names
        // are transformed to snake case from their default CamelCase also.
        if (is_null($table)) {
            $table = $this->joining_table($related, $instance);
        }
        return $this->new_belongs_to_many($instance->new_query(), $this, $table, $foreign_pivot_key, $related_pivot_key, $parent_key ?: $this->get_key_name(), $related_key ?: $instance->get_key_name(), $relation);
    }
    /**
     * Instantiate a new BelongsToMany relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  string|class-string<\Illuminate\Database\Eloquent\Model>  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string|null  $relationName
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<TRelatedModel, TDeclaringModel, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    protected function new_belongs_to_many(Builder $query, Model $parent, $table, $foreign_pivot_key, $related_pivot_key, $parent_key, $related_key, $relation_name = null): \Illuminate\Database\Eloquent\Relations\Belongs_To_Many
    {
        return new Belongs_To_Many($query, $parent, $table, $foreign_pivot_key, $related_pivot_key, $parent_key, $related_key, $relation_name);
    }
    /**
     * Define a polymorphic many-to-many relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  string|null  $table
     * @param  string|null  $foreignPivotKey
     * @param  string|null  $relatedPivotKey
     * @param  string|null  $parentKey
     * @param  string|null  $relatedKey
     * @param  string|null  $relation
     * @param  bool  $inverse
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany<TRelatedModel, $this>
     */
    public function morph_to_many($related, string $name, $table = null, $foreign_pivot_key = null, $related_pivot_key = null, $parent_key = null, $related_key = null, $relation = null, $inverse = false)
    {
        $relation = $relation ?: $this->guess_belongs_to_many_relation();
        // First, we will need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we will make the query
        // instances, as well as the relationship instances we need for these.
        $instance = $this->new_related_instance($related);
        $foreign_pivot_key = $foreign_pivot_key ?: $name . '_id';
        $related_pivot_key = $related_pivot_key ?: $instance->get_foreign_key();
        // Now we're ready to create a new query builder for the related model and
        // the relationship instances for this relation. This relation will set
        // appropriate query constraints then entirely manage the hydrations.
        if (!$table) {
            $words = preg_split('/(_)/u', $name, -1, PREG_SPLIT_DELIM_CAPTURE);
            $last_word = array_pop($words);
            $table = implode('', $words) . Str::plural($last_word);
        }
        return $this->new_morph_to_many($instance->new_query(), $this, $name, $table, $foreign_pivot_key, $related_pivot_key, $parent_key ?: $this->get_key_name(), $related_key ?: $instance->get_key_name(), $relation, $inverse);
    }
    /**
     * Instantiate a new MorphToMany relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  string  $name
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string|null  $relationName
     * @param  bool  $inverse
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany<TRelatedModel, TDeclaringModel>
     */
    protected function new_morph_to_many(Builder $query, Model $parent, $name, $table, $foreign_pivot_key, $related_pivot_key, $parent_key, $related_key, $relation_name = null, $inverse = false): \Illuminate\Database\Eloquent\Relations\Morph_To_Many
    {
        return new Morph_To_Many($query, $parent, $name, $table, $foreign_pivot_key, $related_pivot_key, $parent_key, $related_key, $relation_name, $inverse);
    }
    /**
     * Define a polymorphic, inverse many-to-many relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $related
     * @param  string|null  $table
     * @param  string|null  $foreignPivotKey
     * @param  string|null  $relatedPivotKey
     * @param  string|null  $parentKey
     * @param  string|null  $relatedKey
     * @param  string|null  $relation
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany<TRelatedModel, $this>
     */
    public function morphed_by_many($related, string $name, $table = null, $foreign_pivot_key = null, $related_pivot_key = null, $parent_key = null, $related_key = null, $relation = null)
    {
        $foreign_pivot_key = $foreign_pivot_key ?: $this->get_foreign_key();
        // For the inverse of the polymorphic many-to-many relations, we will change
        // the way we determine the foreign and other keys, as it is the opposite
        // of the morph-to-many method since we're figuring out these inverses.
        $related_pivot_key = $related_pivot_key ?: $name . '_id';
        return $this->morph_to_many($related, $name, $table, $foreign_pivot_key, $related_pivot_key, $parent_key, $related_key, $relation, true);
    }
    /**
     * Get the relationship name of the belongsToMany relationship.
     *
     * @return string|null
     */
    protected function guess_belongs_to_many_relation()
    {
        $caller = Arr::first(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), fn($trace): bool => !in_array($trace['function'], array_merge(static::$many_methods, ['guessBelongsToManyRelation'])));
        return $caller['function'] ?? null;
    }
    /**
     * Get the joining table name for a many-to-many relation.
     *
     * @param  string  $related
     * @param  \Illuminate\Database\Eloquent\Model|null  $instance
     */
    public function joining_table($related, $instance = null): string
    {
        // The joining table name, by convention, is simply the snake cased models
        // sorted alphabetically and concatenated with an underscore, so we can
        // just sort the models and join them together to get the table name.
        $segments = [$instance ? $instance->joining_table_segment() : Str::snake(class_basename($related)), $this->joining_table_segment()];
        // Now that we have the model names in an array we can just sort them and
        // use the implode function to join them together with an underscores,
        // which is typically used by convention within the database system.
        sort($segments);
        return strtolower(implode('_', $segments));
    }
    /**
     * Get this model's half of the intermediate table name for belongsToMany relationships.
     *
     * @return string
     */
    public function joining_table_segment()
    {
        return Str::snake(class_basename($this));
    }
    /**
     * Determine if the model touches a given relation.
     *
     * @param  string  $relation
     */
    public function touches($relation): bool
    {
        return in_array($relation, $this->get_touched_relations());
    }
    /**
     * Touch the owning relations of the model.
     */
    public function touch_owners(): void
    {
        $this->without_recursion(function (): void {
            foreach ($this->get_touched_relations() as $relation) {
                $this->{$relation}()->touch();
                if ($this->{$relation} instanceof self) {
                    $this->{$relation}->fire_model_event('saved', false);
                    $this->{$relation}->touch_owners();
                } elseif ($this->{$relation} instanceof Eloquent_Collection) {
                    $this->{$relation}->each->touch_owners();
                }
            }
        });
    }
    /**
     * Get the polymorphic relationship columns.
     *
     * @param  string  $type
     * @param  string  $id
     */
    protected function get_morphs(string $name, $type, $id): array
    {
        return [$type ?: $name . '_type', $id ?: $name . '_id'];
    }
    /**
     * Get the class name for polymorphic relations.
     *
     * @return string
     */
    public function get_morph_class(): int|string|false
    {
        $morph_map = Relation::morph_map();
        if (!empty($morph_map) && in_array(static::class, $morph_map)) {
            return array_search(static::class, $morph_map, true);
        }
        if (static::class === Pivot::class) {
            return static::class;
        }
        if (Relation::requires_morph_map()) {
            throw new Class_Morph_Violation_Exception($this);
        }
        return static::class;
    }
    /**
     * Create a new model instance for a related model.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $class
     * @return TRelatedModel
     */
    protected function new_related_instance($class)
    {
        return tap(new $class(), function ($instance): void {
            if (!$instance->get_connection_name()) {
                $instance->set_connection($this->connection);
            }
        });
    }
    /**
     * Create a new model instance for a related "through" model.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TRelatedModel>  $class
     * @return TRelatedModel
     */
    protected function new_related_through_instance($class)
    {
        return new $class();
    }
    /**
     * Get all the loaded relations for the instance.
     *
     * @return array
     */
    public function get_relations()
    {
        return $this->relations;
    }
    /**
     * Get a specified relationship.
     *
     * @param  string  $relation
     * @return mixed
     */
    public function get_relation($relation)
    {
        return $this->relations[$relation];
    }
    /**
     * Determine if the given relation is loaded.
     *
     * @param  string  $key
     */
    public function relation_loaded($key): bool
    {
        return array_key_exists($key, $this->relations);
    }
    /**
     * Set the given relationship on the model.
     *
     * @param  string  $relation
     * @param  mixed  $value
     * @return $this
     */
    public function set_relation($relation, $value)
    {
        $this->relations[$relation] = $value;
        $this->propagate_relation_autoload_callback_to_relation($relation, $value);
        return $this;
    }
    /**
     * Unset a loaded relationship.
     *
     * @param  string  $relation
     * @return $this
     */
    public function unset_relation($relation)
    {
        unset($this->relations[$relation]);
        return $this;
    }
    /**
     * Set the entire relations array on the model.
     *
     * @return $this
     */
    public function set_relations(array $relations)
    {
        $this->relations = $relations;
        return $this;
    }
    /**
     * Enable relationship autoloading for this model.
     *
     * @return $this
     */
    public function with_relationship_autoloading()
    {
        $this->new_collection([$this])->with_relationship_autoloading();
        return $this;
    }
    /**
     * Duplicate the instance and unset all the loaded relations.
     *
     * @return $this
     */
    public function without_relations()
    {
        $model = clone $this;
        return $model->unset_relations();
    }
    /**
     * Unset all the loaded relations for the instance.
     *
     * @return $this
     */
    public function unset_relations()
    {
        $this->relations = [];
        return $this;
    }
    /**
     * Get the relationships that are touched on save.
     *
     * @return array
     */
    public function get_touched_relations()
    {
        return $this->touches;
    }
    /**
     * Set the relationships that are touched on save.
     *
     * @return $this
     */
    public function set_touched_relations(array $touches)
    {
        $this->touches = $touches;
        return $this;
    }
}