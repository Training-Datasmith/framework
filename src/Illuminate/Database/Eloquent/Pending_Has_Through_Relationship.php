<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Relations\Has_Many;
use Illuminate\Database\Eloquent\Relations\Morph_One_Or_Many;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
/**
 * @template TIntermediateModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 * @template TLocalRelationship of \Illuminate\Database\Eloquent\Relations\HasOneOrMany<TIntermediateModel, TDeclaringModel>
 */
class Pending_Has_Through_Relationship
{
    /**
     * Create a pending has-many-through or has-one-through relationship.
     *
     * @param  TDeclaringModel  $rootModel
     * @param  TLocalRelationship  $localRelationship
     */
    public function __construct(
        /**
         * The root model that the relationship exists on.
         */
        protected $root_model,
        /**
         * The local relationship.
         */
        protected $local_relationship
    )
    {
    }
    /**
     * Define the distant relationship that this model has.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  string|(callable(TIntermediateModel): (\Illuminate\Database\Eloquent\Relations\HasOne<TRelatedModel, TIntermediateModel>|\Illuminate\Database\Eloquent\Relations\HasMany<TRelatedModel, TIntermediateModel>|\Illuminate\Database\Eloquent\Relations\MorphOneOrMany<TRelatedModel, TIntermediateModel>))  $callback
     * @return (
     *     $callback is string
     *     ? \Illuminate\Database\Eloquent\Relations\HasManyThrough<\Illuminate\Database\Eloquent\Model, TIntermediateModel, TDeclaringModel>|\Illuminate\Database\Eloquent\Relations\HasOneThrough<\Illuminate\Database\Eloquent\Model, TIntermediateModel, TDeclaringModel>
     *     : (
     *         TLocalRelationship is \Illuminate\Database\Eloquent\Relations\HasMany<TIntermediateModel, TDeclaringModel>
     *         ? \Illuminate\Database\Eloquent\Relations\HasManyThrough<TRelatedModel, TIntermediateModel, TDeclaringModel>
     *         : (
     *              $callback is callable(TIntermediateModel): \Illuminate\Database\Eloquent\Relations\HasMany<TRelatedModel, TIntermediateModel>
     *              ? \Illuminate\Database\Eloquent\Relations\HasManyThrough<TRelatedModel, TIntermediateModel, TDeclaringModel>
     *              : \Illuminate\Database\Eloquent\Relations\HasOneThrough<TRelatedModel, TIntermediateModel, TDeclaringModel>
     *         )
     *     )
     * )
     */
    public function has($callback)
    {
        if (is_string($callback)) {
            $callback = fn() => $this->local_relationship->get_related()->{$callback}();
        }
        $distant_relation = $callback($this->local_relationship->get_related());
        if ($distant_relation instanceof Has_Many || $this->local_relationship instanceof Has_Many) {
            $returned_relation = $this->root_model->has_many_through($distant_relation->get_related()::class, $this->local_relationship->get_related()::class, $this->local_relationship->get_foreign_key_name(), $distant_relation->get_foreign_key_name(), $this->local_relationship->get_local_key_name(), $distant_relation->get_local_key_name());
        } else {
            $returned_relation = $this->root_model->has_one_through($distant_relation->get_related()::class, $this->local_relationship->get_related()::class, $this->local_relationship->get_foreign_key_name(), $distant_relation->get_foreign_key_name(), $this->local_relationship->get_local_key_name(), $distant_relation->get_local_key_name());
        }
        if ($this->local_relationship instanceof Morph_One_Or_Many) {
            $returned_relation->where($this->local_relationship->get_qualified_morph_type(), $this->local_relationship->get_morph_class());
        }
        return $returned_relation;
    }
    /**
     * Handle dynamic method calls into the model.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (Str::starts_with($method, 'has')) {
            return $this->has((new Stringable($method))->after('has')->lcfirst()->to_string());
        }
        throw new BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $method));
    }
}