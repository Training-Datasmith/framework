<?php

declare (strict_types=1);
namespace Illuminate\Database\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Configuration_Url_Parser;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Process\Exception\Process_Failed_Exception;
use Symfony\Component\Process\Process;
use UnexpectedValueException;
#[As_Command(name: 'db')]
class Db_Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db {connection? : The database connection that should be used}
               {--read : Connect to the read connection}
               {--write : Connect to the write connection}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start a new database CLI session';
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = $this->get_connection();
        if (!isset($connection['host']) && $connection['driver'] !== 'sqlite') {
            $this->components->error('No host specified for this database connection.');
            $this->line('  Use the <options=bold>[--read]</> and <options=bold>[--write]</> options to specify a read or write connection.');
            $this->new_line();
            return Command::FAILURE;
        }
        try {
            (new Process(array_merge([$command = $this->get_command($connection)], $this->command_arguments($connection)), null, $this->command_environment($connection)))->set_timeout(null)->set_tty(true)->must_run(function ($type, string|iterable $buffer): void {
                $this->output->write($buffer);
            });
        } catch (Process_Failed_Exception $e) {
            throw_unless($e->get_process()->get_exit_code() === 127, $e);
            $this->error("{$command} not found in path.");
            return Command::FAILURE;
        }
        return 0;
    }
    /**
     * Get the database connection configuration.
     *
     * @return array
     *
     * @throws \UnexpectedValueException
     */
    public function get_connection()
    {
        $connection = $this->laravel['config']['database.connections.' . (($db = $this->argument('connection')) ?? $this->laravel['config']['database.default'])];
        if (empty($connection)) {
            throw new UnexpectedValueException("Invalid database connection [{$db}].");
        }
        if (!empty($connection['url'])) {
            $connection = (new Configuration_Url_Parser())->parse_configuration($connection);
        }
        if ($this->option('read')) {
            if (is_array($connection['read']['host'])) {
                $connection['read']['host'] = $connection['read']['host'][0];
            }
            $connection = array_merge($connection, $connection['read']);
        } elseif ($this->option('write')) {
            if (is_array($connection['write']['host'])) {
                $connection['write']['host'] = $connection['write']['host'][0];
            }
            $connection = array_merge($connection, $connection['write']);
        }
        return $connection;
    }
    /**
     * Get the arguments for the database client command.
     *
     * @return array
     */
    public function command_arguments(array $connection)
    {
        $driver = ucfirst((string) $connection['driver']);
        return $this->{"get{$driver}Arguments"}($connection);
    }
    /**
     * Get the environment variables for the database client command.
     *
     * @return array|null
     */
    public function command_environment(array $connection)
    {
        $driver = ucfirst((string) $connection['driver']);
        if (method_exists($this, "get{$driver}Environment")) {
            return $this->{"get{$driver}Environment"}($connection);
        }
        return null;
    }
    /**
     * Get the database client command to run.
     */
    public function get_command(array $connection): string
    {
        return ['mysql' => 'mysql', 'mariadb' => 'mariadb', 'pgsql' => 'psql', 'sqlite' => 'sqlite3', 'sqlsrv' => 'sqlcmd'][$connection['driver']];
    }
    /**
     * Get the arguments for the MySQL CLI.
     */
    protected function get_mysql_arguments(array $connection): array
    {
        $optional_arguments = ['password' => '--password=' . $connection['password'], 'unix_socket' => '--socket=' . ($connection['unix_socket'] ?? ''), 'charset' => '--default-character-set=' . ($connection['charset'] ?? '')];
        if (!$connection['password']) {
            unset($optional_arguments['password']);
        }
        return array_merge(['--host=' . $connection['host'], '--port=' . $connection['port'], '--user=' . $connection['username']], $this->get_optional_arguments($optional_arguments, $connection), [$connection['database']]);
    }
    /**
     * Get the arguments for the MariaDB CLI.
     */
    protected function get_maria_db_arguments(array $connection): array
    {
        return $this->get_mysql_arguments($connection);
    }
    /**
     * Get the arguments for the Postgres CLI.
     */
    protected function get_pgsql_arguments(array $connection): array
    {
        return [$connection['database']];
    }
    /**
     * Get the arguments for the SQLite CLI.
     */
    protected function get_sqlite_arguments(array $connection): array
    {
        return [$connection['database']];
    }
    /**
     * Get the arguments for the SQL Server CLI.
     */
    protected function get_sqlsrv_arguments(array $connection): array
    {
        return array_merge(...$this->get_optional_arguments(['database' => ['-d', $connection['database']], 'username' => ['-U', $connection['username']], 'password' => ['-P', $connection['password']], 'host' => ['-S', 'tcp:' . $connection['host'] . ($connection['port'] ? ',' . $connection['port'] : '')], 'trust_server_certificate' => ['-C']], $connection));
    }
    /**
     * Get the environment variables for the Postgres CLI.
     */
    protected function get_pgsql_environment(array $connection): array
    {
        return array_merge(...$this->get_optional_arguments(['username' => ['PGUSER' => $connection['username']], 'host' => ['PGHOST' => $connection['host']], 'port' => ['PGPORT' => $connection['port']], 'password' => ['PGPASSWORD' => $connection['password']]], $connection));
    }
    /**
     * Get the optional arguments based on the connection configuration.
     */
    protected function get_optional_arguments(array $args, array $connection): array
    {
        return array_values(array_filter($args, fn($key): bool => !empty($connection[$key]), ARRAY_FILTER_USE_KEY));
    }
}