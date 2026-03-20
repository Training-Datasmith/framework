<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Database;

use Throwable;
interface Concurrency_Error_Detector
{
    /**
     * Determine if the given exception was caused by a concurrency error such as a deadlock or serialization failure.
     */
    public function caused_by_concurrency_error(Throwable $e): bool;
}