<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storefront's own login gate (cart/checkout/my-account) - deliberately not the shared 'auth'/'role'
 * middleware aliases, since both of those (App\Http\Middleware\Authenticate::redirectTo(),
 * App\Http\Middleware\RoleMiddleware's unauthenticated branch) hard-redirect every non-admin,
 * non-JSON request to the admin login page - there was no customer-facing web session before this build,
 * so nothing needed a customer-appropriate redirect until now.
 */
class EnsureCustomerAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth('web')->check() || (int) auth('web')->user()->role_id !== Role::CUSTOMER) {
            return redirect()->route('customer.login')->with('redirect_to', $request->fullUrl());
        }

        return $next($request);
    }
}
