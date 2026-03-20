<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Database;

use Illuminate\Database\Eloquent\Relations\Relation;
class Model_Identifier
{
    /**
     * Use the Relation morphMap for a Model's name when serializing.
     */
    protected static bool $use_morph_map = false;
    /**
     * The class name of the model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>|string|null
     */
    public $class;
    /**
     * The relationships loaded on the model.
     *
     * @var array
     */
    public $relations;
    /**
     * The class name of the model collection.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Collection>|null
     */
    public $collection_class;
    /**
     * Create a new model identifier.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>|null  $class
     * @param  mixed  $id
     * @param  mixed  $connection
     */
    public function __construct(
        $class,
        /**
         * The unique identifier of the model.
         *
         * This may be either a single ID or an array of IDs.
         */
        public $id,
        array $relations,
        /**
         * The connection name of the model.
         */
        public $connection
    )
    {
        if ($class !== null && self::$use_morph_map) {
            $class = Relation::get_morph_alias($class);
        }
        $this->class = $class;
        $this->relations = $relations;
    }
    /**
     * Specify the collection class that should be used when serializing / restoring collections.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Collection>  $collectionClass
     * @return $this
     */
    public function use_collection_class(?string $collection_class): static
    {
        $this->collection_class = $collection_class;
        return $this;
    }
    /**
     * Get the fully-qualified class name of the Model.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>|null
     */
    public function get_class(): ?string
    {
        if (self::$use_morph_map && $this->class !== null) {
            return Relation::get_morphed_model($this->class) ?? $this->class;
        }
        return $this->class;
    }
    /**
     * Indicate whether to use the relational morph-map when serializing Models.
     */
    public static function use_morph_map(bool $use_morph_map = true): void
    {
        static::$use_morph_map = $use_morph_map;
    }
}