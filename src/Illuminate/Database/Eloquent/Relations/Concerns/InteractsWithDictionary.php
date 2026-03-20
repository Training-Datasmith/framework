<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Relations\Concerns;

use function Illuminate\Support\enum_value;
use InvalidArgumentException;
use Unit_Enum;
trait Interacts_With_Dictionary
{
    /**
     * Get a dictionary key attribute - casting it to a string if necessary.
     *
     * @param  mixed  $attribute
     * @return string|int|null
     *
     * @throws \InvalidArgumentException
     */
    protected function get_dictionary_key($attribute)
    {
        if (is_null($attribute) || is_string($attribute) || is_int($attribute)) {
            return $attribute;
        }
        if (is_object($attribute)) {
            if (method_exists($attribute, '__toString')) {
                return $attribute->__toString();
            }
            if ($attribute instanceof Unit_Enum) {
                return enum_value($attribute);
            }
            throw new InvalidArgumentException('Model attribute value is an object but does not have a __toString method.');
        }
        return (string) $attribute;
    }
}