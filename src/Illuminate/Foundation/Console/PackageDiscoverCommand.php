<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Foundation\Package_Manifest;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'package:discover')]
class Package_Discover_Command extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'package:discover';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild the cached package manifest';
    /**
     * Execute the console command.
     */
    public function handle(Package_Manifest $manifest): void
    {
        $this->components->info('Discovering packages');
        $manifest->build();
        (new Collection($manifest->manifest))->keys()->each(fn($description) => $this->components->task($description))->when_not_empty(fn() => $this->new_line());
    }
}