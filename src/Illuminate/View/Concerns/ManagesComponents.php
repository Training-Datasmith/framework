<?php

declare(strict_types=1);

namespace Illuminate\View\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\View\ComponentSlot;

trait ManagesComponents
{
    /**
     * The components being rendered.
     *
     * @var array
     */
    protected $componentStack = [];

    /**
     * The original data passed to the component.
     *
     * @var array
     */
    protected $componentData = [];

    /**
     * The component data for the component that is currently being rendered.
     *
     * @var array
     */
    protected $currentComponentData = [];

    /**
     * The slot contents for the component.
     *
     * @var array
     */
    protected $slots = [];

    /**
     * The names of the slots being rendered.
     *
     * @var array
     */
    protected $slotStack = [];

    /**
     * Start a component rendering process.
     *
     * @param  \Illuminate\Contracts\View\View|\Illuminate\Contracts\Support\Htmlable|\Closure|string  $view
     */
    public function startComponent($view, array $data = []): void
    {
        if (ob_start()) {
            $this->componentStack[] = $view;

            $this->componentData[$this->currentComponent()] = $data;

            $this->slots[$this->currentComponent()] = [];
        }
    }

    /**
     * Get the first view that actually exists from the given list, and start a component.
     */
    public function startComponentFirst(array $names, array $data = []): void
    {
        $name = Arr::first($names, fn ($item) => $this->exists($item));

        $this->startComponent($name, $data);
    }

    /**
     * Render the current component.
     *
     * @return string
     */
    public function renderComponent()
    {
        $view = array_pop($this->componentStack);

        $this->currentComponentData = array_merge(
            $previousComponentData = $this->currentComponentData,
            $data = $this->componentData()
        );

        try {
            $view = value($view, $data);
            if ($view instanceof View) {
                return $view->with($data)->render();
            }

            if ($view instanceof Htmlable) {
                return $view->toHtml();
            }
            return $this->make($view, $data)->render();
        } finally {
            $this->currentComponentData = $previousComponentData;
        }
    }

    /**
     * Get the data for the given component.
     */
    protected function componentData(): array
    {
        $defaultSlot = new ComponentSlot(trim(ob_get_clean()));

        $slots = array_merge([
            '__default' => $defaultSlot,
        ], $this->slots[count($this->componentStack)]);

        return array_merge(
            $this->componentData[count($this->componentStack)],
            ['slot' => $defaultSlot],
            $this->slots[count($this->componentStack)],
            ['__laravel_slots' => $slots]
        );
    }

    /**
     * Get an item from the component data that exists above the current component.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function getConsumableComponentData($key, $default = null)
    {
        if (array_key_exists($key, $this->currentComponentData)) {
            return $this->currentComponentData[$key];
        }

        $currentComponent = count($this->componentStack);

        if ($currentComponent === 0) {
            return value($default);
        }

        for ($i = $currentComponent - 1; $i >= 0; $i--) {
            $data = $this->componentData[$i] ?? [];

            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return value($default);
    }

    /**
     * Start the slot rendering process.
     *
     * @param  string  $name
     * @param  string|null  $content
     * @param  array  $attributes
     */
    public function slot($name, $content = null, $attributes = []): void
    {
        if (func_num_args() === 2 || $content !== null) {
            $this->slots[$this->currentComponent()][$name] = $content;
        } elseif (ob_start()) {
            $this->slots[$this->currentComponent()][$name] = '';

            $this->slotStack[$this->currentComponent()][] = [$name, $attributes];
        }
    }

    /**
     * Save the slot content for rendering.
     */
    public function endSlot(): void
    {
        last($this->componentStack);

        $currentSlot = array_pop(
            $this->slotStack[$this->currentComponent()]
        );

        [$currentName, $currentAttributes] = $currentSlot;

        $this->slots[$this->currentComponent()][$currentName] = new ComponentSlot(
            trim(ob_get_clean()),
            $currentAttributes
        );
    }

    /**
     * Get the index for the current component.
     */
    protected function currentComponent(): int
    {
        return count($this->componentStack) - 1;
    }

    /**
     * Flush all of the component state.
     *
     * @return void
     */
    protected function flushComponents()
    {
        $this->componentStack = [];
        $this->componentData = [];
        $this->currentComponentData = [];
    }
}
