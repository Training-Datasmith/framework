<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema\Grammars;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;
class Maria_Db_Grammar extends My_Sql_Grammar
{
    /** @inheritDoc */
    public function compile_rename_column(Blueprint $blueprint, Fluent $command)
    {
        if (version_compare($this->connection->get_server_version(), '10.5.2', '<')) {
            return $this->compile_legacy_rename_column($blueprint, $command);
        }
        return parent::compile_rename_column($blueprint, $command);
    }
    /**
     * Create the column definition for a uuid type.
     */
    protected function type_uuid(Fluent $column): string
    {
        if (version_compare($this->connection->get_server_version(), '10.7.0', '<')) {
            return 'char(36)';
        }
        return 'uuid';
    }
    /**
     * Create the column definition for a spatial Geometry type.
     */
    protected function type_geometry(Fluent $column): string
    {
        $subtype = $column->subtype ? strtolower($column->subtype) : null;
        if (!in_array($subtype, ['point', 'linestring', 'polygon', 'geometrycollection', 'multipoint', 'multilinestring', 'multipolygon'])) {
            $subtype = null;
        }
        return sprintf('%s%s', $subtype ?? 'geometry', $column->srid ? ' ref_system_id=' . $column->srid : '');
    }
    /**
     * Wrap the given JSON selector.
     *
     * @param  string  $value
     */
    protected function wrap_json_selector($value): string
    {
        [$field, $path] = $this->wrap_json_field_and_path($value);
        return 'json_value(' . $field . $path . ')';
    }
}