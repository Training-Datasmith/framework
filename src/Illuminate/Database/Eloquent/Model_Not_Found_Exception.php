<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

use Illuminate\Database\Records_Not_Found_Exception;
use Illuminate\Support\Arr;
/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
class Model_Not_Found_Exception extends Records_Not_Found_Exception
{
    /**
     * Name of the affected Eloquent model.
     *
     * @var class-string<TModel>
     */
    protected $model;
    /**
     * The affected model IDs.
     *
     * @var array<int, int|string>
     */
    protected $ids;
    /**
     * Set the affected Eloquent model and instance ids.
     *
     * @param  class-string<TModel>  $model
     * @param  array<int, int|string>|int|string  $ids
     * @return $this
     */
    public function set_model($model, $ids = []): static
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
     * @return class-string<TModel>
     */
    public function get_model()
    {
        return $this->model;
    }
    /**
     * Get the affected Eloquent model IDs.
     *
     * @return array<int, int|string>
     */
    public function get_ids()
    {
        return $this->ids;
    }
}