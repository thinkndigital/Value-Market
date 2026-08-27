<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPurchaseCode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $doctor_brown = Setting::where('variable', 'doctor_brown')->first();
        $web_doctor_brown = Setting::where('variable', 'web_doctor_brown')->first();

        // Exclude system registration routes, and the store-setup routes CheckStoreNotEmpty redirects to -
        // otherwise a fresh install with neither a purchase code nor a store ping-pongs forever between
        // "set up your store" (here) and "purchase code required" (CheckStoreNotEmpty), since each
        // middleware only knew about its own setup route, not the other's.
        $exclude_routes = ['admin.system_registration', 'admin.system_register', 'admin.web_system_register', 'admin.stores.index', 'admin.stores.store'];
        $current_route = $request->route() ? $request->route()->getName() : null;

        if (empty($doctor_brown) && empty($web_doctor_brown) && !in_array($current_route, $exclude_routes)) {
            return redirect()->route('admin.system_registration')->with('error', 'Purchase code is required.');
        }

        return $next($request);
    }
}
