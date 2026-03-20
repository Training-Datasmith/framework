<?php

declare (strict_types=1);
namespace Illuminate\Broadcasting;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\Http_Kernel\Exception\Access_Denied_Http_Exception;
class Broadcast_Controller extends Controller
{
    /**
     * Authenticate the request for channel access.
     *
     * @return \Illuminate\Http\Response
     */
    public function authenticate(Request $request)
    {
        if ($request->has_session()) {
            $request->session()->reflash();
        }
        return Broadcast::auth($request);
    }
    /**
     * Authenticate the current user.
     *
     * See: https://pusher.com/docs/channels/server_api/authenticating-users/#user-authentication.
     *
     * @return array|null
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    public function authenticate_user(Request $request)
    {
        if ($request->has_session()) {
            $request->session()->reflash();
        }
        return Broadcast::resolve_authenticated_user($request) ?? throw new Access_Denied_Http_Exception();
    }
}