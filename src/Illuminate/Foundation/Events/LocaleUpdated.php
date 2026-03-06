<?php

namespace Illuminate\Foundation\Events;

class LocaleUpdated
{
    /**
     * Create a new event instance.
     *
     * @param  string  $locale
     * @param  ?string  $previousLocale
     */
    public function __construct(
        /**
         * The new locale.
         */
        public $locale,
        /**
         * The previous locale.
         */
        public $previousLocale = null
    )
    {
    }
}
