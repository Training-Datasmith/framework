<?php

declare (strict_types=1);
namespace Illuminate\Console\Concerns;

use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;
trait Finds_Available_Models
{
    /**
     * Get a list of possible model names.
     *
     * @return array<int, string>
     */
    protected function find_available_models()
    {
        $model_path = is_dir(app_path('Models')) ? app_path('Models') : app_path();
        return (new Collection(Finder::create()->files()->depth(0)->in($model_path)))->map(fn($file) => $file->get_basename('.php'))->sort()->values()->all();
    }
}