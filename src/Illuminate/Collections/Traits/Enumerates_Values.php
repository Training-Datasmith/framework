<?php

declare (strict_types=1);
namespace Illuminate\Support\Traits;

use Backed_Enum;
use Caching_Iterator;
use Closure;
use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Higher_Order_Collection_Proxy;
use JsonSerializable;
use UnexpectedValueException;
use Unit_Enum;
/**
 * @template TKey of array-key
 *
 * @template-covariant TValue
 *
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $average
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $avg
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $contains
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $doesntContain
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $each
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $every
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $filter
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $first
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $flatMap
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $groupBy
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $hasMany
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $hasSole
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $keyBy
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $last
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $map
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $max
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $min
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $partition
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $percentage
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $reject
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $skipUntil
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $skipWhile
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $some
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $sortBy
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $sortByDesc
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $sum
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $takeUntil
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $takeWhile
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $unique
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $unless
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $until
 * @property-read HigherOrderCollectionProxy<TKey, TValue> $when
 */
trait Enumerates_Values
{
    use Conditionable;
    /**
     * Indicates that the object's string representation should be escaped when __toString is invoked.
     *
     * @var bool
     */
    protected $escape_when_casting_to_string = false;
    /**
     * The methods that can be proxied.
     *
     * @var array<int, string>
     */
    protected static $proxies = ['average', 'avg', 'contains', 'doesntContain', 'each', 'every', 'filter', 'first', 'flatMap', 'groupBy', 'hasMany', 'hasSole', 'keyBy', 'last', 'map', 'max', 'min', 'partition', 'percentage', 'reject', 'skipUntil', 'skipWhile', 'some', 'sortBy', 'sortByDesc', 'sum', 'takeUntil', 'takeWhile', 'unique', 'unless', 'until', 'when'];
    /**
     * Create a new collection instance if the value isn't one already.
     *
     * @template TMakeKey of array-key
     * @template TMakeValue
     *
     * @param  \Illuminate\Contracts\Support\Arrayable<TMakeKey, TMakeValue>|iterable<TMakeKey, TMakeValue>|null  $items
     * @return static<TMakeKey, TMakeValue>
     */
    public static function make($items = []): static
    {
        return new static($items);
    }
    /**
     * Wrap the given value in a collection if applicable.
     *
     * @template TWrapValue
     *
     * @param  iterable<array-key, TWrapValue>|TWrapValue  $value
     * @return static<array-key, TWrapValue>
     */
    public static function wrap($value): static
    {
        return $value instanceof Enumerable ? new static($value) : new static(Arr::wrap($value));
    }
    /**
     * Get the underlying items from the given collection if applicable.
     *
     * @template TUnwrapKey of array-key
     * @template TUnwrapValue
     *
     * @param  array<TUnwrapKey, TUnwrapValue>|static<TUnwrapKey, TUnwrapValue>  $value
     * @return array<TUnwrapKey, TUnwrapValue>
     */
    public static function unwrap($value)
    {
        return $value instanceof Enumerable ? $value->all() : $value;
    }
    /**
     * Create a new instance with no items.
     */
    public static function empty(): static
    {
        return new static([]);
    }
    /**
     * Create a new collection by invoking the callback a given amount of times.
     *
     * @template TTimesValue
     *
     * @param  int  $number
     * @param  (callable(int): TTimesValue)|null  $callback
     * @return static<int, TTimesValue>
     */
    public static function times($number, ?callable $callback = null)
    {
        if ($number < 1) {
            return new static();
        }
        return static::range(1, $number)->unless($callback == null)->map($callback);
    }
    /**
     * Create a new collection by decoding a JSON string.
     *
     * @param  string  $json
     * @param  int  $depth
     * @param  int  $flags
     * @return static<TKey, TValue>
     */
    public static function from_json($json, $depth = 512, $flags = 0): static
    {
        return new static(json_decode($json, true, $depth, $flags));
    }
    /**
     * Get the average value of a given key.
     *
     * @param  (callable(TValue): float|int)|string|null  $callback
     */
    public function avg($callback = null): int|float|null
    {
        $callback = $this->value_retriever($callback);
        $reduced = $this->reduce(static function (&$reduce, $value) use ($callback) {
            if (!is_null($resolved = $callback($value))) {
                $reduce[0] += $resolved;
                $reduce[1]++;
            }
            return $reduce;
        }, [0, 0]);
        return $reduced[1] ? $reduced[0] / $reduced[1] : null;
    }
    /**
     * Alias for the "avg" method.
     *
     * @param  (callable(TValue): float|int)|string|null  $callback
     * @return float|int|null
     */
    public function average($callback = null)
    {
        return $this->avg($callback);
    }
    /**
     * Alias for the "contains" method.
     *
     * @param  (callable(TValue, TKey): bool)|TValue|string  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return bool
     */
    public function some($key, $operator = null, $value = null)
    {
        return $this->contains(...func_get_args());
    }
    /**
     * Dump the given arguments and terminate execution.
     *
     * @param  mixed  ...$args
     * @return never
     */
    public function dd(...$args): void
    {
        dd($this->all(), ...$args);
    }
    /**
     * Dump the items.
     *
     * @param  mixed  ...$args
     * @return $this
     */
    public function dump(...$args)
    {
        dump($this->all(), ...$args);
        return $this;
    }
    /**
     * Execute a callback over each item.
     *
     * @param  callable(TValue, TKey): mixed  $callback
     * @return $this
     */
    public function each(callable $callback)
    {
        foreach ($this as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }
        return $this;
    }
    /**
     * Execute a callback over each nested chunk of items.
     *
     * @param  callable(...mixed): mixed  $callback
     * @return static
     */
    public function each_spread(callable $callback)
    {
        return $this->each(function ($chunk, $key) use ($callback) {
            $chunk[] = $key;
            return $callback(...$chunk);
        });
    }
    /**
     * Determine if all items pass the given truth test.
     *
     * @param  (callable(TValue, TKey): bool)|TValue|string  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return bool
     */
    public function every($key, $operator = null, $value = null)
    {
        if (func_num_args() === 1) {
            $callback = $this->value_retriever($key);
            foreach ($this as $k => $v) {
                if (!$callback($v, $k)) {
                    return false;
                }
            }
            return true;
        }
        return $this->every($this->operator_for_where(...func_get_args()));
    }
    /**
     * Get the first item by the given key value pair.
     *
     * @param  callable|string  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return TValue|null
     */
    public function first_where($key, $operator = null, $value = null)
    {
        return $this->first($this->operator_for_where(...func_get_args()));
    }
    /**
     * Determine if the collection contains multiple items, optionally matching the given criteria.
     *
     * @param  (callable(TValue, TKey): bool)|string|null  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     */
    public function has_many($key = null, $operator = null, $value = null): bool
    {
        $filter = func_num_args() > 1 ? $this->operator_for_where(...func_get_args()) : $key;
        return $this->unless($filter == null)->filter($filter)->take(2)->count() === 2;
    }
    /**
     * Get a single key's value from the first matching item in the collection.
     *
     * @template TValueDefault
     *
     * @param  string  $key
     * @param  TValueDefault|(\Closure(): TValueDefault)  $default
     * @return TValue|TValueDefault
     */
    public function value($key, $default = null)
    {
        $value = $this->first(fn(array $target): bool => data_has($target, $key));
        return data_get($value, $key, $default);
    }
    /**
     * Ensure that every item in the collection is of the expected type.
     *
     * @template TEnsureOfType
     *
     * @param  class-string<TEnsureOfType>|array<array-key, class-string<TEnsureOfType>>|'string'|'int'|'float'|'bool'|'array'|'null'  $type
     * @return static<TKey, TEnsureOfType>
     *
     * @throws \UnexpectedValueException
     */
    public function ensure($type)
    {
        $allowed_types = is_array($type) ? $type : [$type];
        return $this->each(function ($item, $index) use ($allowed_types): true {
            $item_type = get_debug_type($item);
            foreach ($allowed_types as $allowed_type) {
                if ($item_type === $allowed_type || $item instanceof $allowed_type) {
                    return true;
                }
            }
            throw new UnexpectedValueException(sprintf("Collection should only include [%s] items, but '%s' found at position %d.", implode(', ', $allowed_types), $item_type, $index));
        });
    }
    /**
     * Determine if the collection is not empty.
     *
     * @phpstan-assert-if-true TValue $this->first()
     * @phpstan-assert-if-true TValue $this->last()
     *
     * @phpstan-assert-if-false null $this->first()
     * @phpstan-assert-if-false null $this->last()
     */
    public function is_not_empty(): bool
    {
        return !$this->is_empty();
    }
    /**
     * Run a map over each nested chunk of items.
     *
     * @template TMapSpreadValue
     *
     * @param  callable(mixed...): TMapSpreadValue  $callback
     * @return static<TKey, TMapSpreadValue>
     */
    public function map_spread(callable $callback)
    {
        return $this->map(function ($chunk, $key) use ($callback) {
            $chunk[] = $key;
            return $callback(...$chunk);
        });
    }
    /**
     * Run a grouping map over the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @template TMapToGroupsKey of array-key
     * @template TMapToGroupsValue
     *
     * @param  callable(TValue, TKey): array<TMapToGroupsKey, TMapToGroupsValue>  $callback
     * @return static<TMapToGroupsKey, static<int, TMapToGroupsValue>>
     */
    public function map_to_groups(callable $callback)
    {
        $groups = $this->map_to_dictionary($callback);
        return $groups->map($this->make(...));
    }
    /**
     * Map a collection and flatten the result by a single level.
     *
     * @template TFlatMapKey of array-key
     * @template TFlatMapValue
     *
     * @param  callable(TValue, TKey): (\Illuminate\Support\Collection<TFlatMapKey, TFlatMapValue>|array<TFlatMapKey, TFlatMapValue>)  $callback
     * @return static<TFlatMapKey, TFlatMapValue>
     */
    public function flat_map(callable $callback)
    {
        return $this->map($callback)->collapse();
    }
    /**
     * Map the values into a new class.
     *
     * @template TMapIntoValue
     *
     * @param  class-string<TMapIntoValue>  $class
     * @return static<TKey, TMapIntoValue>
     */
    public function map_into($class)
    {
        if (is_subclass_of($class, Backed_Enum::class)) {
            return $this->map(fn($value, $key): \Backed_Enum => $class::from($value));
        }
        return $this->map(fn($value, $key): object => new $class($value, $key));
    }
    /**
     * Get the min value of a given key.
     *
     * @param  (callable(TValue):mixed)|string|null  $callback
     * @return mixed
     */
    public function min($callback = null)
    {
        $callback = $this->value_retriever($callback);
        return $this->map(fn($value) => $callback($value))->reject(fn($value): bool => is_null($value))->reduce(fn($result, $value) => is_null($result) || $value < $result ? $value : $result);
    }
    /**
     * Get the max value of a given key.
     *
     * @param  (callable(TValue):mixed)|string|null  $callback
     * @return mixed
     */
    public function max($callback = null)
    {
        $callback = $this->value_retriever($callback);
        return $this->reject(fn($value): bool => is_null($value))->reduce(function ($result, $item) use ($callback) {
            $value = $callback($item);
            return is_null($result) || $value > $result ? $value : $result;
        });
    }
    /**
     * "Paginate" the collection by slicing it into a smaller collection.
     *
     * @param  int  $page
     * @param  int  $perPage
     * @return static
     */
    public function for_page($page, $per_page)
    {
        $offset = max(0, ($page - 1) * $per_page);
        return $this->slice($offset, $per_page);
    }
    /**
     * Partition the collection into two arrays using the given callback or key.
     *
     * @param  (callable(TValue, TKey): bool)|TValue|string  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return static<int<0, 1>, static<TKey, TValue>>
     */
    public function partition($key, $operator = null, $value = null): static
    {
        $callback = func_num_args() === 1 ? $this->value_retriever($key) : $this->operator_for_where(...func_get_args());
        [$passed, $failed] = Arr::partition($this->getIterator(), $callback);
        return new static([new static($passed), new static($failed)]);
    }
    /**
     * Calculate the percentage of items that pass a given truth test.
     *
     * @param  (callable(TValue, TKey): bool)  $callback
     */
    public function percentage(callable $callback, int $precision = 2): ?float
    {
        if ($this->is_empty()) {
            return null;
        }
        return round($this->filter($callback)->count() / $this->count() * 100, $precision);
    }
    /**
     * Get the sum of the given values.
     *
     * @template TReturnType
     *
     * @param  (callable(TValue): TReturnType)|string|null  $callback
     * @return ($callback is callable ? TReturnType : mixed)
     */
    public function sum($callback = null)
    {
        $callback = is_null($callback) ? $this->identity() : $this->value_retriever($callback);
        return $this->reduce(fn($result, $item): float|int|array => $result + $callback($item), 0);
    }
    /**
     * Apply the callback if the collection is empty.
     *
     * @template TWhenEmptyReturnType
     *
     * @param  (callable($this): TWhenEmptyReturnType)  $callback
     * @param  (callable($this): TWhenEmptyReturnType)|null  $default
     * @return $this|TWhenEmptyReturnType
     */
    public function when_empty(callable $callback, ?callable $default = null)
    {
        return $this->when($this->is_empty(), $callback, $default);
    }
    /**
     * Apply the callback if the collection is not empty.
     *
     * @template TWhenNotEmptyReturnType
     *
     * @param  callable($this): TWhenNotEmptyReturnType  $callback
     * @param  (callable($this): TWhenNotEmptyReturnType)|null  $default
     * @return $this|TWhenNotEmptyReturnType
     */
    public function when_not_empty(callable $callback, ?callable $default = null)
    {
        return $this->when($this->is_not_empty(), $callback, $default);
    }
    /**
     * Apply the callback unless the collection is empty.
     *
     * @template TUnlessEmptyReturnType
     *
     * @param  callable($this): TUnlessEmptyReturnType  $callback
     * @param  (callable($this): TUnlessEmptyReturnType)|null  $default
     * @return $this|TUnlessEmptyReturnType
     */
    public function unless_empty(callable $callback, ?callable $default = null)
    {
        return $this->when_not_empty($callback, $default);
    }
    /**
     * Apply the callback unless the collection is not empty.
     *
     * @template TUnlessNotEmptyReturnType
     *
     * @param  callable($this): TUnlessNotEmptyReturnType  $callback
     * @param  (callable($this): TUnlessNotEmptyReturnType)|null  $default
     * @return $this|TUnlessNotEmptyReturnType
     */
    public function unless_not_empty(callable $callback, ?callable $default = null)
    {
        return $this->when_empty($callback, $default);
    }
    /**
     * Filter items by the given key value pair.
     *
     * @param  callable|string  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return static
     */
    public function where($key, $operator = null, $value = null)
    {
        return $this->filter($this->operator_for_where(...func_get_args()));
    }
    /**
     * Filter items where the value for the given key is null.
     *
     * @param  string|null  $key
     * @return static
     */
    public function where_null($key = null)
    {
        return $this->where_strict($key, null);
    }
    /**
     * Filter items where the value for the given key is not null.
     *
     * @param  string|null  $key
     * @return static
     */
    public function where_not_null($key = null)
    {
        return $this->where($key, '!==', null);
    }
    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return static
     */
    public function where_strict($key, $value)
    {
        return $this->where($key, '===', $value);
    }
    /**
     * Filter items by the given key value pair.
     *
     * @param  string  $key
     * @param  \Illuminate\Contracts\Support\Arrayable|iterable  $values
     * @param  bool  $strict
     * @return static
     */
    public function where_in($key, $values, $strict = false)
    {
        $values = $this->get_arrayable_items($values);
        return $this->filter(fn(array $item): bool => in_array(data_get($item, $key), $values, $strict));
    }
    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param  string  $key
     * @param  \Illuminate\Contracts\Support\Arrayable|iterable  $values
     * @return static
     */
    public function where_in_strict($key, $values)
    {
        return $this->where_in($key, $values, true);
    }
    /**
     * Filter items such that the value of the given key is between the given values.
     *
     * @param  string  $key
     * @param  \Illuminate\Contracts\Support\Arrayable|iterable  $values
     * @return static
     */
    public function where_between($key, $values)
    {
        return $this->where($key, '>=', reset($values))->where($key, '<=', end($values));
    }
    /**
     * Filter items such that the value of the given key is not between the given values.
     *
     * @param  string  $key
     * @param  \Illuminate\Contracts\Support\Arrayable|iterable  $values
     * @return static
     */
    public function where_not_between($key, $values)
    {
        return $this->filter(fn(array $item): bool => data_get($item, $key) < reset($values) || data_get($item, $key) > end($values));
    }
    /**
     * Filter items by the given key value pair.
     *
     * @param  string  $key
     * @param  \Illuminate\Contracts\Support\Arrayable|iterable  $values
     * @param  bool  $strict
     * @return static
     */
    public function where_not_in($key, $values, $strict = false)
    {
        $values = $this->get_arrayable_items($values);
        return $this->reject(fn(array $item): bool => in_array(data_get($item, $key), $values, $strict));
    }
    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param  string  $key
     * @param  \Illuminate\Contracts\Support\Arrayable|iterable  $values
     * @return static
     */
    public function where_not_in_strict($key, $values)
    {
        return $this->where_not_in($key, $values, true);
    }
    /**
     * Filter the items, removing any items that don't match the given type(s).
     *
     * @template TWhereInstanceOf
     *
     * @param  class-string<TWhereInstanceOf>|array<array-key, class-string<TWhereInstanceOf>>  $type
     * @return static<TKey, TWhereInstanceOf>
     */
    public function where_instance_of($type)
    {
        return $this->filter(function ($value) use ($type): bool {
            if (is_array($type)) {
                foreach ($type as $class_type) {
                    if ($value instanceof $class_type) {
                        return true;
                    }
                }
                return false;
            }
            return $value instanceof $type;
        });
    }
    /**
     * Pass the collection to the given callback and return the result.
     *
     * @template TPipeReturnType
     *
     * @param  callable($this): TPipeReturnType  $callback
     * @return TPipeReturnType
     */
    public function pipe(callable $callback)
    {
        return $callback($this);
    }
    /**
     * Pass the collection into a new class.
     *
     * @template TPipeIntoValue
     *
     * @param  class-string<TPipeIntoValue>  $class
     * @return TPipeIntoValue
     */
    public function pipe_into($class)
    {
        return new $class($this);
    }
    /**
     * Pass the collection through a series of callable pipes and return the result.
     *
     * @param  array<callable>  $callbacks
     * @return mixed
     */
    public function pipe_through($callbacks)
    {
        return (new Collection($callbacks))->reduce(fn($carry, $callback) => $callback($carry), $this);
    }
    /**
     * Reduce the collection to a single value.
     *
     * @template TReduceInitial
     * @template TReduceReturnType
     *
     * @param  callable(TReduceInitial|TReduceReturnType, TValue, TKey): TReduceReturnType  $callback
     * @param  TReduceInitial  $initial
     * @return TReduceReturnType
     */
    public function reduce(callable $callback, $initial = null)
    {
        $result = $initial;
        foreach ($this as $key => $value) {
            $result = $callback($result, $value, $key);
        }
        return $result;
    }
    /**
     * Reduce the collection to multiple aggregate values.
     *
     * @param  mixed  ...$initial
     * @return array
     * @throws \UnexpectedValueException
     */
    public function reduce_spread(callable $callback, ...$initial)
    {
        $result = $initial;
        foreach ($this as $key => $value) {
            $result = call_user_func_array($callback, array_merge($result, [$value, $key]));
            if (!is_array($result)) {
                throw new UnexpectedValueException(sprintf("%s::reduceSpread expects reducer to return an array, but got a '%s' instead.", class_basename(static::class), gettype($result)));
            }
        }
        return $result;
    }
    /**
     * Reduce an associative collection to a single value.
     *
     * @template TReduceWithKeysInitial
     * @template TReduceWithKeysReturnType
     *
     * @param  callable(TReduceWithKeysInitial|TReduceWithKeysReturnType, TValue, TKey): TReduceWithKeysReturnType  $callback
     * @param  TReduceWithKeysInitial  $initial
     * @return TReduceWithKeysReturnType
     */
    public function reduce_with_keys(callable $callback, $initial = null)
    {
        return $this->reduce($callback, $initial);
    }
    /**
     * Create a collection of all elements that do not pass a given truth test.
     *
     * @param  (callable(TValue, TKey): bool)|bool|TValue  $callback
     * @return static
     */
    public function reject($callback = true)
    {
        $use_as_callable = $this->use_as_callable($callback);
        return $this->filter(fn($value, $key): bool => $use_as_callable ? !$callback($value, $key) : $value != $callback);
    }
    /**
     * Pass the collection to the given callback and then return it.
     *
     * @param  callable($this): mixed  $callback
     * @return $this
     */
    public function tap(callable $callback)
    {
        $callback($this);
        return $this;
    }
    /**
     * Return only unique items from the collection array.
     *
     * @param  (callable(TValue, TKey): mixed)|string|null  $key
     * @param  bool  $strict
     * @return static
     */
    public function unique($key = null, $strict = false)
    {
        $callback = $this->value_retriever($key);
        $exists = [];
        return $this->reject(function ($item, $key) use ($callback, $strict, &$exists) {
            if (in_array($id = $callback($item, $key), $exists, $strict)) {
                return true;
            }
            $exists[] = $id;
        });
    }
    /**
     * Return only unique items from the collection array using strict comparison.
     *
     * @param  (callable(TValue, TKey): mixed)|string|null  $key
     * @return static
     */
    public function unique_strict($key = null)
    {
        return $this->unique($key, true);
    }
    /**
     * Collect the values into a collection.
     *
     * @return \Illuminate\Support\Collection<TKey, TValue>
     */
    public function collect(): \Illuminate\Support\Collection
    {
        return new Collection($this->all());
    }
    /**
     * Get the collection of items as a plain array.
     *
     * @return array<TKey, mixed>
     */
    public function to_array()
    {
        return $this->map(fn($value) => $value instanceof Arrayable ? $value->to_array() : $value)->all();
    }
    /**
     * Convert the object into something JSON serializable.
     *
     * @return array<TKey, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_map(fn($value) => match (true) {
            $value instanceof JsonSerializable => $value->jsonSerialize(),
            $value instanceof Jsonable => json_decode($value->to_json(), true),
            $value instanceof Arrayable => $value->to_array(),
            default => $value,
        }, $this->all());
    }
    /**
     * Get the collection of items as JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function to_json($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }
    /**
     * Get the collection of items as pretty print formatted JSON.
     *
     * @return string
     */
    public function to_pretty_json(int $options = 0)
    {
        return $this->to_json(JSON_PRETTY_PRINT | $options);
    }
    /**
     * Get a CachingIterator instance.
     *
     * @param  int  $flags
     */
    public function get_caching_iterator($flags = Caching_Iterator::CALL_TOSTRING): \Caching_Iterator
    {
        return new Caching_Iterator($this->getIterator(), $flags);
    }
    /**
     * Convert the collection to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->escape_when_casting_to_string ? e($this->to_json()) : $this->to_json();
    }
    /**
     * Indicate that the model's string representation should be escaped when __toString is invoked.
     *
     * @param  bool  $escape
     * @return $this
     */
    public function escape_when_casting_to_string($escape = true)
    {
        $this->escape_when_casting_to_string = $escape;
        return $this;
    }
    /**
     * Add a method to the list of proxied methods.
     *
     * @param  string  $method
     */
    public static function proxy($method): void
    {
        static::$proxies[] = $method;
    }
    /**
     * Dynamically access collection proxies.
     *
     * @param  string  $key
     * @return mixed
     *
     * @throws \Exception
     */
    public function __get($key)
    {
        if (!in_array($key, static::$proxies)) {
            throw new Exception("Property [{$key}] does not exist on this collection instance.");
        }
        return new Higher_Order_Collection_Proxy($this, $key);
    }
    /**
     * Results array of items from Collection or Arrayable.
     *
     * @param  mixed  $items
     * @return array<TKey, TValue>
     */
    protected function get_arrayable_items($items)
    {
        return is_null($items) || is_scalar($items) || $items instanceof Unit_Enum ? Arr::wrap($items) : Arr::from($items);
    }
    /**
     * Get an operator checker callback.
     *
     * @param  callable|string  $key
     * @param  string|null  $operator
     * @param  mixed  $value
     * @return \Closure
     */
    protected function operator_for_where($key, $operator = null, $value = null)
    {
        if ($this->use_as_callable($key)) {
            return $key;
        }
        if (func_num_args() === 1) {
            $value = true;
            $operator = '=';
        }
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        return function (array $item) use ($key, $operator, $value) {
            $retrieved = enum_value(data_get($item, $key));
            $value = enum_value($value);
            $strings = array_filter([$retrieved, $value], fn($value): bool => match (true) {
                is_string($value) => true,
                $value instanceof \Stringable => true,
                default => false,
            });
            if (count($strings) < 2 && count(array_filter([$retrieved, $value], is_object(...))) == 1) {
                return in_array($operator, ['!=', '<>', '!==']);
            }
            switch ($operator) {
                default:
                case '=':
                case '==':
                    return $retrieved == $value;
                case '!=':
                case '<>':
                    return $retrieved != $value;
                case '<':
                    return $retrieved < $value;
                case '>':
                    return $retrieved > $value;
                case '<=':
                    return $retrieved <= $value;
                case '>=':
                    return $retrieved >= $value;
                case '===':
                    return $retrieved === $value;
                case '!==':
                    return $retrieved !== $value;
                case '<=>':
                    return $retrieved <=> $value;
            }
        };
    }
    /**
     * Determine if the given value is callable, but not a string.
     *
     * @param  mixed  $value
     */
    protected function use_as_callable($value): bool
    {
        return !is_string($value) && is_callable($value);
    }
    /**
     * Get a value retrieving callback.
     *
     * @param  callable|string|null  $value
     * @return callable
     */
    protected function value_retriever($value)
    {
        if ($this->use_as_callable($value)) {
            return $value;
        }
        return fn(array $item) => data_get($item, $value);
    }
    /**
     * Make a function to check an item's equality.
     *
     * @param  mixed  $value
     * @return \Closure(mixed): bool
     */
    protected function equality($value)
    {
        return fn($item): bool => $item === $value;
    }
    /**
     * Make a function using another function, by negating its result.
     *
     * @return \Closure
     */
    protected function negate(Closure $callback)
    {
        return fn(...$params): bool => !$callback(...$params);
    }
    /**
     * Make a function that returns what's passed to it.
     *
     * @return \Closure(TValue): TValue
     */
    protected function identity()
    {
        return fn($value) => $value;
    }
}