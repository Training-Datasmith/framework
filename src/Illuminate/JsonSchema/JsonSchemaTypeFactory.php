<?php

declare (strict_types=1);
namespace Illuminate\Json_Schema;

use Closure;
use Illuminate\Contracts\Json_Schema\Json_Schema as JsonSchemaContract;
class Json_Schema_Type_Factory extends Json_Schema implements Json_Schema_Contract
{
    /**
     * Create a new object schema instance.
     *
     * @param  (Closure(JsonSchemaTypeFactory): array<string, Types\Type>)|array<string, Types\Type>  $properties
     */
    public function object(Closure|array $properties = []): Types\Object_Type
    {
        if ($properties instanceof Closure) {
            $properties = $properties($this);
        }
        return new Types\Object_Type($properties);
    }
    /**
     * Create a new array property instance.
     */
    public function array(): Types\Array_Type
    {
        return new Types\Array_Type();
    }
    /**
     * Create a new string property instance.
     */
    public function string(): Types\String_Type
    {
        return new Types\String_Type();
    }
    /**
     * Create a new integer property instance.
     */
    public function integer(): Types\Integer_Type
    {
        return new Types\Integer_Type();
    }
    /**
     * Create a new number property instance.
     */
    public function number(): Types\Number_Type
    {
        return new Types\Number_Type();
    }
    /**
     * Create a new boolean property instance.
     */
    public function boolean(): Types\Boolean_Type
    {
        return new Types\Boolean_Type();
    }
}