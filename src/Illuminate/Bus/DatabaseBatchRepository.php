<?php

declare (strict_types=1);
namespace Illuminate\Bus;

use Carbon\Carbon_Immutable;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\Postgres_Connection;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
use Throwable;
class Database_Batch_Repository implements Prunable_Batch_Repository
{
    /**
     * Create a new batch repository instance.
     */
    public function __construct(
        /**
         * The batch factory instance.
         */
        protected \Illuminate\Bus\Batch_Factory $factory,
        /**
         * The database connection instance.
         */
        protected \Illuminate\Database\Connection $connection,
        /**
         * The database table to use to store batch information.
         */
        protected string $table
    )
    {
    }
    /**
     * Retrieve a list of batches.
     *
     * @param  int  $limit
     * @param  mixed  $before
     * @return \Illuminate\Bus\Batch[]
     */
    public function get($limit = 50, $before = null)
    {
        return $this->connection->table($this->table)->order_by_desc('id')->limit($limit)->when($before, fn($q) => $q->where('id', '<', $before))->get()->map(fn($batch) => $this->to_batch($batch))->all();
    }
    /**
     * Retrieve information about an existing batch.
     *
     * @return \Illuminate\Bus\Batch|null
     */
    public function find(string $batch_id)
    {
        $batch = $this->connection->table($this->table)->use_write_pdo()->where('id', $batch_id)->first();
        if ($batch) {
            return $this->to_batch($batch);
        }
    }
    /**
     * Store a new pending batch.
     *
     * @return \Illuminate\Bus\Batch
     */
    public function store(Pending_Batch $batch)
    {
        $id = (string) Str::ordered_uuid();
        $this->connection->table($this->table)->insert(['id' => $id, 'name' => $batch->name, 'total_jobs' => 0, 'pending_jobs' => 0, 'failed_jobs' => 0, 'failed_job_ids' => '[]', 'options' => $this->serialize($batch->options), 'created_at' => time(), 'cancelled_at' => null, 'finished_at' => null]);
        return $this->find($id);
    }
    /**
     * Increment the total number of jobs within the batch.
     */
    public function increment_total_jobs(string $batch_id, int $amount): void
    {
        $this->connection->table($this->table)->where('id', $batch_id)->update(['total_jobs' => new Expression('total_jobs + ' . $amount), 'pending_jobs' => new Expression('pending_jobs + ' . $amount), 'finished_at' => null]);
    }
    /**
     * Decrement the total number of pending jobs for the batch.
     */
    public function decrement_pending_jobs(string $batch_id, string $job_id): \Illuminate\Bus\Updated_Batch_Job_Counts
    {
        $values = $this->update_atomic_values($batch_id, fn($batch): array => ['pending_jobs' => $batch->pending_jobs - 1, 'failed_jobs' => $batch->failed_jobs, 'failed_job_ids' => json_encode(array_values(array_diff((array) json_decode((string) $batch->failed_job_ids, true), [$job_id])))]);
        return new Updated_Batch_Job_Counts($values['pending_jobs'], $values['failed_jobs']);
    }
    /**
     * Increment the total number of failed jobs for the batch.
     */
    public function increment_failed_jobs(string $batch_id, string $job_id): \Illuminate\Bus\Updated_Batch_Job_Counts
    {
        $values = $this->update_atomic_values($batch_id, fn($batch): array => ['pending_jobs' => $batch->pending_jobs, 'failed_jobs' => $batch->failed_jobs + 1, 'failed_job_ids' => json_encode(array_values(array_unique(array_merge((array) json_decode((string) $batch->failed_job_ids, true), [$job_id]))))]);
        return new Updated_Batch_Job_Counts($values['pending_jobs'], $values['failed_jobs']);
    }
    /**
     * Update an atomic value within the batch.
     *
     * @return int|null
     */
    protected function update_atomic_values(string $batch_id, Closure $callback)
    {
        return $this->connection->transaction(function () use ($batch_id, $callback) {
            $batch = $this->connection->table($this->table)->where('id', $batch_id)->lock_for_update()->first();
            return is_null($batch) ? [] : tap($callback($batch), function (array $values) use ($batch_id): void {
                $this->connection->table($this->table)->where('id', $batch_id)->update($values);
            });
        });
    }
    /**
     * Mark the batch that has the given ID as finished.
     */
    public function mark_as_finished(string $batch_id): void
    {
        $this->connection->table($this->table)->where('id', $batch_id)->update(['finished_at' => time()]);
    }
    /**
     * Cancel the batch that has the given ID.
     */
    public function cancel(string $batch_id): void
    {
        $this->connection->table($this->table)->where('id', $batch_id)->update(['cancelled_at' => time(), 'finished_at' => time()]);
    }
    /**
     * Delete the batch that has the given ID.
     */
    public function delete(string $batch_id): void
    {
        $this->connection->table($this->table)->where('id', $batch_id)->delete();
    }
    /**
     * Prune all of the entries older than the given date.
     *
     * @return int
     */
    public function prune(DateTimeInterface $before): int|float
    {
        $query = $this->connection->table($this->table)->where_not_null('finished_at')->where('finished_at', '<', $before->get_timestamp());
        $total_deleted = 0;
        do {
            $deleted = $query->limit(1000)->delete();
            $total_deleted += $deleted;
        } while ($deleted !== 0);
        return $total_deleted;
    }
    /**
     * Prune all of the unfinished entries older than the given date.
     *
     * @return int
     */
    public function prune_unfinished(DateTimeInterface $before): int|float
    {
        $query = $this->connection->table($this->table)->where_null('finished_at')->where('created_at', '<', $before->get_timestamp());
        $total_deleted = 0;
        do {
            $deleted = $query->limit(1000)->delete();
            $total_deleted += $deleted;
        } while ($deleted !== 0);
        return $total_deleted;
    }
    /**
     * Prune all of the cancelled entries older than the given date.
     *
     * @return int
     */
    public function prune_cancelled(DateTimeInterface $before): int|float
    {
        $query = $this->connection->table($this->table)->where_not_null('cancelled_at')->where('created_at', '<', $before->get_timestamp());
        $total_deleted = 0;
        do {
            $deleted = $query->limit(1000)->delete();
            $total_deleted += $deleted;
        } while ($deleted !== 0);
        return $total_deleted;
    }
    /**
     * Execute the given Closure within a storage specific transaction.
     *
     * @return mixed
     */
    public function transaction(Closure $callback)
    {
        return $this->connection->transaction(fn() => $callback());
    }
    /**
     * Rollback the last database transaction for the connection.
     */
    public function roll_back(): void
    {
        $this->connection->roll_back(toLevel: 0);
    }
    /**
     * Serialize the given value.
     *
     * @param  mixed  $value
     */
    protected function serialize($value): string
    {
        $serialized = serialize($value);
        return $this->connection instanceof Postgres_Connection ? base64_encode($serialized) : $serialized;
    }
    /**
     * Unserialize the given value.
     *
     * @param  string  $serialized
     * @return mixed
     */
    protected function unserialize($serialized)
    {
        if ($this->connection instanceof Postgres_Connection && !Str::contains($serialized, [':', ';'])) {
            $serialized = base64_decode($serialized);
        }
        try {
            return unserialize($serialized);
        } catch (Throwable) {
            return [];
        }
    }
    /**
     * Convert the given raw batch to a Batch object.
     *
     * @param  object  $batch
     */
    protected function to_batch($batch): \Illuminate\Bus\Batch
    {
        return $this->factory->make($this, $batch->id, $batch->name, (int) $batch->total_jobs, (int) $batch->pending_jobs, (int) $batch->failed_jobs, (array) json_decode((string) $batch->failed_job_ids, true), $this->unserialize($batch->options), Carbon_Immutable::create_from_timestamp($batch->created_at, date_default_timezone_get()), $batch->cancelled_at ? Carbon_Immutable::create_from_timestamp($batch->cancelled_at, date_default_timezone_get()) : $batch->cancelled_at, $batch->finished_at ? Carbon_Immutable::create_from_timestamp($batch->finished_at, date_default_timezone_get()) : $batch->finished_at);
    }
    /**
     * Get the underlying database connection.
     */
    public function get_connection(): \Illuminate\Database\Connection
    {
        return $this->connection;
    }
    /**
     * Set the underlying database connection.
     */
    public function set_connection(Connection $connection): void
    {
        $this->connection = $connection;
    }
}