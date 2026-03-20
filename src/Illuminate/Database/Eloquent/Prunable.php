<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

use Illuminate\Contracts\Debug\Exception_Handler;
use Illuminate\Database\Events\Models_Pruned;
use LogicException;
use Throwable;
trait Prunable
{
    /**
     * Prune all prunable models in the database.
     */
    public function prune_all(int $chunk_size = 1000): int
    {
        $total = 0;
        $this->prunable()->when(static::is_soft_deletable(), function ($query): void {
            $query->with_trashed();
        })->chunk_by_id($chunk_size, function ($models) use (&$total): void {
            $models->each(function ($model) use (&$total): void {
                try {
                    $model->prune();
                    $total++;
                } catch (Throwable $e) {
                    $handler = app(Exception_Handler::class);
                    if ($handler) {
                        $handler->report($e);
                    } else {
                        throw $e;
                    }
                }
            });
            event(new Models_Pruned(static::class, $total));
        });
        return $total;
    }
    /**
     * Get the prunable model query.
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function prunable(): never
    {
        throw new LogicException('Please implement the prunable method on your model.');
    }
    /**
     * Prune the model in the database.
     *
     * @return bool|null
     */
    public function prune()
    {
        $this->pruning();
        return static::is_soft_deletable() ? $this->force_delete() : $this->delete();
    }
    /**
     * Prepare the model for pruning.
     *
     * @return void
     */
    protected function pruning()
    {
    }
}