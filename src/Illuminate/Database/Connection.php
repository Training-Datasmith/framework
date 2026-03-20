<?php

declare (strict_types=1);
namespace Illuminate\Database;

use Carbon\Carbon_Interval;
use Closure;
use DateTimeInterface;
use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\Query_Executed;
use Illuminate\Database\Events\Statement_Prepared;
use Illuminate\Database\Events\Transaction_Beginning;
use Illuminate\Database\Events\Transaction_Committed;
use Illuminate\Database\Events\Transaction_Committing;
use Illuminate\Database\Events\Transaction_Rolled_Back;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\Grammar as QueryGrammar;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Arr;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Interacts_With_Time;
use Illuminate\Support\Traits\Macroable;
use PDO;
use PDOStatement;
use RuntimeException;
class Connection implements Connection_Interface
{
    use Detects_Concurrency_Errors;
    use Detects_Lost_Connections;
    use Concerns\Manages_Transactions;
    use Interacts_With_Time;
    use Macroable;
    /**
     * The active PDO connection used for reads.
     *
     * @var \PDO|(\Closure(): \PDO)
     */
    protected $read_pdo;
    /**
     * The database connection configuration options for reading.
     *
     * @var array
     */
    protected $read_pdo_config = [];
    /**
     * The type of the connection.
     *
     * @var string|null
     */
    protected $read_write_type;
    /**
     * The reconnector instance for the connection.
     *
     * @var (callable(\Illuminate\Database\Connection): mixed)
     */
    protected $reconnector;
    /**
     * The query grammar implementation.
     *
     * @var \Illuminate\Database\Query\Grammars\Grammar
     */
    protected $query_grammar;
    /**
     * The schema grammar implementation.
     *
     * @var \Illuminate\Database\Schema\Grammars\Grammar
     */
    protected $schema_grammar;
    /**
     * The query post processor implementation.
     *
     * @var \Illuminate\Database\Query\Processors\Processor
     */
    protected $post_processor;
    /**
     * The event dispatcher instance.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher|null
     */
    protected $events;
    /**
     * The default fetch mode of the connection.
     *
     * @var int
     */
    protected $fetch_mode = PDO::FETCH_OBJ;
    /**
     * The number of active transactions.
     *
     * @var int
     */
    protected $transactions = 0;
    /**
     * The transaction manager instance.
     *
     * @var \Illuminate\Database\DatabaseTransactionsManager|null
     */
    protected $transactions_manager;
    /**
     * Indicates if changes have been made to the database.
     *
     * @var bool
     */
    protected $records_modified = false;
    /**
     * Indicates if the connection should use the "write" PDO connection.
     *
     * @var bool
     */
    protected $read_on_write_connection = false;
    /**
     * All of the queries run against the connection.
     *
     * @var array{query: string, bindings: array, time: float|null}[]
     */
    protected $query_log = [];
    /**
     * Indicates whether queries are being logged.
     *
     * @var bool
     */
    protected $logging_queries = false;
    /**
     * The duration of all executed queries in milliseconds.
     *
     * @var float
     */
    protected $total_query_duration = 0.0;
    /**
     * All of the registered query duration handlers.
     *
     * @var array{has_run: bool, handler: (callable(\Illuminate\Database\Connection, class-string<\Illuminate\Database\Events\QueryExecuted>): mixed)}[]
     */
    protected $query_duration_handlers = [];
    /**
     * Indicates if the connection is in a "dry run".
     *
     * @var bool
     */
    protected $pretending = false;
    /**
     * All of the callbacks that should be invoked before a transaction is started.
     *
     * @var \Closure[]
     */
    protected $before_starting_transaction = [];
    /**
     * All of the callbacks that should be invoked before a query is executed.
     *
     * @var (\Closure(string, array, \Illuminate\Database\Connection): mixed)[]
     */
    protected $before_executing_callbacks = [];
    /**
     * The connection resolvers.
     *
     * @var \Closure[]
     */
    protected static $resolvers = [];
    /**
     * The last retrieved PDO read / write type.
     *
     * @var null|'read'|'write'
     */
    protected $latest_pdo_type_retrieved;
    /**
     * Create a new database connection instance.
     *
     * @param  \PDO|(\Closure(): \PDO)  $pdo
     * @param  string  $database
     * @param  string  $tablePrefix
     */
    public function __construct(
        /**
         * The active PDO connection.
         */
        protected $pdo,
        /**
         * The name of the connected database.
         */
        protected $database = '',
        /**
         * The table prefix for the connection.
         */
        protected $table_prefix = '',
        /**
         * The database connection configuration options.
         */
        protected array $config = []
    )
    {
        // We need to initialize a query grammar and the query post processors
        // which are both very important parts of the database abstractions
        // so we initialize these to their default values while starting.
        $this->use_default_query_grammar();
        $this->use_default_post_processor();
    }
    /**
     * Set the query grammar to the default implementation.
     */
    public function use_default_query_grammar(): void
    {
        $this->query_grammar = $this->get_default_query_grammar();
    }
    /**
     * Get the default query grammar instance.
     */
    protected function get_default_query_grammar(): \Illuminate\Database\Query\Grammars\Grammar
    {
        return new Query_Grammar($this);
    }
    /**
     * Set the schema grammar to the default implementation.
     */
    public function use_default_schema_grammar(): void
    {
        $this->schema_grammar = $this->get_default_schema_grammar();
    }
    /**
     * Get the default schema grammar instance.
     *
     * @return \Illuminate\Database\Schema\Grammars\Grammar|null
     */
    protected function get_default_schema_grammar()
    {
    }
    /**
     * Set the query post processor to the default implementation.
     */
    public function use_default_post_processor(): void
    {
        $this->post_processor = $this->get_default_post_processor();
    }
    /**
     * Get the default post processor instance.
     */
    protected function get_default_post_processor(): \Illuminate\Database\Query\Processors\Processor
    {
        return new Processor();
    }
    /**
     * Get a schema builder instance for the connection.
     */
    public function get_schema_builder(): \Illuminate\Database\Schema\Builder
    {
        if (is_null($this->schema_grammar)) {
            $this->use_default_schema_grammar();
        }
        return new Schema_Builder($this);
    }
    /**
     * Begin a fluent query against a database table.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Contracts\Database\Query\Expression|\UnitEnum|string  $table
     * @param  string|null  $as
     * @return \Illuminate\Database\Query\Builder
     */
    public function table($table, $as = null)
    {
        return $this->query()->from(enum_value($table), $as);
    }
    /**
     * Get a new query builder instance.
     */
    public function query(): \Illuminate\Database\Query\Builder
    {
        return new Query_Builder($this, $this->get_query_grammar(), $this->get_post_processor());
    }
    /**
     * Run a select statement and return a single result.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     */
    public function select_one($query, $bindings = [], $use_read_pdo = true): mixed
    {
        $records = $this->select($query, $bindings, $use_read_pdo);
        return array_shift($records);
    }
    /**
     * Run a select statement and return the first column of the first row.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return mixed
     *
     * @throws \Illuminate\Database\MultipleColumnsSelectedException
     */
    public function scalar($query, $bindings = [], $use_read_pdo = true)
    {
        $record = $this->select_one($query, $bindings, $use_read_pdo);
        if (is_null($record)) {
            return null;
        }
        $record = (array) $record;
        if (count($record) > 1) {
            throw new Multiple_Columns_Selected_Exception();
        }
        return array_first($record);
    }
    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return array
     */
    public function select_from_write_connection($query, $bindings = [])
    {
        return $this->select($query, $bindings, false);
    }
    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $use_read_pdo = true)
    {
        return $this->run($query, $bindings, function ($query, array $bindings) use ($use_read_pdo): array {
            if ($this->pretending()) {
                return [];
            }
            // For select statements, we'll simply execute the query and return an array
            // of the database result set. Each element in the array will be a single
            // row from the database table, and will either be an array or objects.
            $statement = $this->prepared($this->get_pdo_for_select($use_read_pdo)->prepare($query));
            $this->bind_values($statement, $this->prepare_bindings($bindings));
            $statement->execute();
            return $statement->fetch_all();
        });
    }
    /**
     * Run a select statement against the database and returns all of the result sets.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function select_result_sets($query, $bindings = [], $use_read_pdo = true)
    {
        return $this->run($query, $bindings, function ($query, array $bindings) use ($use_read_pdo): array {
            if ($this->pretending()) {
                return [];
            }
            $statement = $this->prepared($this->get_pdo_for_select($use_read_pdo)->prepare($query));
            $this->bind_values($statement, $this->prepare_bindings($bindings));
            $statement->execute();
            $sets = [];
            do {
                $sets[] = $statement->fetch_all();
            } while ($statement->next_rowset());
            return $sets;
        });
    }
    /**
     * Run a select statement against the database and returns a generator.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return \Generator<int, \stdClass>
     */
    public function cursor($query, $bindings = [], $use_read_pdo = true)
    {
        $statement = $this->run($query, $bindings, function ($query, array $bindings) use ($use_read_pdo): array|\PDOStatement {
            if ($this->pretending()) {
                return [];
            }
            // First we will create a statement for the query. Then, we will set the fetch
            // mode and prepare the bindings for the query. Once that's done we will be
            // ready to execute the query against the database and return the cursor.
            $statement = $this->prepared($this->get_pdo_for_select($use_read_pdo)->prepare($query));
            $this->bind_values($statement, $this->prepare_bindings($bindings));
            // Next, we'll execute the query against the database and return the statement
            // so we can return the cursor. The cursor will use a PHP generator to give
            // back one row at a time without using a bunch of memory to render them.
            $statement->execute();
            return $statement;
        });
        while ($record = $statement->fetch()) {
            yield $record;
        }
    }
    /**
     * Configure the PDO prepared statement.
     */
    protected function prepared(PDOStatement $statement): PDOStatement
    {
        $statement->set_fetch_mode($this->fetch_mode);
        $this->event(new Statement_Prepared($this, $statement));
        return $statement;
    }
    /**
     * Get the PDO connection to use for a select query.
     *
     * @param  bool  $useReadPdo
     * @return \PDO
     */
    protected function get_pdo_for_select($use_read_pdo = true)
    {
        return $use_read_pdo ? $this->get_read_pdo() : $this->get_pdo();
    }
    /**
     * Run an insert statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public function insert($query, $bindings = [])
    {
        return $this->statement($query, $bindings);
    }
    /**
     * Run an update statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function update($query, $bindings = [])
    {
        return $this->affecting_statement($query, $bindings);
    }
    /**
     * Run a delete statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function delete($query, $bindings = [])
    {
        return $this->affecting_statement($query, $bindings);
    }
    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, array $bindings) {
            if ($this->pretending()) {
                return true;
            }
            $statement = $this->get_pdo()->prepare($query);
            $this->bind_values($statement, $this->prepare_bindings($bindings));
            $this->records_have_been_modified();
            return $statement->execute();
        });
    }
    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function affecting_statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, array $bindings) {
            if ($this->pretending()) {
                return 0;
            }
            // For update or delete statements, we want to get the number of rows affected
            // by the statement and return that back to the developer. We'll first need
            // to execute the statement and then we'll use PDO to fetch the affected.
            $statement = $this->get_pdo()->prepare($query);
            $this->bind_values($statement, $this->prepare_bindings($bindings));
            $statement->execute();
            $this->records_have_been_modified(($count = $statement->row_count()) > 0);
            return $count;
        });
    }
    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param  string  $query
     * @return bool
     */
    public function unprepared($query)
    {
        return $this->run($query, [], function ($query): bool {
            if ($this->pretending()) {
                return true;
            }
            $this->records_have_been_modified($change = $this->get_pdo()->exec($query) !== false);
            return $change;
        });
    }
    /**
     * Get the number of open connections for the database.
     *
     * @return int|null
     */
    public function thread_count()
    {
        $query = $this->get_query_grammar()->compile_thread_count();
        return $query ? $this->scalar($query) : null;
    }
    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param  (\Closure(\Illuminate\Database\Connection): mixed)  $callback
     * @return array{query: string, bindings: array, time: float|null}[]
     */
    public function pretend(Closure $callback)
    {
        return $this->with_fresh_query_log(function () use ($callback) {
            $this->pretending = true;
            try {
                // Basically to make the database connection "pretend", we will just return
                // the default values for all the query methods, then we will return an
                // array of queries that were "executed" within the Closure callback.
                $callback($this);
                return $this->query_log;
            } finally {
                $this->pretending = false;
            }
        });
    }
    /**
     * Execute the given callback without "pretending".
     *
     * @return mixed
     */
    public function without_pretending(Closure $callback)
    {
        if (!$this->pretending) {
            return $callback();
        }
        $this->pretending = false;
        try {
            return $callback();
        } finally {
            $this->pretending = true;
        }
    }
    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param  (\Closure(): array{query: string, bindings: array, time: float|null}[])  $callback
     * @return array{query: string, bindings: array, time: float|null}[]
     */
    protected function with_fresh_query_log($callback)
    {
        $logging_queries = $this->logging_queries;
        // First we will back up the value of the logging queries property and then
        // we'll be ready to run callbacks. This query log will also get cleared
        // so we will have a new log of all the queries that are executed now.
        $this->enable_query_log();
        $this->query_log = [];
        // Now we'll execute this callback and capture the result. Once it has been
        // executed we will restore the value of query logging and give back the
        // value of the callback so the original callers can have the results.
        $result = $callback();
        $this->logging_queries = $logging_queries;
        return $result;
    }
    /**
     * Bind values to their parameters in the given statement.
     *
     * @param  \PDOStatement  $statement
     * @param  array  $bindings
     */
    public function bind_values($statement, $bindings): void
    {
        foreach ($bindings as $key => $value) {
            $statement->bind_value(is_string($key) ? $key : $key + 1, $value, match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_resource($value) => PDO::PARAM_LOB,
                default => PDO::PARAM_STR,
            });
        }
    }
    /**
     * Prepare the query bindings for execution.
     */
    public function prepare_bindings(array $bindings): array
    {
        $grammar = $this->get_query_grammar();
        foreach ($bindings as $key => $value) {
            // We need to transform all instances of DateTimeInterface into the actual
            // date string. Each query grammar maintains its own date string format
            // so we'll just ask the grammar for the format to get from the date.
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format($grammar->get_date_format());
            } elseif (is_bool($value)) {
                $bindings[$key] = (int) $value;
            }
        }
        return $bindings;
    }
    /**
     * Run a SQL statement and log its execution context.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return mixed
     * @throws \Illuminate\Database\QueryException
     */
    protected function run($query, $bindings, Closure $callback)
    {
        foreach ($this->before_executing_callbacks as $before_executing_callback) {
            $before_executing_callback($query, $bindings, $this);
        }
        $this->reconnect_if_missing_connection();
        $start = microtime(true);
        // Here we will run this query. If an exception occurs we'll determine if it was
        // caused by a connection that has been lost. If that is the cause, we'll try
        // to re-establish connection and re-run the query with a fresh connection.
        try {
            $result = $this->run_query_callback($query, $bindings, $callback);
        } catch (Query_Exception $e) {
            $result = $this->handle_query_exception($e, $query, $bindings, $callback);
        }
        // Once we have run the query we will calculate the time that it took to run and
        // then log the query, bindings, and execution time so we will report them on
        // the event that the developer needs them. We'll log time in milliseconds.
        $this->log_query($query, $bindings, $this->get_elapsed_time($start));
        return $result;
    }
    /**
     * Run a SQL statement.
     *
     * @param  string  $query
     * @return mixed
     *
     * @throws \Illuminate\Database\QueryException
     */
    protected function run_query_callback($query, array $bindings, Closure $callback)
    {
        // To execute the statement, we'll simply call the callback, which will actually
        // run the SQL against the PDO connection. Then we can calculate the time it
        // took to execute and log the query SQL, bindings and time in our memory.
        try {
            return $callback($query, $bindings);
        } catch (Exception $e) {
            $exception_type = $this->is_unique_constraint_error($e) ? Unique_Constraint_Violation_Exception::class : Query_Exception::class;
            throw new $exception_type($this->get_name_with_read_write_type(), $query, $this->prepare_bindings($bindings), $e, $this->get_connection_details(), $this->latest_read_write_type_used());
        }
    }
    /**
     * Determine if the given database exception was caused by a unique constraint violation.
     */
    protected function is_unique_constraint_error(Exception $exception): bool
    {
        return false;
    }
    /**
     * Log a query in the connection's query log.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  float|null  $time
     */
    public function log_query($query, $bindings, $time = null): void
    {
        $this->total_query_duration += $time ?? 0.0;
        $read_write_type = $this->latest_read_write_type_used();
        $this->event(new Query_Executed($query, $bindings, $time, $this, $read_write_type));
        $query = $this->pretending === true ? $this->query_grammar?->substitute_bindings_into_raw_sql($query, $bindings) ?? $query : $query;
        if ($this->logging_queries) {
            $this->query_log[] = compact('query', 'bindings', 'time', 'readWriteType');
        }
    }
    /**
     * Get the elapsed time in milliseconds since a given starting point.
     *
     * @param  float  $start
     */
    protected function get_elapsed_time($start): float
    {
        return round((microtime(true) - $start) * 1000, 2);
    }
    /**
     * Register a callback to be invoked when the connection queries for longer than a given amount of time.
     *
     * @param  \DateTimeInterface|\Carbon\CarbonInterval|float|int  $threshold
     * @param  (callable(\Illuminate\Database\Connection, \Illuminate\Database\Events\QueryExecuted): mixed)  $handler
     */
    public function when_querying_for_longer_than($threshold, $handler): void
    {
        $threshold = $threshold instanceof DateTimeInterface ? $this->seconds_until($threshold) * 1000 : $threshold;
        $threshold = $threshold instanceof Carbon_Interval ? $threshold->total_milliseconds : $threshold;
        $this->query_duration_handlers[] = ['has_run' => false, 'handler' => $handler];
        $key = count($this->query_duration_handlers) - 1;
        $this->listen(function ($event) use ($threshold, $handler, $key): void {
            if (!$this->query_duration_handlers[$key]['has_run'] && $this->total_query_duration() > $threshold) {
                $handler($this, $event);
                $this->query_duration_handlers[$key]['has_run'] = true;
            }
        });
    }
    /**
     * Allow all the query duration handlers to run again, even if they have already run.
     */
    public function allow_query_duration_handlers_to_run_again(): void
    {
        foreach ($this->query_duration_handlers as $key => $query_duration_handler) {
            $this->query_duration_handlers[$key]['has_run'] = false;
        }
    }
    /**
     * Get the duration of all run queries in milliseconds.
     *
     * @return float
     */
    public function total_query_duration()
    {
        return $this->total_query_duration;
    }
    /**
     * Reset the duration of all run queries.
     */
    public function reset_total_query_duration(): void
    {
        $this->total_query_duration = 0.0;
    }
    /**
     * Handle a query exception.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return mixed
     *
     * @throws \Illuminate\Database\QueryException
     */
    protected function handle_query_exception(Query_Exception $e, $query, $bindings, Closure $callback)
    {
        if ($this->transactions >= 1) {
            throw $e;
        }
        return $this->try_again_if_caused_by_lost_connection($e, $query, $bindings, $callback);
    }
    /**
     * Handle a query exception that occurred during query execution.
     *
     * @param  string  $query
     * @return mixed
     * @throws \Illuminate\Database\QueryException
     */
    protected function try_again_if_caused_by_lost_connection(Query_Exception $e, $query, array $bindings, Closure $callback)
    {
        if ($this->caused_by_lost_connection($e->get_previous())) {
            $this->reconnect();
            return $this->run_query_callback($query, $bindings, $callback);
        }
        throw $e;
    }
    /**
     * Reconnect to the database.
     *
     * @return mixed|false
     *
     * @throws \Illuminate\Database\LostConnectionException
     */
    public function reconnect(): mixed
    {
        if (is_callable($this->reconnector)) {
            return call_user_func($this->reconnector, $this);
        }
        throw new Lost_Connection_Exception('Lost connection and no reconnector available.');
    }
    /**
     * Reconnect to the database if a PDO connection is missing.
     */
    public function reconnect_if_missing_connection(): void
    {
        if (is_null($this->pdo)) {
            $this->reconnect();
        }
    }
    /**
     * Disconnect from the underlying PDO connection.
     */
    public function disconnect(): void
    {
        $this->set_pdo(null)->set_read_pdo(null);
    }
    /**
     * Register a hook to be run just before a database transaction is started.
     *
     * @return $this
     */
    public function before_starting_transaction(Closure $callback): static
    {
        $this->before_starting_transaction[] = $callback;
        return $this;
    }
    /**
     * Register a hook to be run just before a database query is executed.
     *
     * @return $this
     */
    public function before_executing(Closure $callback): static
    {
        $this->before_executing_callbacks[] = $callback;
        return $this;
    }
    /**
     * Register a database query listener with the connection.
     *
     * @param  \Closure(\Illuminate\Database\Events\QueryExecuted)  $callback
     */
    public function listen(Closure $callback): void
    {
        $this->events?->listen(Events\Query_Executed::class, $callback);
    }
    /**
     * Fire an event for this connection.
     *
     * @param  string  $event
     * @return array|null
     */
    protected function fire_connection_event($event)
    {
        return $this->events?->dispatch(match ($event) {
            'beganTransaction' => new Transaction_Beginning($this),
            'committed' => new Transaction_Committed($this),
            'committing' => new Transaction_Committing($this),
            'rollingBack' => new Transaction_Rolled_Back($this),
            default => null,
        });
    }
    /**
     * Fire the given event if possible.
     *
     * @param  mixed  $event
     * @return void
     */
    protected function event($event)
    {
        $this->events?->dispatch($event);
    }
    /**
     * Get a new raw query expression.
     *
     * @param  mixed  $value
     * @return \Illuminate\Contracts\Database\Query\Expression
     */
    public function raw($value): \Illuminate\Database\Query\Expression
    {
        return new Expression($value);
    }
    /**
     * Escape a value for safe SQL embedding.
     *
     * @param  string|float|int|bool|null  $value
     * @param  bool  $binary
     * @return string
     *
     * @throws \RuntimeException
     */
    public function escape($value, $binary = false)
    {
        if ($value === null) {
            return 'null';
        }
        if ($binary) {
            return $this->escape_binary($value);
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $this->escape_bool($value);
        }
        if (is_array($value)) {
            throw new RuntimeException('The database connection does not support escaping arrays.');
        }
        if (str_contains($value, "\x00")) {
            throw new RuntimeException('Strings with null bytes cannot be escaped. Use the binary escape option.');
        }
        if (preg_match('//u', $value) === false) {
            throw new RuntimeException('Strings with invalid UTF-8 byte sequences cannot be escaped.');
        }
        return $this->escape_string($value);
    }
    /**
     * Escape a string value for safe SQL embedding.
     *
     * @param  string  $value
     * @return string
     */
    protected function escape_string($value): string|false
    {
        return $this->get_read_pdo()->quote($value);
    }
    /**
     * Escape a boolean value for safe SQL embedding.
     *
     * @param  bool  $value
     */
    protected function escape_bool($value): string
    {
        return $value ? '1' : '0';
    }
    /**
     * Escape a binary value for safe SQL embedding.
     *
     * @param  string  $value
     *
     * @throws \RuntimeException
     */
    protected function escape_binary($value): never
    {
        throw new RuntimeException('The database connection does not support escaping binary values.');
    }
    /**
     * Determine if the database connection has modified any database records.
     *
     * @return bool
     */
    public function has_modified_records()
    {
        return $this->records_modified;
    }
    /**
     * Indicate if any records have been modified.
     *
     * @param  bool  $value
     */
    public function records_have_been_modified(array|int|float|string|bool|null $value = true): void
    {
        if (!$this->records_modified) {
            $this->records_modified = $value;
        }
    }
    /**
     * Set the record modification state.
     *
     * @return $this
     */
    public function set_record_modification_state(bool $value): static
    {
        $this->records_modified = $value;
        return $this;
    }
    /**
     * Reset the record modification state.
     */
    public function forget_record_modification_state(): void
    {
        $this->records_modified = false;
    }
    /**
     * Indicate that the connection should use the write PDO connection for reads.
     *
     * @param  bool  $value
     * @return $this
     */
    public function use_write_connection_when_reading($value = true): static
    {
        $this->read_on_write_connection = $value;
        return $this;
    }
    /**
     * Get the current PDO connection.
     *
     * @return \PDO
     */
    public function get_pdo()
    {
        $this->latest_pdo_type_retrieved = 'write';
        if ($this->pdo instanceof Closure) {
            return $this->pdo = call_user_func($this->pdo);
        }
        return $this->pdo;
    }
    /**
     * Get the current PDO connection parameter without executing any reconnect logic.
     *
     * @return \PDO|\Closure|null
     */
    public function get_raw_pdo()
    {
        return $this->pdo;
    }
    /**
     * Get the current PDO connection used for reading.
     *
     * @return \PDO
     */
    public function get_read_pdo()
    {
        if ($this->transactions > 0) {
            return $this->get_pdo();
        }
        if ($this->read_on_write_connection || $this->records_modified && $this->get_config('sticky')) {
            return $this->get_pdo();
        }
        $this->latest_pdo_type_retrieved = 'read';
        if ($this->read_pdo instanceof Closure) {
            return $this->read_pdo = call_user_func($this->read_pdo);
        }
        return $this->read_pdo ?: $this->get_pdo();
    }
    /**
     * Get the current read PDO connection parameter without executing any reconnect logic.
     *
     * @return \PDO|\Closure|null
     */
    public function get_raw_read_pdo()
    {
        return $this->read_pdo;
    }
    /**
     * Set the PDO connection.
     *
     * @param  \PDO|\Closure|null  $pdo
     * @return $this
     */
    public function set_pdo($pdo): static
    {
        $this->transactions = 0;
        $this->pdo = $pdo;
        return $this;
    }
    /**
     * Set the PDO connection used for reading.
     *
     * @param  \PDO|\Closure|null  $pdo
     * @return $this
     */
    public function set_read_pdo($pdo): static
    {
        $this->read_pdo = $pdo;
        return $this;
    }
    /**
     * Set the read PDO connection configuration.
     *
     * @return $this
     */
    public function set_read_pdo_config(array $config): static
    {
        $this->read_pdo_config = $config;
        return $this;
    }
    /**
     * Set the reconnect instance on the connection.
     *
     * @param  (callable(\Illuminate\Database\Connection): mixed)  $reconnector
     * @return $this
     */
    public function set_reconnector(callable $reconnector): static
    {
        $this->reconnector = $reconnector;
        return $this;
    }
    /**
     * Get the database connection name.
     *
     * @return string|null
     */
    public function get_name()
    {
        return $this->get_config('name');
    }
    /**
     * Get the database connection with its read / write type.
     *
     * @return string|null
     */
    public function get_name_with_read_write_type()
    {
        $name = $this->get_name() . ($this->read_write_type ? '::' . $this->read_write_type : '');
        return empty($name) ? null : $name;
    }
    /**
     * Get an option from the configuration options.
     *
     * @param  string|null  $option
     * @return mixed
     */
    public function get_config($option = null)
    {
        return Arr::get($this->config, $option);
    }
    /**
     * Get the basic connection information as an array for debugging.
     */
    protected function get_connection_details(): array
    {
        $config = $this->latest_read_write_type_used() === 'read' ? $this->read_pdo_config : $this->config;
        return ['driver' => $this->get_driver_name(), 'name' => $this->get_name_with_read_write_type(), 'host' => $config['host'] ?? null, 'port' => $config['port'] ?? null, 'database' => $config['database'] ?? null, 'unix_socket' => $config['unix_socket'] ?? null];
    }
    /**
     * Get the PDO driver name.
     *
     * @return string
     */
    public function get_driver_name()
    {
        return $this->get_config('driver');
    }
    /**
     * Get a human-readable name for the given connection driver.
     *
     * @return string
     */
    public function get_driver_title()
    {
        return $this->get_driver_name();
    }
    /**
     * Get the query grammar used by the connection.
     *
     * @return \Illuminate\Database\Query\Grammars\Grammar
     */
    public function get_query_grammar()
    {
        return $this->query_grammar;
    }
    /**
     * Set the query grammar used by the connection.
     *
     * @return $this
     */
    public function set_query_grammar(Query\Grammars\Grammar $grammar): static
    {
        $this->query_grammar = $grammar;
        return $this;
    }
    /**
     * Get the schema grammar used by the connection.
     *
     * @return \Illuminate\Database\Schema\Grammars\Grammar
     */
    public function get_schema_grammar()
    {
        return $this->schema_grammar;
    }
    /**
     * Set the schema grammar used by the connection.
     *
     * @return $this
     */
    public function set_schema_grammar(Schema\Grammars\Grammar $grammar): static
    {
        $this->schema_grammar = $grammar;
        return $this;
    }
    /**
     * Get the query post processor used by the connection.
     *
     * @return \Illuminate\Database\Query\Processors\Processor
     */
    public function get_post_processor()
    {
        return $this->post_processor;
    }
    /**
     * Set the query post processor used by the connection.
     *
     * @return $this
     */
    public function set_post_processor(Processor $processor): static
    {
        $this->post_processor = $processor;
        return $this;
    }
    /**
     * Get the event dispatcher used by the connection.
     *
     * @return \Illuminate\Contracts\Events\Dispatcher|null
     */
    public function get_event_dispatcher()
    {
        return $this->events;
    }
    /**
     * Set the event dispatcher instance on the connection.
     *
     * @return $this
     */
    public function set_event_dispatcher(Dispatcher $events): static
    {
        $this->events = $events;
        return $this;
    }
    /**
     * Unset the event dispatcher for this connection.
     */
    public function unset_event_dispatcher(): void
    {
        $this->events = null;
    }
    /**
     * Run the statement to start a new transaction.
     *
     * @return void
     */
    protected function execute_begin_transaction_statement()
    {
        $this->get_pdo()->begin_transaction();
    }
    /**
     * Set the transaction manager instance on the connection.
     *
     * @param  \Illuminate\Database\DatabaseTransactionsManager  $manager
     * @return $this
     */
    public function set_transaction_manager($manager): static
    {
        $this->transactions_manager = $manager;
        return $this;
    }
    /**
     * Unset the transaction manager for this connection.
     */
    public function unset_transaction_manager(): void
    {
        $this->transactions_manager = null;
    }
    /**
     * Determine if the connection is in a "dry run".
     */
    public function pretending(): bool
    {
        return $this->pretending === true;
    }
    /**
     * Get the connection query log.
     *
     * @return array{query: string, bindings: array, time: float|null}[]
     */
    public function get_query_log()
    {
        return $this->query_log;
    }
    /**
     * Get the connection query log with embedded bindings.
     */
    public function get_raw_query_log(): array
    {
        return array_map(fn(array $log): array => ['raw_query' => $this->query_grammar->substitute_bindings_into_raw_sql($log['query'], $this->prepare_bindings($log['bindings'])), 'time' => $log['time']], $this->get_query_log());
    }
    /**
     * Clear the query log.
     */
    public function flush_query_log(): void
    {
        $this->query_log = [];
    }
    /**
     * Enable the query log on the connection.
     */
    public function enable_query_log(): void
    {
        $this->logging_queries = true;
    }
    /**
     * Disable the query log on the connection.
     */
    public function disable_query_log(): void
    {
        $this->logging_queries = false;
    }
    /**
     * Determine whether we're logging queries.
     *
     * @return bool
     */
    public function logging()
    {
        return $this->logging_queries;
    }
    /**
     * Get the name of the connected database.
     *
     * @return string
     */
    public function get_database_name()
    {
        return $this->database;
    }
    /**
     * Set the name of the connected database.
     *
     * @param  string  $database
     * @return $this
     */
    public function set_database_name($database): static
    {
        $this->database = $database;
        return $this;
    }
    /**
     * Set the read / write type of the connection.
     *
     * @param  string|null  $readWriteType
     * @return $this
     */
    public function set_read_write_type($read_write_type): static
    {
        $this->read_write_type = $read_write_type;
        return $this;
    }
    /**
     * Retrieve the latest read / write type used.
     *
     * @return 'read'|'write'|null
     */
    protected function latest_read_write_type_used()
    {
        return $this->read_write_type ?? $this->latest_pdo_type_retrieved;
    }
    /**
     * Get the table prefix for the connection.
     *
     * @return string
     */
    public function get_table_prefix()
    {
        return $this->table_prefix;
    }
    /**
     * Set the table prefix in use by the connection.
     *
     * @param  string  $prefix
     * @return $this
     */
    public function set_table_prefix($prefix): static
    {
        $this->table_prefix = $prefix;
        return $this;
    }
    /**
     * Execute the given callback without table prefix.
     */
    public function without_table_prefix(Closure $callback): mixed
    {
        $table_prefix = $this->get_table_prefix();
        $this->set_table_prefix('');
        try {
            return $callback($this);
        } finally {
            $this->set_table_prefix($table_prefix);
        }
    }
    /**
     * Get the server version for the connection.
     */
    public function get_server_version(): string
    {
        return $this->get_pdo()->get_attribute(PDO::ATTR_SERVER_VERSION);
    }
    /**
     * Register a connection resolver.
     *
     * @param  string  $driver
     */
    public static function resolver_for($driver, Closure $callback): void
    {
        static::$resolvers[$driver] = $callback;
    }
    /**
     * Get the connection resolver for the given driver.
     *
     * @param  string  $driver
     * @return \Closure|null
     */
    public static function get_resolver($driver)
    {
        return static::$resolvers[$driver] ?? null;
    }
    /**
     * Prepare the instance for cloning.
     */
    public function __clone()
    {
        // When cloning, re-initialize grammars to reference cloned connection...
        $this->use_default_query_grammar();
        if (!is_null($this->schema_grammar)) {
            $this->use_default_schema_grammar();
        }
    }
}