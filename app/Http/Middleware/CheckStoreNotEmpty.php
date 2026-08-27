<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckStoreNotEmpty
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $store_count = Store::count();

        // Exclude store setup routes (index to view the form, store to actually submit it - without
        // "store" excluded too, submitting the very first store bounces the POST back to the empty form
        // and the data is lost, since store_count is still 0 at that point), and the system-registration
        // routes CheckPurchaseCode redirects to - see the comment there for why both middlewares need to
        // know about each other's setup routes.
        $exclude_routes = ['admin.stores.index', 'admin.stores.store', 'admin.system_registration', 'admin.system_register', 'admin.web_system_register'];
        $current_route = $request->route() ? $request->route()->getName() : null;

        if ($store_count === 0 && !in_array($current_route, $exclude_routes)) {
            return redirect()->route('admin.stores.index')->with('error', 'Please set up your store first.');
        }

        return $next($request);
    }
}
