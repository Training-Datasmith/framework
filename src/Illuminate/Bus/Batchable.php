<?php

declare (strict_types=1);
namespace Illuminate\Bus;

use Carbon\Carbon_Immutable;
use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\Batch_Fake;
trait Batchable
{
    /**
     * The batch ID (if applicable).
     *
     * @var string|null
     */
    public $batch_id;
    /**
     * The fake batch, if applicable.
     *
     * @var \Illuminate\Support\Testing\Fakes\BatchFake
     */
    private $fake_batch;
    /**
     * Get the batch instance for the job, if applicable.
     *
     * @return \Illuminate\Bus\Batch|null
     */
    public function batch()
    {
        if ($this->fake_batch) {
            return $this->fake_batch;
        }
        if ($this->batch_id) {
            return Container::get_instance()->make(Batch_Repository::class)?->find($this->batch_id);
        }
    }
    /**
     * Determine if the batch is still active and processing.
     */
    public function batching(): bool
    {
        $batch = $this->batch();
        return $batch && !$batch->cancelled();
    }
    /**
     * Set the batch ID on the job.
     *
     * @return $this
     */
    public function with_batch_id(string $batch_id)
    {
        $this->batch_id = $batch_id;
        return $this;
    }
    /**
     * Indicate that the job should use a fake batch.
     *
     * @return array{0: $this, 1: \Illuminate\Support\Testing\Fakes\BatchFake}
     */
    public function with_fake_batch(string $id = '', string $name = '', int $total_jobs = 0, int $pending_jobs = 0, int $failed_jobs = 0, array $failed_job_ids = [], array $options = [], ?Carbon_Immutable $created_at = null, ?Carbon_Immutable $cancelled_at = null, ?Carbon_Immutable $finished_at = null): array
    {
        $this->fake_batch = new Batch_Fake(empty($id) ? (string) Str::uuid() : $id, $name, $total_jobs, $pending_jobs, $failed_jobs, $failed_job_ids, $options, $created_at ?? Carbon_Immutable::now(), $cancelled_at, $finished_at);
        return [$this, $this->fake_batch];
    }
}