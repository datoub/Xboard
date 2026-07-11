<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\NodeResource;
use App\Models\User;
use App\Services\ServerService;
use App\Services\Subscription\SubscriptionStateService;
use App\Services\UserService;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function fetch(Request $request)
    {
        $user = User::find($request->user()->id);
        $servers = [];
        $userService = new UserService();
        if ($userService->isAvailable($user)) {
            $servers = ServerService::getAvailableServers($user);
        }
        $authModeV2 = $request->header('X-Xboard-Auth-Mode') === 'v2';
        $subscriptionState = $authModeV2
            ? app(SubscriptionStateService::class)->describe($user)
            : null;
        $eTag = sha1(json_encode(array_column($servers, 'cache_key')));
        if (!$authModeV2 && strpos($request->header('If-None-Match', ''), $eTag) !== false) {
            return response(null, 304);
        }
        $data = NodeResource::collection($servers);
        $payload = ['data' => $data];

        if ($authModeV2) {
            $payload['meta'] = [
                'subscription' => $subscriptionState,
            ];
        }

        $response = response($payload)->header('ETag', "\"{$eTag}\"");

        if ($authModeV2) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Subscription-State', $subscriptionState['state']);
            $response->headers->set('Vary', 'X-Xboard-Auth-Mode');
        }

        return $response;
    }
}
