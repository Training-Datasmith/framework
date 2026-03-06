<?php

declare(strict_types=1);

namespace Illuminate\Cache;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Support\Str;
use RuntimeException;

class DynamoDbStore implements LockProvider, Store
{
    use InteractsWithTime;

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
        protected DynamoDbClient $dynamo,
        protected $table,
        protected $keyAttribute = 'key',
        protected $valueAttribute = 'value',
        protected $expirationAttribute = 'expires_at',
        $prefix = '',
        /**
         * The classes that should be allowed during unserialization.
         */
        protected $serializableClasses = null,
    ) {
        $this->setPrefix($prefix);
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        $response = $this->dynamo->getItem([
            'TableName' => $this->table,
            'ConsistentRead' => false,
            'Key' => [
                $this->keyAttribute => [
                    'S' => $this->prefix.$key,
                ],
            ],
        ]);

        if (! isset($response['Item'])) {
            return;
        }

        if ($this->isExpired($response['Item'])) {
            return;
        }

        if (isset($response['Item'][$this->valueAttribute])) {
            return $this->unserialize(
                $response['Item'][$this->valueAttribute]['S'] ??
                $response['Item'][$this->valueAttribute]['N'] ??
                null
            );
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

        $prefixedKeys = array_map(fn ($key): string => $this->prefix.$key, $keys);

        $response = $this->dynamo->batchGetItem([
            'RequestItems' => [
                $this->table => [
                    'ConsistentRead' => false,
                    'Keys' => (new Collection($prefixedKeys))->map(fn ($key): array => [
                        $this->keyAttribute => [
                            'S' => $key,
                        ],
                    ])->all(),
                ],
            ],
        ]);

        $now = Carbon::now();

        return array_merge(
            Arr::mapWithKeys($keys, fn ($key): array => [$key => null]),
            (new Collection($response['Responses'][$this->table]))->mapWithKeys(function (array $response) use ($now): array {
                if ($this->isExpired($response, $now)) {
                    $value = null;
                } else {
                    $value = $this->unserialize(
                        $response[$this->valueAttribute]['S'] ??
                        $response[$this->valueAttribute]['N'] ??
                        null
                    );
                }

                return [Str::replaceFirst($this->prefix, '', $response[$this->keyAttribute]['S']) => $value];
            })->all()
        );
    }

    /**
     * Determine if the given item is expired.
     *
     * @param  \DateTimeInterface|null  $expiration
     */
    protected function isExpired(array $item, $expiration = null): bool
    {
        $expiration = $expiration ?: Carbon::now();

        return isset($item[$this->expirationAttribute]) &&
               $expiration->getTimestamp() >= $item[$this->expirationAttribute]['N'];
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
        $this->dynamo->putItem([
            'TableName' => $this->table,
            'Item' => [
                $this->keyAttribute => [
                    'S' => $this->prefix.$key,
                ],
                $this->valueAttribute => [
                    $this->type($value) => $this->serialize($value),
                ],
                $this->expirationAttribute => [
                    'N' => (string) $this->toTimestamp($seconds),
                ],
            ],
        ]);

        return true;
    }

    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param  int  $seconds
     */
    public function putMany(array $values, $seconds): bool
    {
        if (count($values) === 0) {
            return true;
        }

        $expiration = $this->toTimestamp($seconds);

        $this->dynamo->batchWriteItem([
            'RequestItems' => [
                $this->table => (new Collection($values))->map(fn ($value, $key): array => [
                    'PutRequest' => [
                        'Item' => [
                            $this->keyAttribute => [
                                'S' => $this->prefix.$key,
                            ],
                            $this->valueAttribute => [
                                $this->type($value) => $this->serialize($value),
                            ],
                            $this->expirationAttribute => [
                                'N' => (string) $expiration,
                            ],
                        ],
                    ],
                ])->values()->all(),
            ],
        ]);

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
            $this->dynamo->putItem([
                'TableName' => $this->table,
                'Item' => [
                    $this->keyAttribute => [
                        'S' => $this->prefix.$key,
                    ],
                    $this->valueAttribute => [
                        $this->type($value) => $this->serialize($value),
                    ],
                    $this->expirationAttribute => [
                        'N' => (string) $this->toTimestamp($seconds),
                    ],
                ],
                'ConditionExpression' => 'attribute_not_exists(#key) OR #expires_at < :now',
                'ExpressionAttributeNames' => [
                    '#key' => $this->keyAttribute,
                    '#expires_at' => $this->expirationAttribute,
                ],
                'ExpressionAttributeValues' => [
                    ':now' => [
                        'N' => (string) $this->currentTime(),
                    ],
                ],
            ]);

            return true;
        } catch (DynamoDbException $e) {
            if (str_contains($e->getMessage(), 'ConditionalCheckFailed')) {
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
            $response = $this->dynamo->updateItem([
                'TableName' => $this->table,
                'Key' => [
                    $this->keyAttribute => [
                        'S' => $this->prefix.$key,
                    ],
                ],
                'ConditionExpression' => 'attribute_exists(#key) AND #expires_at > :now',
                'UpdateExpression' => 'SET #value = #value + :amount',
                'ExpressionAttributeNames' => [
                    '#key' => $this->keyAttribute,
                    '#value' => $this->valueAttribute,
                    '#expires_at' => $this->expirationAttribute,
                ],
                'ExpressionAttributeValues' => [
                    ':now' => [
                        'N' => (string) $this->currentTime(),
                    ],
                    ':amount' => [
                        'N' => (string) $value,
                    ],
                ],
                'ReturnValues' => 'UPDATED_NEW',
            ]);

            return (int) $response['Attributes'][$this->valueAttribute]['N'];
        } catch (DynamoDbException $e) {
            if (str_contains($e->getMessage(), 'ConditionalCheckFailed')) {
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
            $response = $this->dynamo->updateItem([
                'TableName' => $this->table,
                'Key' => [
                    $this->keyAttribute => [
                        'S' => $this->prefix.$key,
                    ],
                ],
                'ConditionExpression' => 'attribute_exists(#key) AND #expires_at > :now',
                'UpdateExpression' => 'SET #value = #value - :amount',
                'ExpressionAttributeNames' => [
                    '#key' => $this->keyAttribute,
                    '#value' => $this->valueAttribute,
                    '#expires_at' => $this->expirationAttribute,
                ],
                'ExpressionAttributeValues' => [
                    ':now' => [
                        'N' => (string) $this->currentTime(),
                    ],
                    ':amount' => [
                        'N' => (string) $value,
                    ],
                ],
                'ReturnValues' => 'UPDATED_NEW',
            ]);

            return (int) $response['Attributes'][$this->valueAttribute]['N'];
        } catch (DynamoDbException $e) {
            if (str_contains($e->getMessage(), 'ConditionalCheckFailed')) {
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
        return $this->put($key, $value, Carbon::now()->addYears(5)->getTimestamp());
    }

    /**
     * Get a lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function lock($name, $seconds = 0, $owner = null): \Illuminate\Cache\DynamoDbLock
    {
        return new DynamoDbLock($this, $name, $seconds, $owner);
    }

    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function restoreLock($name, $owner): \Illuminate\Cache\DynamoDbLock
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
        $this->dynamo->deleteItem([
            'TableName' => $this->table,
            'Key' => [
                $this->keyAttribute => [
                    'S' => $this->prefix.$key,
                ],
            ],
        ]);

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
    protected function toTimestamp($seconds)
    {
        return $seconds > 0
            ? $this->availableAt($seconds)
            : $this->currentTime();
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

        if ($this->serializableClasses !== null) {
            return unserialize($value, ['allowed_classes' => $this->serializableClasses]);
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
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Set the cache key prefix.
     *
     * @param  string  $prefix
     */
    public function setPrefix($prefix): void
    {
        $this->prefix = $prefix;
    }

    /**
     * Get the DynamoDb Client instance.
     */
    public function getClient(): \Aws\DynamoDb\DynamoDbClient
    {
        return $this->dynamo;
    }
}
