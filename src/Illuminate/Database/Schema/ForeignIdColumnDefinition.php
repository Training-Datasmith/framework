<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema;

use Illuminate\Support\Stringable;
class Foreign_Id_Column_Definition extends Column_Definition
{
    /**
     * Create a new foreign ID column definition.
     *
     * @param  array  $attributes
     */
    public function __construct(
        /**
         * The schema builder blueprint instance.
         */
        protected \Illuminate\Database\Schema\Blueprint $blueprint,
        $attributes = []
    )
    {
        parent::__construct($attributes);
    }
    /**
     * Create a foreign key constraint on this column referencing the "id" column of the conventionally related table.
     *
     * @param  string|null  $table
     * @param  string|null  $column
     * @param  string|null  $indexName
     * @return \Illuminate\Database\Schema\ForeignKeyDefinition
     */
    public function constrained($table = null, $column = null, $index_name = null)
    {
        $table ??= $this->table;
        $column ??= $this->references_model_column ?? 'id';
        return $this->references($column, $index_name)->on($table ?? (new Stringable($this->name))->before_last('_' . $column)->plural());
    }
    /**
     * Specify which column this foreign ID references on another table.
     *
     * @param  string  $column
     * @param  string|null  $indexName
     * @return \Illuminate\Database\Schema\ForeignKeyDefinition
     */
    public function references($column, $index_name = null)
    {
        return $this->blueprint->foreign($this->name, $index_name)->references($column);
    }
}