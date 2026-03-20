<?php

declare (strict_types=1);
namespace Illuminate\Log;

use Psr\Log\Logger_Interface;
if (!function_exists('Illuminate\Log\log')) {
    /**
     * Log a debug message to the logs.
     *
     * @param  string|null  $message
     * @return ($message is null ? \Psr\Log\LoggerInterface: null)
     */
    function log($message = null, array $context = []): ?Logger_Interface
    {
        return logger($message, $context);
    }
}