<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Factories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Belongs_To_Many;
use Illuminate\Database\Eloquent\Relations\Has_One_Or_Many;
use Illuminate\Database\Eloquent\Relations\Morph_One_Or_Many;
class Relationship
{
    /**
     * Create a new child relationship instance.
     *
     * @param  string  $relationship
     */
    public function __construct(
        /**
         * The related factory instance.
         */
        protected \Illuminate\Database\Eloquent\Factories\Factory $factory,
        /**
         * The relationship name.
         */
        protected $relationship
    )
    {
    }
    /**
     * Create the child relationship for the given parent model.
     */
    public function create_for(Model $parent): void
    {
        $relationship = $parent->{$this->relationship}();
        if ($relationship instanceof Morph_One_Or_Many) {
            $this->factory->state([$relationship->get_morph_type() => $relationship->get_morph_class(), $relationship->get_foreign_key_name() => $relationship->get_parent_key()])->prepend_state($relationship->get_query()->pending_attributes)->create([], $parent);
        } elseif ($relationship instanceof Has_One_Or_Many) {
            $this->factory->state([$relationship->get_foreign_key_name() => $relationship->get_parent_key()])->prepend_state($relationship->get_query()->pending_attributes)->create([], $parent);
        } elseif ($relationship instanceof Belongs_To_Many) {
            $relationship->attach($this->factory->prepend_state($relationship->get_query()->pending_attributes)->create([], $parent));
        }
    }
    /**
     * Specify the model instances to always use when creating relationships.
     *
     * @param  \Illuminate\Support\Collection  $recycle
     * @return $this
     */
    public function recycle($recycle): static
    {
        $this->factory = $this->factory->recycle($recycle);
        return $this;
    }
}