<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Factories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Morph_To;
class Belongs_To_Relationship
{
    /**
     * The cached, resolved parent instance ID.
     *
     * @var mixed
     */
    protected $resolved;
    /**
     * Create a new "belongs to" relationship definition.
     *
     * @param  \Illuminate\Database\Eloquent\Factories\Factory|\Illuminate\Database\Eloquent\Model  $factory
     * @param  string  $relationship
     */
    public function __construct(
        /**
         * The related factory instance.
         */
        protected $factory,
        /**
         * The relationship name.
         */
        protected $relationship
    )
    {
    }
    /**
     * Get the parent model attributes and resolvers for the given child model.
     *
     * @return array
     */
    public function attributes_for(Model $model)
    {
        $relationship = $model->{$this->relationship}();
        return $relationship instanceof Morph_To ? [$relationship->get_morph_type() => $this->factory instanceof Factory ? $this->factory->new_model()->get_morph_class() : $this->factory->get_morph_class(), $relationship->get_foreign_key_name() => $this->resolver($relationship->get_owner_key_name())] : [$relationship->get_foreign_key_name() => $this->resolver($relationship->get_owner_key_name())];
    }
    /**
     * Get the deferred resolver for this relationship's parent ID.
     *
     * @param  string|null  $key
     * @return \Closure
     */
    protected function resolver($key)
    {
        return function () use ($key) {
            if (!$this->resolved) {
                $instance = $this->factory instanceof Factory ? $this->factory->get_random_recycled_model($this->factory->model_name()) ?? $this->factory->create() : $this->factory;
                return $this->resolved = $key ? $instance->{$key} : $instance->get_key();
            }
            return $this->resolved;
        };
    }
    /**
     * Specify the model instances to always use when creating relationships.
     *
     * @param  \Illuminate\Support\Collection  $recycle
     * @return $this
     */
    public function recycle($recycle): static
    {
        if ($this->factory instanceof Factory) {
            $this->factory = $this->factory->recycle($recycle);
        }
        return $this;
    }
}