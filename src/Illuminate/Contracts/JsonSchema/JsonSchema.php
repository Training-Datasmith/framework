<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Json_Schema;

use Closure;
interface Json_Schema
{
    /**
     * Create a new object schema instance.
     *
     * @param  (Closure(JsonSchema): array<string, \Illuminate\JsonSchema\Types\Type>)|array<string, \Illuminate\JsonSchema\Types\Type>  $properties
     * @return \Illuminate\JsonSchema\Types\ObjectType
     */
    public function object(Closure|array $properties = []);
    /**
     * Create a new array property instance.
     *
     * @return \Illuminate\JsonSchema\Types\ArrayType
     */
    public function array();
    /**
     * Create a new string property instance.
     *
     * @return \Illuminate\JsonSchema\Types\StringType
     */
    public function string();
    /**
     * Create a new integer property instance.
     *
     * @return \Illuminate\JsonSchema\Types\IntegerType
     */
    public function integer();
    /**
     * Create a new number property instance.
     *
     * @return \Illuminate\JsonSchema\Types\NumberType
     */
    public function number();
    /**
     * Create a new boolean property instance.
     *
     * @return \Illuminate\JsonSchema\Types\BooleanType
     */
    public function boolean();
}