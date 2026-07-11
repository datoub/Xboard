<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Services\AuthService;
use Auth;
use Closure;
use Illuminate\Support\Facades\Cache;

class User
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!Auth::guard('sanctum')->check()) {
            if ($request->header('X-Xboard-Auth-Mode') === 'v2') {
                return response()->json([
                    'data' => null,
                    'code' => 'AUTH_EXPIRED',
                    'message' => '未登录或登录已过期',
                ], 401)->withHeaders([
                    'Cache-Control' => 'no-store, private',
                    'X-Auth-State' => 'expired',
                    'Vary' => 'X-Xboard-Auth-Mode',
                ]);
            }

            throw new ApiException('未登录或登陆已过期', 403);
        }
        return $next($request);
    }
}
