<?php

namespace Illuminate\Translation;

trait CreatesPotentiallyTranslatedStrings
{
    /**
     * Create a pending potentially translated string.
     *
     * @param  string  $attribute
     * @param  string|null  $message
     */
    protected function pendingPotentiallyTranslatedString($attribute, $message): \Illuminate\Translation\PotentiallyTranslatedString
    {
        $destructor = $message === null
            ? fn ($message) => $this->messages[] = $message
            : fn ($message) => $this->messages[$attribute] = $message;

        return new class($message ?? $attribute, $this->validator->getTranslator(), $destructor) extends PotentiallyTranslatedString
        {
            /**
             * Create a new pending potentially translated string.
             *
             * @param  string  $message
             * @param  \Illuminate\Contracts\Translation\Translator  $translator
             * @param  \Closure  $destructor
             */
            public function __construct($message, $translator, /**
             * The callback to call when the object destructs.
             */
            protected $destructor)
            {
                parent::__construct($message, $translator);
            }

            /**
             * Handle the object's destruction.
             */
            public function __destruct()
            {
                ($this->destructor)($this->toString());
            }
        };
    }
}
