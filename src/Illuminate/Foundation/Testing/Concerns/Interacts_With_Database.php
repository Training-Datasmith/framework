<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing\Concerns;

use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\Query_Executed;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Constraints\Count_In_Database;
use Illuminate\Testing\Constraints\Has_In_Database;
use Illuminate\Testing\Constraints\Not_Soft_Deleted_In_Database;
use Illuminate\Testing\Constraints\Soft_Deleted_In_Database;
use Php_Unit\Framework\Constraint\Logical_Not as ReverseConstraint;
trait Interacts_With_Database
{
    /**
     * Assert that a given where condition exists in the database.
     *
     * @param  iterable<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>|string  $table
     * @param  array<string, mixed>  $data
     * @param  string|null  $connection
     * @return $this
     */
    protected function assert_database_has($table, array $data = [], $connection = null)
    {
        if (is_iterable($table)) {
            foreach ($table as $item) {
                $this->assert_database_has($item, $data, $connection);
            }
            return $this;
        }
        if ($table instanceof Model) {
            $data = [$table->get_key_name() => $table->get_key(), ...$data];
        }
        $this->assert_that($this->get_table($table), new Has_In_Database($this->get_connection($connection, $table), $data));
        return $this;
    }
    /**
     * Assert that a given where condition does not exist in the database.
     *
     * @param  iterable<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>|string  $table
     * @param  array<string, mixed>  $data
     * @param  string|null  $connection
     * @return $this
     */
    protected function assert_database_missing($table, array $data = [], $connection = null)
    {
        if (is_iterable($table)) {
            foreach ($table as $item) {
                $this->assert_database_missing($item, $data, $connection);
            }
            return $this;
        }
        if ($table instanceof Model) {
            $data = [$table->get_key_name() => $table->get_key(), ...$data];
        }
        $constraint = new Reverse_Constraint(new Has_In_Database($this->get_connection($connection, $table), $data));
        $this->assert_that($this->get_table($table), $constraint);
        return $this;
    }
    /**
     * Assert the count of table entries.
     *
     * @param  \Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>|string  $table
     * @param  string|null  $connection
     * @return $this
     */
    protected function assert_database_count($table, int $count, $connection = null)
    {
        $this->assert_that($this->get_table($table), new Count_In_Database($this->get_connection($connection, $table), $count));
        return $this;
    }
    /**
     * Assert that the given table has no entries.
     *
     * @param  \Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>|string  $table
     * @param  string|null  $connection
     * @return $this
     */
    protected function assert_database_empty($table, $connection = null)
    {
        $this->assert_that($this->get_table($table), new Count_In_Database($this->get_connection($connection, $table), 0));
        return $this;
    }
    /**
     * Assert the given record has been "soft deleted".
     *
     * @param  iterable<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>|string  $table
     * @param  array<string, mixed>  $data
     * @param  string|null  $connection
     * @param  string|null  $deletedAtColumn
     * @return $this
     */
    protected function assert_soft_deleted($table, array $data = [], $connection = null, $deleted_at_column = 'deleted_at')
    {
        if (is_iterable($table)) {
            foreach ($table as $item) {
                $this->assert_soft_deleted($item, $data, $connection);
            }
            return $this;
        }
        if ($this->is_soft_deletable_model($table)) {
            return $this->assert_soft_deleted($table->get_table(), array_merge($data, [$table->get_key_name() => $table->get_key()]), $table->get_connection_name(), $table->get_deleted_at_column());
        }
        $this->assert_that($this->get_table($table), new Soft_Deleted_In_Database($this->get_connection($connection, $table), $data, $this->get_deleted_at_column($table, $deleted_at_column)));
        return $this;
    }
    /**
     * Assert the given record has not been "soft deleted".
     *
     * @param  iterable<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>|string  $table
     * @param  array<string, mixed>  $data
     * @param  string|null  $connection
     * @param  string|null  $deletedAtColumn
     * @return $this
     */
    protected function assert_not_soft_deleted($table, array $data = [], $connection = null, $deleted_at_column = 'deleted_at')
    {
        if (is_iterable($table)) {
            foreach ($table as $item) {
                $this->assert_not_soft_deleted($item, $data, $connection);
            }
            return $this;
        }
        if ($this->is_soft_deletable_model($table)) {
            return $this->assert_not_soft_deleted($table->get_table(), array_merge($data, [$table->get_key_name() => $table->get_key()]), $table->get_connection_name(), $table->get_deleted_at_column());
        }
        $this->assert_that($this->get_table($table), new Not_Soft_Deleted_In_Database($this->get_connection($connection, $table), $data, $this->get_deleted_at_column($table, $deleted_at_column)));
        return $this;
    }
    /**
     * Assert the given model exists in the database.
     *
     * @param  iterable<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>|string  $model
     * @return $this
     */
    protected function assert_model_exists($model)
    {
        return $this->assert_database_has($model);
    }
    /**
     * Assert the given model does not exist in the database.
     *
     * @param  iterable<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>|string  $model
     * @return $this
     */
    protected function assert_model_missing($model)
    {
        return $this->assert_database_missing($model);
    }
    /**
     * Specify the number of database queries that should occur throughout the test.
     *
     * @param  int  $expected
     * @param  string|null  $connection
     * @return $this
     */
    public function expects_database_query_count($expected, $connection = null)
    {
        $connection_instance = $this->get_connection($connection);
        $actual = 0;
        $connection_instance->listen(function (Query_Executed $event) use (&$actual, $connection_instance, $connection): void {
            if (is_null($connection) || $connection_instance === $event->connection) {
                $actual++;
            }
        });
        $this->before_application_destroyed(function () use (&$actual, $expected, $connection_instance): void {
            $this->assert_same($expected, $actual, "Expected {$expected} database queries on the [{$connection_instance->get_name()}] connection. {$actual} occurred.");
        });
        return $this;
    }
    /**
     * Determine if the argument is a soft deletable model.
     *
     * @param  mixed  $model
     */
    protected function is_soft_deletable_model($model): bool
    {
        return $model instanceof Model && $model::is_soft_deletable();
    }
    /**
     * Cast a JSON string to a database compatible type.
     *
     * @param  array|object|string  $value
     * @param  string|null  $connection
     * @return \Illuminate\Contracts\Database\Query\Expression
     */
    public function cast_as_json($value, $connection = null): \Illuminate\Database\Query\Expression
    {
        if ($value instanceof Jsonable) {
            $value = $value->to_json();
        } elseif (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
        $db = DB::connection($connection);
        $value = $db->get_pdo()->quote($value);
        return $db->raw($db->get_query_grammar()->compile_json_value_cast($value));
    }
    /**
     * Get the database connection.
     *
     * @param  string|null  $connection
     * @param  \Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>|string|null  $table
     * @return \Illuminate\Database\Connection
     */
    protected function get_connection($connection = null, $table = null)
    {
        $database = $this->app->make('db');
        $connection = ($connection ?: $this->get_table_connection($table)) ?: $database->get_default_connection();
        return $database->connection($connection);
    }
    /**
     * Get the table name from the given model or string.
     *
     * @param  \Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>|string  $table
     * @return string
     */
    protected function get_table($table)
    {
        if ($table instanceof Model) {
            return $table->get_table();
        }
        return $this->new_model_for($table)?->get_table() ?: $table;
    }
    /**
     * Get the table connection specified in the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>|string  $table
     * @return string|null
     */
    protected function get_table_connection($table)
    {
        if ($table instanceof Model) {
            return $table->get_connection_name();
        }
        return $this->new_model_for($table)?->get_connection_name();
    }
    /**
     * Get the table column name used for soft deletes.
     *
     * @param  \Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>|string  $table
     * @param  string  $defaultColumnName
     * @return string
     */
    protected function get_deleted_at_column($table, $default_column_name = 'deleted_at')
    {
        return $this->new_model_for($table)?->get_deleted_at_column() ?: $default_column_name;
    }
    /**
     * Get the model entity from the given model or string.
     *
     * @param  \Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model>|string  $table
     */
    protected function new_model_for($table): ?\Illuminate\Database\Eloquent\Model
    {
        return is_subclass_of($table, Model::class) ? new $table() : null;
    }
    /**
     * Seed a given database connection.
     *
     * @param  list<string>|class-string<\Illuminate\Database\Seeder>|string  $class
     * @return $this
     */
    public function seed($class = 'Database\Seeders\DatabaseSeeder')
    {
        foreach (Arr::wrap($class) as $class) {
            $this->artisan('db:seed', ['--class' => $class, '--no-interaction' => true]);
        }
        return $this;
    }
}