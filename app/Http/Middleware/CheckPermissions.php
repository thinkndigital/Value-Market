<?php

namespace App\Http\Middleware;

use Closure;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Auth\Access\AuthorizationException;

class CheckPermissions
{
    public function handle($request, Closure $next, $permissions, $guard = null)
    {
        $authGuard = Auth::guard($guard);

        if ($authGuard->guest()) {

            return $this->unauthorizedResponse('User is not logged in.');
        }

        $user = $authGuard->user();

        // Phase 2 (docs/PHASE_2_RBAC_ARCHITECTURE.md, Task 3): previously read $user->role->name with no
        // null check - a user with role_id = NULL, or pointing at a deleted role row, crashed the request
        // instead of failing the permission check. isSuperAdmin() is null-safe (compares role_id directly).
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $permissions = is_array($permissions) ? $permissions : explode('|', $permissions);

        foreach ($permissions as $permission) {
            if ($user->hasPermissionTo($permission)) {

                return $next($request);
            }
        }


        return $this->unauthorizedResponse('User does not have the required permissions.');
    }

    protected function unauthorizedResponse($message)
    {
        if (request()->expectsJson()) {
            $response = [
                'error' => true,
                'error_message' => $message,
                'data' => [],
            ];
            // Phase 2 (docs/PHASE_2_RBAC_ARCHITECTURE.md, Task 20): this previously returned HTTP 200 with
            // an error:true body - correct for any client that checks the app's established error:true
            // convention (unchanged here), but not a real 403 for anything that also inspects the status
            // code. Adding the status is a strict correctness improvement, not a behavior change for
            // existing clients.
            return response()->json($response, 403);
        }

        throw new AuthorizationException($message);
    }

}
