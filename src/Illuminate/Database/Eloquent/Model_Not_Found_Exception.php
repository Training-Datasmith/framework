<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

use Illuminate\Database\Records_Not_Found_Exception;
use Illuminate\Support\Arr;
/**
 * Thrown when an Eloquent query returns no results for a model lookup.
 *
 * Carries the fully-qualified model class name and the queried IDs so that
 * exception handlers can produce useful error messages without inspecting the
 * original query.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @since 5.0
 */
class Model_Not_Found_Exception extends Records_Not_Found_Exception
{
    /**
     * Name of the affected Eloquent model.
     *
     * @var class-string<TModel>
     */
    protected string $model;

    /**
     * The affected model IDs.
     *
     * @var array<int, int|string>
     */
    protected array $ids = [];

    /**
     * Set the affected Eloquent model and instance ids.
     *
     * Automatically constructs a human-readable exception message that
     * includes the model class and the missing IDs, e.g.:
     * "No query results for model [App\Models\User] 1, 2, 3"
     *
     * @param  class-string<TModel>              $model  Fully-qualified model class name.
     * @param  array<int, int|string>|int|string $ids    The ID(s) that were not found.
     * @return $this
     *
     * @since 5.0
     */
    public function set_model(string $model, array|int|string $ids = []): static
    {
        $this->model = $model;
        $this->ids = Arr::wrap($ids);
        $this->message = "No query results for model [{$model}]";
        if (count($this->ids) > 0) {
            $this->message .= ' ' . implode(', ', $this->ids);
        } else {
            $this->message .= '.';
        }
        return $this;
    }

    /**
     * Get the affected Eloquent model.
     *
     * @return class-string<TModel>  Fully-qualified model class name.
     *
     * @since 5.0
     */
    public function get_model(): string
    {
        return $this->model;
    }

    /**
     * Get the affected Eloquent model IDs.
     *
     * @return array<int, int|string>  The IDs that were queried but not found.
     *
     * @since 5.0
     */
    public function get_ids(): array
    {
        return $this->ids;
    }
}