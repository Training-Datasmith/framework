<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Events;

class Vendor_Tag_Published
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