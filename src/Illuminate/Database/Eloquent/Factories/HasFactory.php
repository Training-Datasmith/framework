<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Factories;

use Illuminate\Database\Eloquent\Attributes\Use_Factory;
/**
 * @template TFactory of \Illuminate\Database\Eloquent\Factories\Factory
 */
trait Has_Factory
{
    /**
     * Get a new factory instance for the model.
     *
     * @param  (callable(array<string, mixed>, static|null): array<string, mixed>)|array<string, mixed>|int|null  $count
     * @param  (callable(array<string, mixed>, static|null): array<string, mixed>)|array<string, mixed>  $state
     * @return TFactory
     */
    public static function factory($count = null, $state = [])
    {
        $factory = static::new_factory() ?? Factory::factory_for_model(static::class);
        return $factory->count(is_numeric($count) ? $count : null)->state(is_callable($count) || is_array($count) ? $count : $state);
    }
    /**
     * Create a new factory instance for the model.
     *
     * @return TFactory|null
     */
    protected static function new_factory()
    {
        if (isset(static::$factory)) {
            return static::$factory::new();
        }
        return static::get_use_factory_attribute() ?? null;
    }
    /**
     * Get the factory from the UseFactory class attribute.
     *
     * @return TFactory|null
     */
    protected static function get_use_factory_attribute()
    {
        $attributes = (new \ReflectionClass(static::class))->get_attributes(Use_Factory::class);
        if ($attributes !== []) {
            $use_factory = $attributes[0]->new_instance();
            $factory = $use_factory->factory_class::new();
            $factory->guess_model_names_using(fn(): string => static::class);
            return $factory;
        }
    }
}