<?php

namespace Illuminate\Queue\Jobs;

use Illuminate\Support\InteractsWithTime;

class DatabaseJobRecord
{
    use InteractsWithTime;

    /**
     * Create a new job record instance.
     *
     * @param  \stdClass  $record
     */
    public function __construct(
        /**
         * The underlying job record.
         */
        protected $record
    )
    {
    }

    /**
     * Increment the number of times the job has been attempted.
     *
     * @return int
     */
    public function increment(): int|float
    {
        $this->record->attempts++;

        return $this->record->attempts;
    }

    /**
     * Update the "reserved at" timestamp of the job.
     *
     * @return int
     */
    public function touch()
    {
        $this->record->reserved_at = $this->currentTime();

        return $this->record->reserved_at;
    }

    /**
     * Dynamically access the underlying job information.
     */
    public function __get(string $key): mixed
    {
        return $this->record->{$key};
    }
}
