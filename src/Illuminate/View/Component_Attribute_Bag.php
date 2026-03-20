<?php

declare(strict_types=1);

namespace Illuminate\View;

use ArrayAccess;
use ArrayIterator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\InteractsWithData;
use Illuminate\Support\Traits\Macroable;
use IteratorAggregate;
use JsonSerializable;
use Stringable;
use Traversable;

class ComponentAttributeBag implements Arrayable, ArrayAccess, IteratorAggregate, JsonSerializable, Htmlable, Stringable
{
    use Conditionable;
    use InteractsWithData;
    use Macroable;

    /**
     * The raw array of attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Create a new component attribute bag instance.
     */
    public function __construct(array $attributes = [])
    {
        $this->setAttributes($attributes);
    }

    /**
     * Get all the attribute values.
     *
     * @param  mixed  $keys
     * @return array
     */
    public function all($keys = null)
    {
        if (is_null($keys)) {
            return $this->attributes;
        }

        return $this->only($keys)->toArray();
    }

    /**
     * Get the first attribute's value.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function first($default = null)
    {
        return $this->getIterator()->current() ?? value($default);
    }

    /**
     * Get a given attribute from the attribute array.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->attributes[$key] ?? value($default);
    }

    /**
     * Retrieve data from the instance.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    protected function data($key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->attributes;
        }

        return $this->get($key, $default);
    }

    /**
     * Only include the given attribute from the attribute array.
     *
     * @param  mixed  $keys
     */
    public function only($keys): static
    {
        if (is_null($keys)) {
            $values = $this->attributes;
        } else {
            $keys = Arr::wrap($keys);

            $values = Arr::only($this->attributes, $keys);
        }

        return new static($values);
    }

    /**
     * Exclude the given attribute from the attribute array.
     *
     * @param  mixed  $keys
     */
    public function except($keys): static
    {
        if (is_null($keys)) {
            $values = $this->attributes;
        } else {
            $keys = Arr::wrap($keys);

            $values = Arr::except($this->attributes, $keys);
        }

        return new static($values);
    }

    /**
     * Filter the attributes, returning a bag of attributes that pass the filter.
     *
     * @param  callable  $callback
     */
    public function filter(?callable $callback): static
    {
        return new static((new Collection($this->attributes))->filter($callback)->all());
    }

    /**
     * Return a bag of attributes that have keys starting with the given value / pattern.
     *
     * @param  string|string[]  $needles
     */
    public function whereStartsWith($needles): static
    {
        return $this->filter(fn ($value, $key): bool => Str::startsWith($key, $needles));
    }

    /**
     * Return a bag of attributes with keys that do not start with the given value / pattern.
     *
     * @param  string|string[]  $needles
     */
    public function whereDoesntStartWith($needles): static
    {
        return $this->filter(fn ($value, $key): bool => ! Str::startsWith($key, $needles));
    }

    /**
     * Return a bag of attributes that have keys starting with the given value / pattern.
     *
     * @param  string|string[]  $needles
     * @return static
     */
    public function thatStartWith($needles)
    {
        return $this->whereStartsWith($needles);
    }

    /**
     * Only include the given attribute from the attribute array.
     *
     * @param  mixed  $keys
     */
    public function onlyProps(array $keys): static
    {
        return $this->only(static::extractPropNames($keys));
    }

    /**
     * Exclude the given attribute from the attribute array.
     *
     * @param  mixed  $keys
     */
    public function exceptProps(array $keys): static
    {
        return $this->except(static::extractPropNames($keys));
    }

    /**
     * Conditionally merge classes into the attribute bag.
     *
     * @param  mixed  $classList
     */
    public function class($classList): static
    {
        $classList = Arr::wrap($classList);

        return $this->merge(['class' => Arr::toCssClasses($classList)]);
    }

    /**
     * Conditionally merge styles into the attribute bag.
     *
     * @param  mixed  $styleList
     */
    public function style($styleList): static
    {
        $styleList = Arr::wrap($styleList);

        return $this->merge(['style' => Arr::toCssStyles($styleList)]);
    }

    /**
     * Merge additional attributes / values into the attribute bag.
     *
     * @param  bool  $escape
     */
    public function merge(array $attributeDefaults = [], $escape = true): static
    {
        $attributeDefaults = array_map(fn ($value) => $this->shouldEscapeAttributeValue($escape, $value)
            ? e($value)
            : $value, $attributeDefaults);

        [$appendableAttributes, $nonAppendableAttributes] = (new Collection($this->attributes))
            ->partition(fn ($value, $key): bool => $key === 'class' || $key === 'style' || (
                isset($attributeDefaults[$key]) &&
                $attributeDefaults[$key] instanceof AppendableAttributeValue
            ));

        $attributes = $appendableAttributes->mapWithKeys(function ($value, $key) use ($attributeDefaults, $escape): array {
            $defaultsValue = isset($attributeDefaults[$key]) && $attributeDefaults[$key] instanceof AppendableAttributeValue
                ? $this->resolveAppendableAttributeDefault($attributeDefaults, $key, $escape)
                : ($attributeDefaults[$key] ?? '');

            if ($key === 'style') {
                $value = Str::finish($value, ';');
            }

            return [$key => implode(' ', array_unique(array_filter([$defaultsValue, $value])))];
        })->merge($nonAppendableAttributes)->all();

        return new static(array_merge($attributeDefaults, $attributes));
    }

    /**
     * Determine if the specific attribute value should be escaped.
     *
     * @param  bool  $escape
     * @param  mixed  $value
     * @return bool
     */
    protected function shouldEscapeAttributeValue($escape, $value)
    {
        if (! $escape) {
            return false;
        }

        return ! is_object($value) &&
               ! is_null($value) &&
               ! is_bool($value);
    }

    /**
     * Create a new appendable attribute value.
     *
     * @param  mixed  $value
     */
    public function prepends($value): \Illuminate\View\AppendableAttributeValue
    {
        return new AppendableAttributeValue($value);
    }

    /**
     * Resolve an appendable attribute value default value.
     *
     * @param  string  $key
     * @param  bool  $escape
     * @return mixed
     */
    protected function resolveAppendableAttributeDefault(array $attributeDefaults, $key, $escape)
    {
        if ($this->shouldEscapeAttributeValue($escape, $value = $attributeDefaults[$key]->value)) {
            return e($value);
        }

        return $value;
    }

    /**
     * Determine if the attribute bag is empty.
     */
    public function isEmpty(): bool
    {
        return trim((string) $this) === '';
    }

    /**
     * Determine if the attribute bag is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Get all of the raw attributes.
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Set the underlying attributes.
     */
    public function setAttributes(array $attributes): void
    {
        if (isset($attributes['attributes']) &&
            $attributes['attributes'] instanceof self) {
            $parentBag = $attributes['attributes'];

            unset($attributes['attributes']);

            $attributes = $parentBag->merge($attributes, $escape = false)->getAttributes();
        }

        $this->attributes = $attributes;
    }

    /**
     * Extract "prop" names from given keys.
     */
    public static function extractPropNames(array $keys): array
    {
        $props = [];

        foreach ($keys as $key => $default) {
            $key = is_numeric($key) ? $default : $key;

            $props[] = $key;
            $props[] = Str::kebab($key);
        }

        return $props;
    }

    /**
     * Get content as a string of HTML.
     */
    public function toHtml(): string
    {
        return (string) $this;
    }

    /**
     * Merge additional attributes / values into the attribute bag.
     */
    public function __invoke(array $attributeDefaults = []): \Illuminate\Support\HtmlString
    {
        return new HtmlString((string) $this->merge($attributeDefaults));
    }

    /**
     * Determine if the given offset exists.
     *
     * @param  string  $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Get the value at the given offset.
     *
     * @param  string  $offset
     */
    public function offsetGet($offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * Set the value at a given offset.
     *
     * @param  string  $offset
     * @param  mixed  $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->attributes[$offset] = $value;
    }

    /**
     * Remove the value at the given offset.
     *
     * @param  string  $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Get an iterator for the items.
     *
     * @return \ArrayIterator
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->attributes);
    }

    /**
     * Convert the object into a JSON serializable form.
     */
    public function jsonSerialize(): mixed
    {
        return $this->attributes;
    }

    /**
     * Get all the attribute values.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->all();
    }

    /**
     * Implode the attributes into a single HTML ready string.
     */
    public function __toString(): string
    {
        $string = '';

        foreach ($this->attributes as $key => $value) {
            if ($value === false) {
                continue;
            }
            if (is_null($value)) {
                continue;
            }
            if ($value === true) {
                $value = $key === 'x-data' || str_starts_with((string) $key, 'wire:') ? '' : $key;
            }

            $string .= ' '.$key.'="'.str_replace('"', '\\"', trim((string) $value)).'"';
        }

        return trim($string);
    }
}
