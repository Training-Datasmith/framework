<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema;

use Illuminate\Support\Fluent;
/**
 * @method ForeignKeyDefinition deferrable(bool $value = true) Set the foreign key as deferrable (PostgreSQL)
 * @method ForeignKeyDefinition initiallyImmediate(bool $value = true) Set the default time to check the constraint (PostgreSQL)
 * @method ForeignKeyDefinition lock(('none'|'shared'|'default'|'exclusive') $value) Specify the DDL lock mode for the foreign key operation (MySQL)
 * @method ForeignKeyDefinition on(string $table) Specify the referenced table
 * @method ForeignKeyDefinition onDelete(('cascade'|'restrict'|'set null'|'no action') $action) Add an ON DELETE action
 * @method ForeignKeyDefinition onUpdate(('cascade'|'restrict'|'set null'|'no action') $action) Add an ON UPDATE action
 * @method ForeignKeyDefinition references(string|string[] $columns) Specify the referenced column(s)
 */
class Foreign_Key_Definition extends Fluent
{
    /**
     * Indicate that updates should cascade.
     *
     * @return $this
     */
    public function cascade_on_update()
    {
        return $this->on_update('cascade');
    }
    /**
     * Indicate that updates should be restricted.
     *
     * @return $this
     */
    public function restrict_on_update()
    {
        return $this->on_update('restrict');
    }
    /**
     * Indicate that updates should set the foreign key value to null.
     *
     * @return $this
     */
    public function null_on_update()
    {
        return $this->on_update('set null');
    }
    /**
     * Indicate that updates should have "no action".
     *
     * @return $this
     */
    public function no_action_on_update()
    {
        return $this->on_update('no action');
    }
    /**
     * Indicate that deletes should cascade.
     *
     * @return $this
     */
    public function cascade_on_delete()
    {
        return $this->on_delete('cascade');
    }
    /**
     * Indicate that deletes should be restricted.
     *
     * @return $this
     */
    public function restrict_on_delete()
    {
        return $this->on_delete('restrict');
    }
    /**
     * Indicate that deletes should set the foreign key value to null.
     *
     * @return $this
     */
    public function null_on_delete()
    {
        return $this->on_delete('set null');
    }
    /**
     * Indicate that deletes should have "no action".
     *
     * @return $this
     */
    public function no_action_on_delete()
    {
        return $this->on_delete('no action');
    }
}