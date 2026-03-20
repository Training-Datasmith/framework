<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Events;

class Locale_Updated
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
        public $previous_locale = null
    )
    {
    }
}