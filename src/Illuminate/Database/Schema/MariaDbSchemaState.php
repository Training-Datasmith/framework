<?php

declare (strict_types=1);
namespace Illuminate\Database\Schema;

class Maria_Db_Schema_State extends My_Sql_Schema_State
{
    /**
     * Load the given schema file into the database.
     *
     * @param  string  $path
     */
    public function load($path): void
    {
        $version_info = $this->detect_client_version();
        $command = 'mariadb ' . $this->connection_string($version_info) . ' --database="${:LARAVEL_LOAD_DATABASE}" < "${:LARAVEL_LOAD_PATH}"';
        $process = $this->make_process($command)->set_timeout(null);
        $process->must_run(null, array_merge($this->base_variables($this->connection->get_config()), ['LARAVEL_LOAD_PATH' => $path]));
    }
    /**
     * Get the base dump command arguments for MariaDB as a string.
     */
    protected function base_dump_command(): string
    {
        $version_info = $this->detect_client_version();
        $command = 'mariadb-dump ' . $this->connection_string($version_info) . ' --no-tablespaces --skip-add-locks --skip-comments --skip-set-charset --tz-utc';
        return $command . ' "${:LARAVEL_LOAD_DATABASE}"';
    }
}