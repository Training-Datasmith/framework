<?php

declare (strict_types=1);
namespace Illuminate\Database\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\Model_Pruning_Finished;
use Illuminate\Database\Events\Model_Pruning_Starting;
use Illuminate\Database\Events\Models_Pruned;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Finder\Finder;
#[As_Command(name: 'model:prune')]
class Prune_Command extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'model:prune
                                {--model=* : Class names of the models to be pruned}
                                {--except=* : Class names of the models to be excluded from pruning}
                                {--path=* : Absolute path(s) to directories where models are located}
                                {--chunk=1000 : The number of models to retrieve per chunk of models to be deleted}
                                {--pretend : Display the number of prunable records found instead of deleting them}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune models that are no longer needed';
    /**
     * Execute the console command.
     */
    public function handle(Dispatcher $events): void
    {
        $models = $this->models();
        if ($models->is_empty()) {
            $this->components->info('No prunable models found.');
            return;
        }
        if ($this->option('pretend')) {
            $models->each(function ($model): void {
                $this->pretend_to_prune($model);
            });
            return;
        }
        $pruning = [];
        $events->listen(Models_Pruned::class, function ($event) use (&$pruning): void {
            if (!in_array($event->model, $pruning)) {
                $pruning[] = $event->model;
                $this->new_line();
                $this->components->info(sprintf('Pruning [%s] records.', $event->model));
            }
            $this->components->two_column_detail($event->model, "{$event->count} records");
        });
        $events->dispatch(new Model_Pruning_Starting($models->all()));
        $models->each(function (string $model): void {
            $this->prune_model($model);
        });
        $events->dispatch(new Model_Pruning_Finished($models->all()));
        $events->forget(Models_Pruned::class);
    }
    /**
     * Prune the given model.
     *
     * @return void
     */
    protected function prune_model(string $model)
    {
        $instance = new $model();
        $chunk_size = property_exists($instance, 'prunableChunkSize') ? $instance->prunable_chunk_size : $this->option('chunk');
        $total = $model::is_prunable() ? $instance->prune_all($chunk_size) : 0;
        if ($total == 0) {
            $this->components->info("No prunable [{$model}] records found.");
        }
    }
    /**
     * Determine the models that should be pruned.
     */
    protected function models(): \Illuminate\Support\Collection
    {
        $models = $this->option('model');
        $except = $this->option('except');
        if ($models && $except) {
            throw new InvalidArgumentException('The --models and --except options cannot be combined.');
        }
        if ($models) {
            return (new Collection($models))->filter(static fn(string $model): bool => class_exists($model))->values();
        }
        return (new Collection(Finder::create()->in($this->get_path())->files()->name('*.php')))->map(function ($model) {
            $namespace = $this->laravel->get_namespace();
            return $namespace . str_replace(['/', '.php'], ['\\', ''], Str::after($model->get_real_path(), realpath(app_path()) . DIRECTORY_SEPARATOR));
        })->when(!empty($except), fn($models) => $models->reject(fn($model): bool => in_array($model, $except)))->filter(fn(string $model): bool => $this->is_prunable($model))->values();
    }
    /**
     * Get the path where models are located.
     *
     * @return string[]|string
     */
    protected function get_path()
    {
        if (!empty($path = $this->option('path'))) {
            return (new Collection($path))->map(fn($path): string => base_path($path))->all();
        }
        return app_path('Models');
    }
    /**
     * Display how many models will be pruned.
     *
     * @param  class-string  $model
     * @return void
     */
    protected function pretend_to_prune($model)
    {
        $instance = new $model();
        $count = $instance->prunable()->when($model::is_soft_deletable(), function ($query): void {
            $query->with_trashed();
        })->count();
        if ($count === 0) {
            $this->components->info("No prunable [{$model}] records found.");
        } else {
            $this->components->info("{$count} [{$model}] records will be pruned.");
        }
    }
    /**
     * Determine if the given model is prunable.
     */
    protected function is_prunable(string $model): bool
    {
        return class_exists($model) && is_a($model, Model::class, true) && !(new \ReflectionClass($model))->is_abstract() && $model::is_prunable();
    }
}