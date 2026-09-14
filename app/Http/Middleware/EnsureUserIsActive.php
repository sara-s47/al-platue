<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error(
                message: 'Your account is not active.',
                status: 403,
            );
        }

        if ($user->status === UserStatus::Blocked) {
            return ApiResponse::error(
                message: 'Your account is not active.',
                status: 403,
            );
        }

        // TODO: temporary — allow access without phone OTP verification.
        // if ($user->status !== UserStatus::Active) {
        //     return ApiResponse::error(
        //         message: 'Your account is not active.',
        //         status: 403,
        //     );
        // }

        return $next($request);
    }
}
