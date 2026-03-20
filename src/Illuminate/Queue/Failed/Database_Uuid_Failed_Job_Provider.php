<?php

declare(strict_types=1);

namespace Illuminate\Queue\Failed;

use DateTimeInterface;
use Illuminate\Support\Facades\Date;

class DatabaseUuidFailedJobProvider implements CountableFailedJobProvider, FailedJobProviderInterface, PrunableFailedJobProvider
{
    /**
     * Create a new database failed job provider.
     *
     * @param  string  $database
     * @param  string  $table
     */
    public function __construct(
        /**
         * The connection resolver implementation.
         */
        protected \Illuminate\Database\ConnectionResolverInterface $resolver,
        /**
         * The database connection name.
         */
        protected $database,
        /**
         * The database table.
         */
        protected $table
    ) {
    }

    /**
     * Log a failed job into storage.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  string  $payload
     * @param  \Throwable  $exception
     * @return string|null
     */
    public function log($connection, $queue, $payload, $exception)
    {
        $this->getTable()->insert([
            'uuid' => $uuid = json_decode($payload, true)['uuid'],
            'connection' => $connection,
            'queue' => $queue,
            'payload' => $payload,
            'exception' => (string) mb_convert_encoding($exception, 'UTF-8'),
            'failed_at' => Date::now(),
        ]);

        return $uuid;
    }

    /**
     * Get the IDs of all of the failed jobs.
     *
     * @param  string|null  $queue
     * @return array
     */
    public function ids($queue = null)
    {
        return $this->getTable()
            ->when(! is_null($queue), fn ($query) => $query->where('queue', $queue))
            ->orderBy('id', 'desc')
            ->pluck('uuid')
            ->all();
    }

    /**
     * Get a list of all of the failed jobs.
     *
     * @return array
     */
    public function all()
    {
        return $this->getTable()->orderBy('id', 'desc')->get()->map(function ($record): \stdClass {
            $record->id = $record->uuid;
            unset($record->uuid);

            return $record;
        })->all();
    }

    /**
     * Get a single failed job.
     *
     * @param  mixed  $id
     * @return object|null
     */
    public function find($id)
    {
        if ($record = $this->getTable()->where('uuid', $id)->first()) {
            $record->id = $record->uuid;
            unset($record->uuid);
        }

        return $record;
    }

    /**
     * Delete a single failed job from storage.
     *
     * @param  mixed  $id
     */
    public function forget($id): bool
    {
        return $this->getTable()->where('uuid', $id)->delete() > 0;
    }

    /**
     * Flush all of the failed jobs from storage.
     *
     * @param  int|null  $hours
     */
    public function flush($hours = null): void
    {
        $this->getTable()->when($hours, function ($query, $hours): void {
            $query->where('failed_at', '<=', Date::now()->subHours($hours));
        })->delete();
    }

    /**
     * Prune all of the entries older than the given date.
     *
     * @return int
     */
    public function prune(DateTimeInterface $before): int|float
    {
        $query = $this->getTable()->where('failed_at', '<', $before);

        $totalDeleted = 0;

        do {
            $deleted = $query->limit(1000)->delete();

            $totalDeleted += $deleted;
        } while ($deleted !== 0);

        return $totalDeleted;
    }

    /**
     * Count the failed jobs.
     *
     * @param  string|null  $connection
     * @param  string|null  $queue
     * @return int
     */
    public function count($connection = null, $queue = null)
    {
        return $this->getTable()
            ->when($connection, fn ($builder) => $builder->whereConnection($connection))
            ->when($queue, fn ($builder) => $builder->whereQueue($queue))
            ->count();
    }

    /**
     * Get a new query builder instance for the table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function getTable()
    {
        return $this->resolver->connection($this->database)->table($this->table);
    }
}
