<?php

namespace Illuminate\Pagination;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use UnexpectedValueException;

/** @implements Arrayable<array-key, mixed> */
class Cursor implements Arrayable
{
    /**
     * Create a new cursor instance.
     *
     * @param  bool  $pointsToNextItems
     */
    public function __construct(
        /**
         * The parameters associated with the cursor.
         */
        protected array $parameters,
        /**
         * Determine whether the cursor points to the next or previous set of items.
         */
        protected $pointsToNextItems = true
    )
    {
    }

    /**
     * Get the given parameter from the cursor.
     *
     * @return string|null
     * @throws \UnexpectedValueException
     */
    public function parameter(string $parameterName)
    {
        if (! array_key_exists($parameterName, $this->parameters)) {
            throw new UnexpectedValueException("Unable to find parameter [{$parameterName}] in pagination item.");
        }

        return $this->parameters[$parameterName];
    }

    /**
     * Get the given parameters from the cursor.
     *
     * @return array
     */
    public function parameters(array $parameterNames)
    {
        return (new Collection($parameterNames))
            ->map(fn (string $parameterName) => $this->parameter($parameterName))
            ->toArray();
    }

    /**
     * Determine whether the cursor points to the next set of items.
     *
     * @return bool
     */
    public function pointsToNextItems()
    {
        return $this->pointsToNextItems;
    }

    /**
     * Determine whether the cursor points to the previous set of items.
     */
    public function pointsToPreviousItems(): bool
    {
        return ! $this->pointsToNextItems;
    }

    /**
     * Get the array representation of the cursor.
     */
    public function toArray(): array
    {
        return array_merge($this->parameters, [
            '_pointsToNextItems' => $this->pointsToNextItems,
        ]);
    }

    /**
     * Get the encoded string representation of the cursor to construct a URL.
     */
    public function encode(): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($this->toArray())));
    }

    /**
     * Get a cursor instance from the encoded string representation.
     *
     * @param  string|null  $encodedString
     * @return static|null
     */
    public static function fromEncoded($encodedString): ?self
    {
        if (! is_string($encodedString)) {
            return null;
        }

        $parameters = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $encodedString)), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        $pointsToNextItems = $parameters['_pointsToNextItems'];

        unset($parameters['_pointsToNextItems']);

        return new static($parameters, $pointsToNextItems);
    }
}
