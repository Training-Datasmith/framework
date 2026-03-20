<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

use Illuminate\Database\Events\Models_Pruned;
use LogicException;
trait Mass_Prunable
{
    /**
     * Prune all prunable models in the database.
     *
     * @return int
     */
    public function prune_all(int $chunk_size = 1000): int|float
    {
        $query = tap($this->prunable(), function ($query) use ($chunk_size): void {
            $query->when(!$query->get_query()->limit, function ($query) use ($chunk_size): void {
                $query->limit($chunk_size);
            });
        });
        $total = 0;
        $soft_deletable = static::is_soft_deletable();
        do {
            $total += $count = $soft_deletable ? $query->force_delete() : $query->delete();
            if ($count > 0) {
                event(new Models_Pruned(static::class, $total));
            }
        } while ($count > 0);
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
}