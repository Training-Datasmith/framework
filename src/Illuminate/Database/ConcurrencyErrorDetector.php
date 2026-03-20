<?php

declare (strict_types=1);
namespace Illuminate\Database;

use Illuminate\Contracts\Database\Concurrency_Error_Detector as ConcurrencyErrorDetectorContract;
use Illuminate\Support\Str;
use PDOException;
use Throwable;
class Concurrency_Error_Detector implements Concurrency_Error_Detector_Contract
{
    /**
     * Determine if the given exception was caused by a concurrency error such as a deadlock or serialization failure.
     */
    public function caused_by_concurrency_error(Throwable $e): bool
    {
        if ($e instanceof PDOException && ($e->get_code() === 40001 || $e->get_code() === '40001')) {
            return true;
        }
        $message = $e->get_message();
        return Str::contains($message, ['Deadlock found when trying to get lock', 'deadlock detected', 'The database file is locked', 'database is locked', 'database table is locked', 'A table in the database is locked', 'has been chosen as the deadlock victim', 'Lock wait timeout exceeded; try restarting transaction', 'WSREP detected deadlock/conflict and aborted the transaction. Try restarting the transaction', 'Record has changed since last read in table']);
    }
}