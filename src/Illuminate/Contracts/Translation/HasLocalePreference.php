<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Translation;

interface Has_Locale_Preference
{
    /**
     * Get the preferred locale of the entity.
     *
     * @return string|null
     */
    public function preferred_locale();
}