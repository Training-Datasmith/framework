<?php

declare (strict_types=1);
namespace Illuminate\Log\Context;

use Illuminate\Container\Container;
use Illuminate\Contracts\Log\Context_Log_Processor as ContextLogProcessorContract;
use Illuminate\Log\Context\Repository as ContextRepository;
use Monolog\Log_Record;
class Context_Log_Processor implements Context_Log_Processor_Contract
{
    /**
     * Add contextual data to the log's "extra" parameter.
     */
    public function __invoke(Log_Record $record): Log_Record
    {
        $app = Container::get_instance();
        if (!$app->bound(Context_Repository::class)) {
            return $record;
        }
        return $record->with(extra: [...$record->extra, ...$app->get(Context_Repository::class)->all()]);
    }
}