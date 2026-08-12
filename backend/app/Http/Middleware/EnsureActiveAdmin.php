<?php

namespace App\Http\Middleware;

use App\Enums\AdminUserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAdmin
{
    /**
     * Reject sessions belonging to staff accounts blocked after login.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()?->fresh();

        if ($user?->status !== AdminUserStatus::Active) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
