<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Database;

use Throwable;
interface Lost_Connection_Detector
{
    /**
     * Determine if the given exception was caused by a lost connection.
     */
    public function caused_by_lost_connection(Throwable $e): bool;
}