<?php

namespace Illuminate\Foundation\Events;

class VendorTagPublished
{
    /**
     * Create a new event instance.
     *
     * @param  string  $tag
     * @param  array  $paths
     */
    public function __construct(
        /**
         * The vendor tag that was published.
         */
        public $tag,
        /**
         * The publishable paths registered by the tag.
         */
        public $paths
    )
    {
    }
}
