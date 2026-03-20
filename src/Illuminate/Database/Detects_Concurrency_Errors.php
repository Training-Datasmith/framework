<?php

declare (strict_types=1);
namespace Illuminate\Database;

use Illuminate\Container\Container;
use Illuminate\Contracts\Database\Concurrency_Error_Detector as ConcurrencyErrorDetectorContract;
use Throwable;
trait Detects_Concurrency_Errors
{
    /**
     * Determine if the given exception was caused by a concurrency error such as a deadlock or serialization failure.
     *
     * @return bool
     */
    protected function caused_by_concurrency_error(Throwable $e)
    {
        $container = Container::get_instance();
        $detector = $container->bound(Concurrency_Error_Detector_Contract::class) ? $container[Concurrency_Error_Detector_Contract::class] : new Concurrency_Error_Detector();
        return $detector->caused_by_concurrency_error($e);
    }
}