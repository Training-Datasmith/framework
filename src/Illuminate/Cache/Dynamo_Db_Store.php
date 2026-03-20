<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Aws\Dynamo_Db\Dynamo_Db_Client;
use Aws\Dynamo_Db\Exception\Dynamo_Db_Exception;
use Illuminate\Contracts\Cache\Lock_Provider;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Interacts_With_Time;
use Illuminate\Support\Str;
use RuntimeException;
class Dynamo_Db_Store implements Lock_Provider, Store
{
    use Interacts_With_Time;
    /**
     * A string that should be prepended to keys.
     *
     * @var string
     */
    protected $prefix;
    /**
     * Create a new store instance.
     *
     * @param  \Aws\DynamoDb\DynamoDbClient  $dynamo  The DynamoDB client instance.
     * @param  string  $table  The table name.
     * @param  string  $keyAttribute  The name of the attribute that should hold the key.
     * @param  string  $valueAttribute  The name of the attribute that should hold the value.
     * @param  string  $expirationAttribute  The name of the attribute that should hold the expiration timestamp.
     * @param  string  $prefix
     * @param  array|bool|null  $serializableClasses
     */
    public function __construct(
        protected Dynamo_Db_Client $dynamo,
        protected $table,
        protected $key_attribute = 'key',
        protected $value_attribute = 'value',
        protected $expiration_attribute = 'expires_at',
        $prefix = '',
        /**
         * The classes that should be allowed during unserialization.
         */
        protected $serializable_classes = null
    )
    {
        $this->set_prefix($prefix);
    }
    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        $response = $this->dynamo->get_item(['TableName' => $this->table, 'ConsistentRead' => false, 'Key' => [$this->key_attribute => ['S' => $this->prefix . $key]]]);
        if (!isset($response['Item'])) {
            return;
        }
        if ($this->is_expired($response['Item'])) {
            return;
        }
        if (isset($response['Item'][$this->value_attribute])) {
            return $this->unserialize($response['Item'][$this->value_attribute]['S'] ?? $response['Item'][$this->value_attribute]['N'] ?? null);
        }
    }
    /**
     * Retrieve multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     */
    public function many(array $keys): array
    {
        if (count($keys) === 0) {
            return [];
        }
        $prefixed_keys = array_map(fn($key): string => $this->prefix . $key, $keys);
        $response = $this->dynamo->batch_get_item(['RequestItems' => [$this->table => ['ConsistentRead' => false, 'Keys' => (new Collection($prefixed_keys))->map(fn($key): array => [$this->key_attribute => ['S' => $key]])->all()]]]);
        $now = Carbon::now();
        return array_merge(Arr::map_with_keys($keys, fn($key): array => [$key => null]), (new Collection($response['Responses'][$this->table]))->map_with_keys(function (array $response) use ($now): array {
            if ($this->is_expired($response, $now)) {
                $value = null;
            } else {
                $value = $this->unserialize($response[$this->value_attribute]['S'] ?? $response[$this->value_attribute]['N'] ?? null);
            }
            return [Str::replace_first($this->prefix, '', $response[$this->key_attribute]['S']) => $value];
        })->all());
    }
    /**
     * Determine if the given item is expired.
     *
     * @param  \DateTimeInterface|null  $expiration
     */
    protected function is_expired(array $item, $expiration = null): bool
    {
        $expiration = $expiration ?: Carbon::now();
        return isset($item[$this->expiration_attribute]) && $expiration->get_timestamp() >= $item[$this->expiration_attribute]['N'];
    }
    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $seconds
     */
    public function put($key, $value, $seconds): bool
    {
        $this->dynamo->put_item(['TableName' => $this->table, 'Item' => [$this->key_attribute => ['S' => $this->prefix . $key], $this->value_attribute => [$this->type($value) => $this->serialize($value)], $this->expiration_attribute => ['N' => (string) $this->to_timestamp($seconds)]]]);
        return true;
    }
    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param  int  $seconds
     */
    public function put_many(array $values, $seconds): bool
    {
        if (count($values) === 0) {
            return true;
        }
        $expiration = $this->to_timestamp($seconds);
        $this->dynamo->batch_write_item(['RequestItems' => [$this->table => (new Collection($values))->map(fn($value, $key): array => ['PutRequest' => ['Item' => [$this->key_attribute => ['S' => $this->prefix . $key], $this->value_attribute => [$this->type($value) => $this->serialize($value)], $this->expiration_attribute => ['N' => (string) $expiration]]]])->values()->all()]]);
        return true;
    }
    /**
     * Store an item in the cache if the key doesn't exist.
     *
     * @param  mixed  $value
     * @param  int  $seconds
     */
    public function add(string $key, $value, $seconds): bool
    {
        try {
            $this->dynamo->put_item(['TableName' => $this->table, 'Item' => [$this->key_attribute => ['S' => $this->prefix . $key], $this->value_attribute => [$this->type($value) => $this->serialize($value)], $this->expiration_attribute => ['N' => (string) $this->to_timestamp($seconds)]], 'ConditionExpression' => 'attribute_not_exists(#key) OR #expires_at < :now', 'ExpressionAttributeNames' => ['#key' => $this->key_attribute, '#expires_at' => $this->expiration_attribute], 'ExpressionAttributeValues' => [':now' => ['N' => (string) $this->current_time()]]]);
            return true;
        } catch (Dynamo_Db_Exception $e) {
            if (str_contains($e->get_message(), 'ConditionalCheckFailed')) {
                return false;
            }
            throw $e;
        }
    }
    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|false
     *
     * @throws \Aws\DynamoDb\Exception\DynamoDbException
     */
    public function increment($key, $value = 1): int|false
    {
        try {
            $response = $this->dynamo->update_item(['TableName' => $this->table, 'Key' => [$this->key_attribute => ['S' => $this->prefix . $key]], 'ConditionExpression' => 'attribute_exists(#key) AND #expires_at > :now', 'UpdateExpression' => 'SET #value = #value + :amount', 'ExpressionAttributeNames' => ['#key' => $this->key_attribute, '#value' => $this->value_attribute, '#expires_at' => $this->expiration_attribute], 'ExpressionAttributeValues' => [':now' => ['N' => (string) $this->current_time()], ':amount' => ['N' => (string) $value]], 'ReturnValues' => 'UPDATED_NEW']);
            return (int) $response['Attributes'][$this->value_attribute]['N'];
        } catch (Dynamo_Db_Exception $e) {
            if (str_contains($e->get_message(), 'ConditionalCheckFailed')) {
                return false;
            }
            throw $e;
        }
    }
    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|false
     *
     * @throws \Aws\DynamoDb\Exception\DynamoDbException
     */
    public function decrement($key, $value = 1): int|false
    {
        try {
            $response = $this->dynamo->update_item(['TableName' => $this->table, 'Key' => [$this->key_attribute => ['S' => $this->prefix . $key]], 'ConditionExpression' => 'attribute_exists(#key) AND #expires_at > :now', 'UpdateExpression' => 'SET #value = #value - :amount', 'ExpressionAttributeNames' => ['#key' => $this->key_attribute, '#value' => $this->value_attribute, '#expires_at' => $this->expiration_attribute], 'ExpressionAttributeValues' => [':now' => ['N' => (string) $this->current_time()], ':amount' => ['N' => (string) $value]], 'ReturnValues' => 'UPDATED_NEW']);
            return (int) $response['Attributes'][$this->value_attribute]['N'];
        } catch (Dynamo_Db_Exception $e) {
            if (str_contains($e->get_message(), 'ConditionalCheckFailed')) {
                return false;
            }
            throw $e;
        }
    }
    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    public function forever($key, $value): bool
    {
        return $this->put($key, $value, Carbon::now()->add_years(5)->get_timestamp());
    }
    /**
     * Get a lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function lock($name, $seconds = 0, $owner = null): \Illuminate\Cache\Dynamo_Db_Lock
    {
        return new Dynamo_Db_Lock($this, $name, $seconds, $owner);
    }
    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function restore_lock($name, $owner): \Illuminate\Cache\Dynamo_Db_Lock
    {
        return $this->lock($name, 0, $owner);
    }
    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     */
    public function forget($key): bool
    {
        $this->dynamo->delete_item(['TableName' => $this->table, 'Key' => [$this->key_attribute => ['S' => $this->prefix . $key]]]);
        return true;
    }
    /**
     * Remove all items from the cache.
     *
     *
     * @throws \RuntimeException
     */
    public function flush(): never
    {
        throw new RuntimeException('DynamoDb does not support flushing an entire table. Please create a new table.');
    }
    /**
     * Get the UNIX timestamp for the given number of seconds.
     *
     * @param  int  $seconds
     * @return int
     */
    protected function to_timestamp($seconds)
    {
        return $seconds > 0 ? $this->available_at($seconds) : $this->current_time();
    }
    /**
     * Serialize the value.
     *
     * @param  mixed  $value
     */
    protected function serialize($value): string
    {
        return is_numeric($value) ? (string) $value : serialize($value);
    }
    /**
     * Unserialize the value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function unserialize($value)
    {
        if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
            return (int) $value;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        if ($this->serializable_classes !== null) {
            return unserialize($value, ['allowed_classes' => $this->serializable_classes]);
        }
        return unserialize($value);
    }
    /**
     * Get the DynamoDB type for the given value.
     *
     * @param  mixed  $value
     */
    protected function type($value): string
    {
        return is_numeric($value) ? 'N' : 'S';
    }
    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function get_prefix()
    {
        return $this->prefix;
    }
    /**
     * Set the cache key prefix.
     *
     * @param  string  $prefix
     */
    public function set_prefix($prefix): void
    {
        $this->prefix = $prefix;
    }
    /**
     * Get the DynamoDb Client instance.
     */
    public function get_client(): \Aws\Dynamo_Db\Dynamo_Db_Client
    {
        return $this->dynamo;
    }
}