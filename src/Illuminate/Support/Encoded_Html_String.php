<?php

declare(strict_types=1);

namespace Illuminate\Support;

use BackedEnum;
use Illuminate\Contracts\Support\DeferringDisplayableValue;
use Illuminate\Contracts\Support\Htmlable;

class EncodedHtmlString extends HtmlString
{
    /**
     * The HTML string.
     *
     * @var \Illuminate\Contracts\Support\DeferringDisplayableValue|\Illuminate\Contracts\Support\Htmlable|\BackedEnum|string|int|float|null
     */
    protected $html;

    /**
     * The callback that should be used to encode the HTML strings.
     *
     * @var callable|null
     */
    protected static $encodeUsingFactory;

    /**
     * Create a new encoded HTML string instance.
     *
     * @param  \Illuminate\Contracts\Support\DeferringDisplayableValue|\Illuminate\Contracts\Support\Htmlable|\BackedEnum|string|int|float|null  $html
     */
    public function __construct($html = '', protected bool $doubleEncode = true)
    {
        parent::__construct($html);
    }

    /**
     * Convert the special characters in the given value.
     *
     * @internal
     *
     * @param  string|null  $value
     */
    public static function convert($value, bool $withQuote = true, bool $doubleEncode = true): string
    {
        $flag = $withQuote ? ENT_QUOTES : ENT_NOQUOTES;

        return htmlspecialchars($value ?? '', $flag | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
    }

    /**
     * Get the HTML string.
     *
     * @return string
     */
    #[\Override]
    public function toHtml()
    {
        $value = $this->html;

        if ($value instanceof DeferringDisplayableValue) {
            $value = $value->resolveDisplayableValue();
        }

        if ($value instanceof Htmlable) {
            return $value->toHtml();
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return (static::$encodeUsingFactory ?? (fn ($value, bool $doubleEncode): string => static::convert($value, doubleEncode: $doubleEncode)))($value, $this->doubleEncode);
    }

    /**
     * Set the callable that will be used to encode the HTML strings.
     */
    public static function encodeUsing(?callable $factory = null): void
    {
        static::$encodeUsingFactory = $factory;
    }

    /**
     * Flush the class's global state.
     */
    public static function flushState(): void
    {
        static::$encodeUsingFactory = null;
    }
}
