<?php

declare(strict_types=1);

namespace Illuminate\View;

class AnonymousComponent extends Component
{
    /**
     * Create a new anonymous component instance.
     *
     * @param  string  $view
     * @param  array  $data
     */
    public function __construct(
        /**
         * The component view.
         */
        protected $view,
        /**
         * The component data.
         */
        protected $data
    ) {
    }

    /**
     * Get the view / view contents that represent the component.
     *
     * @return string
     */
    public function render()
    {
        return $this->view;
    }

    /**
     * Get the data that should be supplied to the view.
     */
    public function data(): array
    {
        $this->attributes = $this->attributes ?: $this->newAttributeBag();

        return array_merge(
            ($this->data['attributes'] ?? null)?->getAttributes() ?: [],
            $this->attributes->getAttributes(),
            $this->data,
            ['attributes' => $this->attributes]
        );
    }
}
