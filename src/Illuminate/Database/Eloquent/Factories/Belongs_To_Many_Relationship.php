<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Factories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
class Belongs_To_Many_Relationship
{
    /**
     * The pivot attributes / attribute resolver.
     *
     * @var callable|array
     */
    protected $pivot;
    /**
     * Create a new attached relationship definition.
     *
     * @param  \Illuminate\Database\Eloquent\Factories\Factory|\Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Model|array  $factory
     * @param  callable|array  $pivot
     * @param  string  $relationship
     */
    public function __construct(
        /**
         * The related factory instance.
         */
        protected $factory,
        $pivot,
        /**
         * The relationship name.
         */
        protected $relationship
    )
    {
        $this->pivot = $pivot;
    }
    /**
     * Create the attached relationship for the given model.
     */
    public function create_for(Model $model): void
    {
        $factory_instance = $this->factory instanceof Factory;
        if ($factory_instance) {
            $relationship = $model->{$this->relationship}();
        }
        Collection::wrap($factory_instance ? $this->factory->prepend_state($relationship->get_query()->pending_attributes)->create([], $model) : $this->factory)->each(function ($attachable) use ($model): void {
            $model->{$this->relationship}()->attach($attachable, is_callable($this->pivot) ? call_user_func($this->pivot, $model) : $this->pivot);
        });
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