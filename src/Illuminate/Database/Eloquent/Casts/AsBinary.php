<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\Casts_Attributes;
use Illuminate\Support\Binary_Codec;
use InvalidArgumentException;
class As_Binary implements Castable
{
    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @param  array{string}  $arguments
     */
    public static function cast_using(array $arguments): \Illuminate\Contracts\Database\Eloquent\Casts_Attributes
    {
        return new class($arguments) implements Casts_Attributes
        {
            protected string $format;
            public function __construct(protected array $arguments)
            {
                $this->format = $this->arguments[0] ?? throw new InvalidArgumentException('The binary codec format is required.');
                if (!in_array($this->format, Binary_Codec::formats(), true)) {
                    throw new InvalidArgumentException(sprintf('Unsupported binary codec format [%s]. Allowed formats are: %s.', $this->format, implode(', ', Binary_Codec::formats())));
                }
            }
            public function get($model, $key, $value, $attributes): ?string
            {
                return Binary_Codec::decode($attributes[$key] ?? null, $this->format);
            }
            public function set($model, $key, $value, $attributes): array
            {
                return [$key => Binary_Codec::encode($value, $this->format)];
            }
        };
    }
    /**
     * Encode / decode values as binary UUIDs.
     */
    public static function uuid(): string
    {
        return self::class . ':uuid';
    }
    /**
     * Encode / decode values as binary ULIDs.
     */
    public static function ulid(): string
    {
        return self::class . ':ulid';
    }
    /**
     * Encode / decode values using the given format.
     */
    public static function of(string $format): string
    {
        return self::class . ':' . $format;
    }
}