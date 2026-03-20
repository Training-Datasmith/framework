<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema;

use Exception;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\Process_Failed_Exception;
use Symfony\Component\Process\Process;
class My_Sql_Schema_State extends Schema_State
{
    /**
     * Dump the database's schema into a file.
     *
     * @param  string  $path
     */
    public function dump(Connection $connection, $path): void
    {
        $this->execute_dump_process($this->make_process($this->base_dump_command() . ' --routines --result-file="${:LARAVEL_LOAD_PATH}" --no-data'), $this->output, array_merge($this->base_variables($this->connection->get_config()), ['LARAVEL_LOAD_PATH' => $path]));
        $this->remove_auto_incrementing_state($path);
        if ($this->has_migration_table()) {
            $this->append_migration_data($path);
        }
    }
    /**
     * Remove the auto-incrementing state from the given schema dump.
     *
     * @return void
     */
    protected function remove_auto_incrementing_state(string $path)
    {
        $this->files->put($path, preg_replace('/\s+AUTO_INCREMENT=[0-9]+/iu', '', $this->files->get($path)));
    }
    /**
     * Append the migration data to the schema dump.
     *
     * @return void
     */
    protected function append_migration_data(string $path)
    {
        $process = $this->execute_dump_process($this->make_process($this->base_dump_command() . ' ' . $this->get_migration_table() . ' --no-create-info --skip-extended-insert --skip-routines --compact --complete-insert'), null, array_merge($this->base_variables($this->connection->get_config()), []));
        $this->files->append($path, $process->get_output());
    }
    /**
     * Load the given schema file into the database.
     *
     * @param  string  $path
     */
    public function load($path): void
    {
        $version_info = $this->detect_client_version();
        $command = 'mysql ' . $this->connection_string($version_info) . ' --database="${:LARAVEL_LOAD_DATABASE}" < "${:LARAVEL_LOAD_PATH}"';
        $process = $this->make_process($command)->set_timeout(null);
        $process->must_run(null, array_merge($this->base_variables($this->connection->get_config()), ['LARAVEL_LOAD_PATH' => $path]));
    }
    /**
     * Get the base dump command arguments for MySQL as a string.
     */
    protected function base_dump_command(): string
    {
        $version_info = $this->detect_client_version();
        $command = 'mysqldump ' . $this->connection_string($version_info) . ' --no-tablespaces --skip-add-locks --skip-comments --skip-set-charset --tz-utc --column-statistics=0';
        if (!$this->connection->is_maria()) {
            $command .= ' --set-gtid-purged=OFF';
        }
        return $command . ' "${:LARAVEL_LOAD_DATABASE}"';
    }
    /**
     * Generate a basic connection string (--socket, --host, --port, --user, --password) for the database.
     *
     * @param  array{version: string, isMariaDb: bool}  $versionInfo
     */
    protected function connection_string(array $version_info): string
    {
        $value = ' --user="${:LARAVEL_LOAD_USER}" --password="${:LARAVEL_LOAD_PASSWORD}"';
        $config = $this->connection->get_config();
        $value .= $config['unix_socket'] ?? false ? ' --socket="${:LARAVEL_LOAD_SOCKET}"' : ' --host="${:LARAVEL_LOAD_HOST}" --port="${:LARAVEL_LOAD_PORT}"';
        /** @phpstan-ignore class.notFound */
        if (isset($config['options'][PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA])) {
            $value .= ' --ssl-ca="${:LARAVEL_LOAD_SSL_CA}"';
        }
        /** @phpstan-ignore class.notFound */
        if (isset($config['options'][PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CERT : \PDO::MYSQL_ATTR_SSL_CERT])) {
            $value .= ' --ssl-cert="${:LARAVEL_LOAD_SSL_CERT}"';
        }
        /** @phpstan-ignore class.notFound */
        if (isset($config['options'][PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_KEY : \PDO::MYSQL_ATTR_SSL_KEY])) {
            $value .= ' --ssl-key="${:LARAVEL_LOAD_SSL_KEY}"';
        }
        /** @phpstan-ignore class.notFound */
        $verify_cert_option = PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT : \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT;
        if (isset($config['options'][$verify_cert_option]) && $config['options'][$verify_cert_option] === false) {
            if (version_compare($version_info['version'], '5.7.11', '>=') && !$version_info['isMariaDb']) {
                $value .= ' --ssl-mode=DISABLED';
            } else {
                $value .= ' --ssl=off';
            }
        }
        return $value;
    }
    /**
     * Get the base variables for a dump / load command.
     */
    protected function base_variables(array $config): array
    {
        $config['host'] ??= '';
        return [
            'LARAVEL_LOAD_SOCKET' => $config['unix_socket'] ?? '',
            'LARAVEL_LOAD_HOST' => is_array($config['host']) ? $config['host'][0] : $config['host'],
            'LARAVEL_LOAD_PORT' => $config['port'] ?? '',
            'LARAVEL_LOAD_USER' => $config['username'],
            'LARAVEL_LOAD_PASSWORD' => $config['password'] ?? '',
            'LARAVEL_LOAD_DATABASE' => $config['database'],
            'LARAVEL_LOAD_SSL_CA' => $config['options'][PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA] ?? '',
            // @phpstan-ignore class.notFound
            'LARAVEL_LOAD_SSL_CERT' => $config['options'][PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CERT : \PDO::MYSQL_ATTR_SSL_CERT] ?? '',
            // @phpstan-ignore class.notFound
            'LARAVEL_LOAD_SSL_KEY' => $config['options'][PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_KEY : \PDO::MYSQL_ATTR_SSL_KEY] ?? '',
        ];
    }
    /**
     * Execute the given dump process.
     *
     * @param  callable  $output
     * @return \Symfony\Component\Process\Process
     */
    protected function execute_dump_process(Process $process, $output, array $variables, int $depth = 0)
    {
        if ($depth > 30) {
            throw new Exception('Dump execution exceeded maximum depth of 30.');
        }
        try {
            $process->set_timeout(null)->must_run($output, $variables);
        } catch (Exception $e) {
            if (Str::contains($e->get_message(), ['column-statistics', 'column_statistics'])) {
                return $this->execute_dump_process(Process::from_shell_command_line(str_replace(' --column-statistics=0', '', $process->get_command_line())), $output, $variables, $depth + 1);
            }
            if (str_contains($e->get_message(), 'set-gtid-purged')) {
                return $this->execute_dump_process(Process::from_shell_command_line(str_replace(' --set-gtid-purged=OFF', '', $process->get_command_line())), $output, $variables, $depth + 1);
            }
            throw $e;
        }
        return $process;
    }
    /**
     * Detect the MySQL client version.
     *
     * @return array{version: string, isMariaDb: bool}
     */
    protected function detect_client_version(): array
    {
        [$version, $is_maria_db] = ['8.0.0', false];
        try {
            $version_output = $this->make_process('mysql --version')->must_run()->get_output();
            if (preg_match('/(\d+\.\d+\.\d+)/', $version_output, $matches)) {
                $version = $matches[1];
            }
            $is_maria_db = stripos($version_output, 'mariadb') !== false;
        } catch (Process_Failed_Exception) {
        }
        return ['version' => $version, 'isMariaDb' => $is_maria_db];
    }
}