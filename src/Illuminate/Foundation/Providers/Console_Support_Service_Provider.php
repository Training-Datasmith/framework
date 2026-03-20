<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Providers;

use Illuminate\Contracts\Support\Deferrable_Provider;
use Illuminate\Database\Migration_Service_Provider;
use Illuminate\Support\Aggregate_Service_Provider;
class Console_Support_Service_Provider extends Aggregate_Service_Provider implements Deferrable_Provider
{
    /**
     * The provider class names.
     *
     * @var string[]
     */
    protected $providers = [Artisan_Service_Provider::class, Migration_Service_Provider::class, Composer_Service_Provider::class];
}