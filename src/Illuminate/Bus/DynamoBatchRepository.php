<?php

declare (strict_types=1);
namespace Illuminate\Bus;

use Aws\Dynamo_Db\Dynamo_Db_Client;
use Aws\Dynamo_Db\Marshaler;
use Carbon\Carbon_Immutable;
use Closure;
use Illuminate\Support\Str;
class Dynamo_Batch_Repository implements Batch_Repository
{
    /**
     * The database connection instance.
     *
     * @var \Aws\DynamoDb\DynamoDbClient
     */
    protected $dynamo_db_client;
    /**
     * The DynamoDB marshaler instance.
     *
     * @var \Aws\DynamoDb\Marshaler
     */
    protected $marshaler;
    /**
     * Create a new batch repository instance.
     */
    public function __construct(
        /**
         * The batch factory instance.
         */
        protected \Illuminate\Bus\Batch_Factory $factory,
        Dynamo_Db_Client $dynamo_db_client,
        /**
         * The application name.
         */
        protected string $application_name,
        /**
         * The table to use to store batch information.
         */
        protected string $table,
        /**
         * The time-to-live value for batch records.
         */
        protected ?int $ttl,
        /**
         * The name of the time-to-live attribute for batch records.
         */
        protected ?string $ttl_attribute
    )
    {
        $this->dynamo_db_client = $dynamo_db_client;
        $this->marshaler = new Marshaler();
    }
    /**
     * Retrieve a list of batches.
     *
     * @param  int  $limit
     * @param  mixed  $before
     * @return \Illuminate\Bus\Batch[]
     */
    public function get($limit = 50, $before = null): array
    {
        $condition = 'application = :application';
        if ($before) {
            $condition = 'application = :application AND id < :id';
        }
        $result = $this->dynamo_db_client->query(['TableName' => $this->table, 'KeyConditionExpression' => $condition, 'ExpressionAttributeValues' => array_filter([':application' => ['S' => $this->application_name], ':id' => array_filter(['S' => $before])]), 'Limit' => $limit, 'ScanIndexForward' => false]);
        return array_map(fn($b) => $this->to_batch($this->marshaler->unmarshal_item($b, mapAsObject: true)), $result['Items']);
    }
    /**
     * Retrieve information about an existing batch.
     *
     * @return \Illuminate\Bus\Batch|null
     */
    public function find(string $batch_id)
    {
        if (trim($batch_id) === '') {
            return null;
        }
        $b = $this->dynamo_db_client->get_item(['TableName' => $this->table, 'Key' => ['application' => ['S' => $this->application_name], 'id' => ['S' => $batch_id]]]);
        if (!isset($b['Item'])) {
            // If we didn't find it via a standard read, attempt consistent read...
            $b = $this->dynamo_db_client->get_item(['TableName' => $this->table, 'Key' => ['application' => ['S' => $this->application_name], 'id' => ['S' => $batch_id]], 'ConsistentRead' => true]);
            if (!isset($b['Item'])) {
                return null;
            }
        }
        $batch = $this->marshaler->unmarshal_item($b['Item'], mapAsObject: true);
        if ($batch) {
            return $this->to_batch($batch);
        }
    }
    /**
     * Store a new pending batch.
     *
     * @return \Illuminate\Bus\Batch
     */
    public function store(Pending_Batch $batch)
    {
        $id = (string) Str::ordered_uuid();
        $batch = ['id' => $id, 'name' => $batch->name, 'total_jobs' => 0, 'pending_jobs' => 0, 'failed_jobs' => 0, 'failed_job_ids' => [], 'options' => $this->serialize($batch->options ?? []), 'created_at' => time(), 'cancelled_at' => null, 'finished_at' => null];
        if (!is_null($this->ttl)) {
            $batch[$this->ttl_attribute] = time() + $this->ttl;
        }
        $this->dynamo_db_client->put_item(['TableName' => $this->table, 'Item' => $this->marshaler->marshal_item(array_merge(['application' => $this->application_name], $batch))]);
        return $this->find($id);
    }
    /**
     * Increment the total number of jobs within the batch.
     */
    public function increment_total_jobs(string $batch_id, int $amount): void
    {
        $update = 'SET total_jobs = total_jobs + :val, pending_jobs = pending_jobs + :val';
        if ($this->ttl) {
            $update = "SET total_jobs = total_jobs + :val, pending_jobs = pending_jobs + :val, #{$this->ttl_attribute} = :ttl";
        }
        $this->dynamo_db_client->update_item(array_filter(['TableName' => $this->table, 'Key' => ['application' => ['S' => $this->application_name], 'id' => ['S' => $batch_id]], 'UpdateExpression' => $update, 'ExpressionAttributeValues' => array_filter([':val' => ['N' => "{$amount}"], ':ttl' => array_filter(['N' => $this->get_expiry_time()])]), 'ExpressionAttributeNames' => $this->ttl_expression_attribute_name(), 'ReturnValues' => 'ALL_NEW']));
    }
    /**
     * Decrement the total number of pending jobs for the batch.
     */
    public function decrement_pending_jobs(string $batch_id, string $job_id): \Illuminate\Bus\Updated_Batch_Job_Counts
    {
        $update = 'SET pending_jobs = pending_jobs - :inc';
        if ($this->ttl !== null) {
            $update = "SET pending_jobs = pending_jobs - :inc, #{$this->ttl_attribute} = :ttl";
        }
        $batch = $this->dynamo_db_client->update_item(array_filter(['TableName' => $this->table, 'Key' => ['application' => ['S' => $this->application_name], 'id' => ['S' => $batch_id]], 'UpdateExpression' => $update, 'ExpressionAttributeValues' => array_filter([':inc' => ['N' => '1'], ':ttl' => array_filter(['N' => $this->get_expiry_time()])]), 'ExpressionAttributeNames' => $this->ttl_expression_attribute_name(), 'ReturnValues' => 'ALL_NEW']));
        $values = $this->marshaler->unmarshal_item($batch['Attributes']);
        return new Updated_Batch_Job_Counts($values['pending_jobs'], $values['failed_jobs']);
    }
    /**
     * Increment the total number of failed jobs for the batch.
     */
    public function increment_failed_jobs(string $batch_id, string $job_id): \Illuminate\Bus\Updated_Batch_Job_Counts
    {
        $update = 'SET failed_jobs = failed_jobs + :inc, failed_job_ids = list_append(failed_job_ids, :jobId)';
        if ($this->ttl !== null) {
            $update = "SET failed_jobs = failed_jobs + :inc, failed_job_ids = list_append(failed_job_ids, :jobId), #{$this->ttl_attribute} = :ttl";
        }
        $batch = $this->dynamo_db_client->update_item(array_filter(['TableName' => $this->table, 'Key' => ['application' => ['S' => $this->application_name], 'id' => ['S' => $batch_id]], 'UpdateExpression' => $update, 'ExpressionAttributeValues' => array_filter([':jobId' => $this->marshaler->marshal_value([$job_id]), ':inc' => ['N' => '1'], ':ttl' => array_filter(['N' => $this->get_expiry_time()])]), 'ExpressionAttributeNames' => $this->ttl_expression_attribute_name(), 'ReturnValues' => 'ALL_NEW']));
        $values = $this->marshaler->unmarshal_item($batch['Attributes']);
        return new Updated_Batch_Job_Counts($values['pending_jobs'], $values['failed_jobs']);
    }
    /**
     * Mark the batch that has the given ID as finished.
     */
    public function mark_as_finished(string $batch_id): void
    {
        $update = 'SET finished_at = :timestamp';
        if ($this->ttl !== null) {
            $update = "SET finished_at = :timestamp, #{$this->ttl_attribute} = :ttl";
        }
        $this->dynamo_db_client->update_item(array_filter(['TableName' => $this->table, 'Key' => ['application' => ['S' => $this->application_name], 'id' => ['S' => $batch_id]], 'UpdateExpression' => $update, 'ExpressionAttributeValues' => array_filter([':timestamp' => ['N' => (string) time()], ':ttl' => array_filter(['N' => $this->get_expiry_time()])]), 'ExpressionAttributeNames' => $this->ttl_expression_attribute_name()]));
    }
    /**
     * Cancel the batch that has the given ID.
     */
    public function cancel(string $batch_id): void
    {
        $update = 'SET cancelled_at = :timestamp, finished_at = :timestamp';
        if ($this->ttl !== null) {
            $update = "SET cancelled_at = :timestamp, finished_at = :timestamp, #{$this->ttl_attribute} = :ttl";
        }
        $this->dynamo_db_client->update_item(array_filter(['TableName' => $this->table, 'Key' => ['application' => ['S' => $this->application_name], 'id' => ['S' => $batch_id]], 'UpdateExpression' => $update, 'ExpressionAttributeValues' => array_filter([':timestamp' => ['N' => (string) time()], ':ttl' => array_filter(['N' => $this->get_expiry_time()])]), 'ExpressionAttributeNames' => $this->ttl_expression_attribute_name()]));
    }
    /**
     * Delete the batch that has the given ID.
     */
    public function delete(string $batch_id): void
    {
        $this->dynamo_db_client->delete_item(['TableName' => $this->table, 'Key' => ['application' => ['S' => $this->application_name], 'id' => ['S' => $batch_id]]]);
    }
    /**
     * Execute the given Closure within a storage specific transaction.
     *
     * @return mixed
     */
    public function transaction(Closure $callback)
    {
        return $callback();
    }
    /**
     * Rollback the last database transaction for the connection.
     *
     * @return void
     */
    public function roll_back()
    {
    }
    /**
     * Convert the given raw batch to a Batch object.
     *
     * @param  object  $batch
     */
    protected function to_batch($batch): \Illuminate\Bus\Batch
    {
        return $this->factory->make($this, $batch->id, $batch->name, (int) $batch->total_jobs, (int) $batch->pending_jobs, (int) $batch->failed_jobs, $batch->failed_job_ids, $this->unserialize($batch->options) ?? [], Carbon_Immutable::create_from_timestamp($batch->created_at, date_default_timezone_get()), $batch->cancelled_at ? Carbon_Immutable::create_from_timestamp($batch->cancelled_at, date_default_timezone_get()) : $batch->cancelled_at, $batch->finished_at ? Carbon_Immutable::create_from_timestamp($batch->finished_at, date_default_timezone_get()) : $batch->finished_at);
    }
    /**
     * Create the underlying DynamoDB table.
     */
    public function create_aws_dynamo_table(): void
    {
        $definition = ['TableName' => $this->table, 'AttributeDefinitions' => [['AttributeName' => 'application', 'AttributeType' => 'S'], ['AttributeName' => 'id', 'AttributeType' => 'S']], 'KeySchema' => [['AttributeName' => 'application', 'KeyType' => 'HASH'], ['AttributeName' => 'id', 'KeyType' => 'RANGE']], 'BillingMode' => 'PAY_PER_REQUEST'];
        $this->dynamo_db_client->create_table($definition);
        if (!is_null($this->ttl)) {
            $this->dynamo_db_client->update_time_to_live(['TableName' => $this->table, 'TimeToLiveSpecification' => ['AttributeName' => $this->ttl_attribute, 'Enabled' => true]]);
        }
    }
    /**
     * Delete the underlying DynamoDB table.
     */
    public function delete_aws_dynamo_table(): void
    {
        $this->dynamo_db_client->delete_table(['TableName' => $this->table]);
    }
    /**
     * Get the expiry time based on the configured time-to-live.
     */
    protected function get_expiry_time(): ?string
    {
        return is_null($this->ttl) ? null : (string) (time() + $this->ttl);
    }
    /**
     * Get the expression attribute name for the time-to-live attribute.
     */
    protected function ttl_expression_attribute_name(): array
    {
        return is_null($this->ttl) ? [] : ["#{$this->ttl_attribute}" => $this->ttl_attribute];
    }
    /**
     * Serialize the given value.
     *
     * @param  mixed  $value
     */
    protected function serialize($value): string
    {
        return serialize($value);
    }
    /**
     * Unserialize the given value.
     *
     * @param  string  $serialized
     */
    protected function unserialize($serialized): mixed
    {
        return unserialize($serialized);
    }
    /**
     * Get the underlying DynamoDB client instance.
     */
    public function get_dynamo_client(): Dynamo_Db_Client
    {
        return $this->dynamo_db_client;
    }
    /**
     * The name of the table that contains the batch records.
     */
    public function get_table(): string
    {
        return $this->table;
    }
}