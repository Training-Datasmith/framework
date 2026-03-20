<?php

declare (strict_types=1);
namespace Illuminate\Database\Concerns;

use Illuminate\Support\Collection;
trait Explains_Queries
{
    /**
     * Explains the query.
     */
    public function explain(): \Illuminate\Support\Collection
    {
        $sql = $this->to_sql();
        $bindings = $this->get_bindings();
        $explanation = $this->get_connection()->select('EXPLAIN ' . $sql, $bindings);
        return new Collection($explanation);
    }
}