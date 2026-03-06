<?php

namespace Illuminate\Translation;

use Stringable;

class PotentiallyTranslatedString implements Stringable
{
    /**
     * The translated string.
     *
     * @var string|null
     */
    protected $translation;

    /**
     * Create a new potentially translated string.
     *
     * @param  string  $string
     * @param  \Illuminate\Contracts\Translation\Translator  $translator
     */
    public function __construct(
        /**
         * The string that may be translated.
         */
        protected $string,
        /**
         * The validator that may perform the translation.
         */
        protected $translator
    )
    {
    }

    /**
     * Translate the string.
     *
     * @param  string|null  $locale
     * @return $this
     */
    public function translate(array $replace = [], $locale = null): static
    {
        $this->translation = $this->translator->get($this->string, $replace, $locale);

        return $this;
    }

    /**
     * Translates the string based on a count.
     *
     * @param  \Countable|int|float|array  $number
     * @param  string|null  $locale
     * @return $this
     */
    public function translateChoice($number, array $replace = [], $locale = null): static
    {
        $this->translation = $this->translator->choice($this->string, $number, $replace, $locale);

        return $this;
    }

    /**
     * Get the original string.
     *
     * @return string
     */
    public function original()
    {
        return $this->string;
    }

    /**
     * Get the potentially translated string.
     */
    public function __toString(): string
    {
        return $this->translation ?? $this->string;
    }

    /**
     * Get the potentially translated string.
     */
    public function toString(): string
    {
        return (string) $this;
    }
}
