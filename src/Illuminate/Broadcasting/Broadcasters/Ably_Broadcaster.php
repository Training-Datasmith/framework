<?php

declare (strict_types=1);
namespace Illuminate\Broadcasting\Broadcasters;

use Ably\Ably_Rest;
use Ably\Exceptions\Ably_Exception;
use Ably\Models\Message as AblyMessage;
use Illuminate\Broadcasting\Broadcast_Exception;
use Illuminate\Support\Str;
use Symfony\Component\Http_Kernel\Exception\Access_Denied_Http_Exception;
/**
 * @author Matthew Hall (matthall28@gmail.com)
 * @author Taylor Otwell (taylor@laravel.com)
 */
class Ably_Broadcaster extends Broadcaster
{
    /**
     * The AblyRest SDK instance.
     *
     * @var \Ably\AblyRest
     */
    protected $ably;
    /**
     * Create a new broadcaster instance.
     */
    public function __construct(Ably_Rest $ably)
    {
        $this->ably = $ably;
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
        $channel_name = $this->normalize_channel_name($request->channel_name);
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
     */
    public function valid_authentication_response($request, $result): array
    {
        if (str_starts_with((string) $request->channel_name, 'private')) {
            $signature = $this->generate_ably_signature($request->channel_name, $request->socket_id);
            return ['auth' => $this->get_public_token() . ':' . $signature];
        }
        $channel_name = $this->normalize_channel_name($request->channel_name);
        $user = $this->retrieve_user($request, $channel_name);
        $broadcast_identifier = method_exists($user, 'getAuthIdentifierForBroadcasting') ? $user->get_auth_identifier_for_broadcasting() : $user->get_auth_identifier();
        $signature = $this->generate_ably_signature($request->channel_name, $request->socket_id, $user_data = array_filter(['user_id' => (string) $broadcast_identifier, 'user_info' => $result]));
        return ['auth' => $this->get_public_token() . ':' . $signature, 'channel_data' => json_encode($user_data)];
    }
    /**
     * Generate the signature needed for Ably authentication headers.
     *
     * @param  string  $channelName
     * @param  array|null  $userData
     */
    public function generate_ably_signature($channel_name, string $socket_id, $user_data = null): string
    {
        return hash_hmac('sha256', sprintf('%s:%s%s', $socket_id, $channel_name, $user_data ? ':' . json_encode($user_data) : ''), $this->get_private_token());
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
        try {
            foreach ($this->format_channels($channels) as $channel) {
                $this->ably->channels->get($channel)->publish($this->build_ably_message($event, $payload));
            }
        } catch (Ably_Exception $e) {
            throw new Broadcast_Exception(sprintf('Ably error: %s', $e->get_message()));
        }
    }
    /**
     * Build an Ably message object for broadcasting.
     *
     * @param  string  $event
     * @return \Ably\Models\Message
     */
    protected function build_ably_message($event, array $payload = [])
    {
        return tap(new Ably_Message(), function ($message) use ($event, $payload): void {
            $message->name = $event;
            $message->data = $payload;
            $message->connection_key = data_get($payload, 'socket');
        });
    }
    /**
     * Return true if the channel is protected by authentication.
     *
     * @param  string  $channel
     */
    public function is_guarded_channel($channel): bool
    {
        return Str::starts_with($channel, ['private-', 'presence-']);
    }
    /**
     * Remove prefix from channel name.
     *
     * @param  string  $channel
     * @return string
     */
    public function normalize_channel_name($channel)
    {
        if ($this->is_guarded_channel($channel)) {
            return str_starts_with($channel, 'private-') ? Str::replace_first('private-', '', $channel) : Str::replace_first('presence-', '', $channel);
        }
        return $channel;
    }
    /**
     * Format the channel array into an array of strings.
     */
    protected function format_channels(array $channels): array
    {
        return array_map(function ($channel) {
            $channel = (string) $channel;
            if (Str::starts_with($channel, ['private-', 'presence-'])) {
                return str_starts_with($channel, 'private-') ? Str::replace_first('private-', 'private:', $channel) : Str::replace_first('presence-', 'presence:', $channel);
            }
            return 'public:' . $channel;
        }, $channels);
    }
    /**
     * Get the public token value from the Ably key.
     *
     * @return string
     */
    protected function get_public_token()
    {
        return Str::before($this->ably->options->key, ':');
    }
    /**
     * Get the private token value from the Ably key.
     *
     * @return string
     */
    protected function get_private_token()
    {
        return Str::after($this->ably->options->key, ':');
    }
    /**
     * Get the underlying Ably SDK instance.
     *
     * @return \Ably\AblyRest
     */
    public function get_ably()
    {
        return $this->ably;
    }
    /**
     * Set the underlying Ably SDK instance.
     *
     * @param  \Ably\AblyRest  $ably
     */
    public function set_ably($ably): void
    {
        $this->ably = $ably;
    }
}