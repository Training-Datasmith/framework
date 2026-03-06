<?php

namespace Illuminate\Database\Schema;

class MariaDbSchemaState extends MySqlSchemaState
{
    /**
     * Load the given schema file into the database.
     *
     * @param  string  $path
     */
    public function load($path): void
    {
        $versionInfo = $this->detectClientVersion();

        $command = 'mariadb '.$this->connectionString($versionInfo).' --database="${:LARAVEL_LOAD_DATABASE}" < "${:LARAVEL_LOAD_PATH}"';

        $process = $this->makeProcess($command)->setTimeout(null);

        $process->mustRun(null, array_merge($this->baseVariables($this->connection->getConfig()), [
            'LARAVEL_LOAD_PATH' => $path,
        ]));
    }

    /**
     * Get the base dump command arguments for MariaDB as a string.
     */
    protected function baseDumpCommand(): string
    {
        $versionInfo = $this->detectClientVersion();

        $command = 'mariadb-dump '.$this->connectionString($versionInfo).' --no-tablespaces --skip-add-locks --skip-comments --skip-set-charset --tz-utc';

        return $command.' "${:LARAVEL_LOAD_DATABASE}"';
    }
}
