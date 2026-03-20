<?php

declare (strict_types=1);
namespace Illuminate\Database;

use Illuminate\Container\Container;
use Illuminate\Contracts\Database\Lost_Connection_Detector as LostConnectionDetectorContract;
use Throwable;
trait Detects_Lost_Connections
{
    /**
     * Determine if the given exception was caused by a lost connection.
     *
     * @return bool
     */
    protected function caused_by_lost_connection(Throwable $e)
    {
        $container = Container::get_instance();
        $detector = $container->bound(Lost_Connection_Detector_Contract::class) ? $container[Lost_Connection_Detector_Contract::class] : new Lost_Connection_Detector();
        return $detector->caused_by_lost_connection($e);
    }
}