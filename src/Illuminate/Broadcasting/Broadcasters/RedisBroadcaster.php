<?php

declare (strict_types=1);
namespace Illuminate\Broadcasting\Broadcasters;

use Illuminate\Broadcasting\Broadcast_Exception;
use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Redis\Connections\Php_Redis_Cluster_Connection;
use Illuminate\Redis\Connections\Predis_Cluster_Connection;
use Illuminate\Redis\Connections\Predis_Connection;
use Illuminate\Support\Arr;
use Predis\Connection\Cluster\Redis_Cluster;
use Predis\Connection\Connection_Exception;
use Redis_Exception;
use Symfony\Component\Http_Kernel\Exception\Access_Denied_Http_Exception;
class Redis_Broadcaster extends Broadcaster
{
    use Use_Pusher_Channel_Conventions;
    /**
     * Create a new broadcaster instance.
     *
     * @param  string|null  $connection
     * @param  string  $prefix
     */
    public function __construct(
        /**
         * The Redis instance.
         */
        protected \Redis $redis,
        /**
         * The Redis connection to use for broadcasting.
         */
        protected $connection = null,
        /**
         * The Redis key prefix.
         */
        protected $prefix = ''
    )
    {
    }
    /**
     * Authenticate the incoming request for a given channel.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    public function auth($request)
    {
        $channel_name = $this->normalize_channel_name(str_replace($this->prefix, '', $request->channel_name));
        if (empty($request->channel_name) || $this->is_guarded_channel($request->channel_name) && !$this->retrieve_user($request, $channel_name)) {
            throw new Access_Denied_Http_Exception();
        }
        return parent::verify_user_can_access_channel($request, $channel_name);
    }
    /**
     * Return the valid authentication response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $result
     * @return mixed
     */
    public function valid_authentication_response($request, $result)
    {
        if (is_bool($result)) {
            return json_encode($result);
        }
        $channel_name = $this->normalize_channel_name($request->channel_name);
        $user = $this->retrieve_user($request, $channel_name);
        $broadcast_identifier = method_exists($user, 'getAuthIdentifierForBroadcasting') ? $user->get_auth_identifier_for_broadcasting() : $user->get_auth_identifier();
        return json_encode(['channel_data' => ['user_id' => $broadcast_identifier, 'user_info' => $result]]);
    }
    /**
     * Broadcast the given event.
     *
     * @param  string  $event
     *
     * @throws \Illuminate\Broadcasting\BroadcastException
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        if (empty($channels)) {
            return;
        }
        $connection = $this->redis->connection($this->connection);
        $payload = json_encode(['event' => $event, 'data' => $payload, 'socket' => Arr::pull($payload, 'socket')]);
        try {
            if ($connection instanceof Php_Redis_Cluster_Connection) {
                foreach ($channels as $channel) {
                    $connection->publish($channel, $payload);
                }
            } elseif ($connection instanceof Predis_Cluster_Connection && $connection->client()->get_connection() instanceof Redis_Cluster) {
                $random_cluster_node_connection = new Predis_Connection($connection->client()->get_client_by('slot', mt_rand(0, 16383)));
                if ($events = $connection->get_event_dispatcher()) {
                    $random_cluster_node_connection->set_event_dispatcher($events);
                }
                $random_cluster_node_connection->eval($this->broadcast_multiple_channels_script(), 0, $payload, ...$this->format_channels($channels));
            } else {
                $connection->eval($this->broadcast_multiple_channels_script(), 0, $payload, ...$this->format_channels($channels));
            }
        } catch (Connection_Exception|Redis_Exception $e) {
            throw new Broadcast_Exception(sprintf('Redis error: %s.', $e->get_message()));
        }
    }
    /**
     * Get the Lua script for broadcasting to multiple channels.
     *
     * ARGV[1] - The payload
     * ARGV[2...] - The channels
     */
    protected function broadcast_multiple_channels_script(): string
    {
        return <<<'LUA'
        for i = 2, #ARGV do
          redis.call('publish', ARGV[i], ARGV[1])
        end
        LUA;
    }
    /**
     * Format the channel array into an array of strings.
     */
    protected function format_channels(array $channels): array
    {
        return array_map(fn($channel): string => $this->prefix . $channel, parent::format_channels($channels));
    }
}