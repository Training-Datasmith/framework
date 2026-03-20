<?php

declare (strict_types=1);
namespace Illuminate\Broadcasting\Broadcasters;

use Illuminate\Broadcasting\Broadcast_Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Pusher\Api_Error_Exception;
use Pusher\Pusher;
use Symfony\Component\Http_Kernel\Exception\Access_Denied_Http_Exception;
class Pusher_Broadcaster extends Broadcaster
{
    use Use_Pusher_Channel_Conventions;
    /**
     * Create a new broadcaster instance.
     *
     * @param  \Pusher\Pusher  $pusher
     */
    public function __construct(
        /**
         * The Pusher SDK instance.
         */
        protected \Pusher\Pusher $pusher,
        /**
         * Indicates if JSONP callbacks are allowed on authorization.
         */
        protected bool $allow_jsonp = false
    )
    {
    }
    /**
     * Resolve the authenticated user payload for an incoming connection request.
     *
     * See: https://pusher.com/docs/channels/library_auth_reference/auth-signatures/#user-authentication
     * See: https://pusher.com/docs/channels/server_api/authenticating-users/#response
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|null
     */
    public function resolve_authenticated_user($request)
    {
        if (!$user = parent::resolve_authenticated_user($request)) {
            return;
        }
        if (method_exists($this->pusher, 'authenticateUser')) {
            return $this->pusher->authenticate_user($request->socket_id, $user);
        }
        $settings = $this->pusher->get_settings();
        $encoded_user = json_encode($user);
        $decoded_string = "{$request->socket_id}::user::{$encoded_user}";
        $auth = $settings['auth_key'] . ':' . hash_hmac('sha256', $decoded_string, (string) $settings['secret']);
        return ['auth' => $auth, 'user_data' => $encoded_user];
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
     * @return mixed
     */
    public function valid_authentication_response($request, $result)
    {
        if (str_starts_with((string) $request->channel_name, 'private')) {
            return $this->decode_pusher_response($request, method_exists($this->pusher, 'authorizeChannel') ? $this->pusher->authorize_channel($request->channel_name, $request->socket_id) : $this->pusher->socket_auth($request->channel_name, $request->socket_id));
        }
        $channel_name = $this->normalize_channel_name($request->channel_name);
        $user = $this->retrieve_user($request, $channel_name);
        $broadcast_identifier = method_exists($user, 'getAuthIdentifierForBroadcasting') ? $user->get_auth_identifier_for_broadcasting() : $user->get_auth_identifier();
        return $this->decode_pusher_response($request, method_exists($this->pusher, 'authorizePresenceChannel') ? $this->pusher->authorize_presence_channel($request->channel_name, $request->socket_id, $broadcast_identifier, $result) : $this->pusher->presence_auth($request->channel_name, $request->socket_id, $broadcast_identifier, $result));
    }
    /**
     * Decode the given Pusher response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $response
     * @return array
     */
    protected function decode_pusher_response($request, $response)
    {
        if (!$request->input('callback', false) || !$this->allow_jsonp) {
            return json_decode((string) $response, true);
        }
        return response()->json(json_decode((string) $response, true))->with_callback($request->callback);
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
        $socket = Arr::pull($payload, 'socket');
        $parameters = $socket !== null ? ['socket_id' => $socket] : [];
        $channels = new Collection($this->format_channels($channels));
        try {
            $channels->chunk(100)->each(function ($channels) use ($event, $payload, $parameters): void {
                $this->pusher->trigger($channels->to_array(), $event, $payload, $parameters);
            });
        } catch (Api_Error_Exception $e) {
            throw new Broadcast_Exception(sprintf('Pusher error: %s.', $e->get_message()));
        }
    }
    /**
     * Get the Pusher SDK instance.
     *
     * @return \Pusher\Pusher
     */
    public function get_pusher(): \Pusher\Pusher
    {
        return $this->pusher;
    }
    /**
     * Set the Pusher SDK instance.
     *
     * @param  \Pusher\Pusher  $pusher
     */
    public function set_pusher(\Pusher\Pusher $pusher): void
    {
        $this->pusher = $pusher;
    }
}