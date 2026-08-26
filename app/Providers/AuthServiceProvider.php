<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\Product;
use App\Policies\ProductPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Product::class => ProductPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Implicitly grant "Super Admin" role all permissions
        // This works in the app by using gate-related functions like auth()->user->can() and @can()
        //
        // Phase 2 (docs/PHASE_2_RBAC_ARCHITECTURE.md, Task 3): previously did `$user->role->name` with no
        // null check - a user with role_id = NULL (a legitimate, nullable column; most plain customers
        // likely have no role_id at all) hitting ANY Gate/Policy check crashed the request with
        // "Attempt to read property 'name' on null", confirmed empirically. Now uses the null-safe
        // isSuperAdmin() helper (compares role_id directly, no relation load, no null dereference
        // possible) instead of loading and reading the role relation.
        Gate::before(function ($user, $ability) {
            return $user->isSuperAdmin() ? true : null;
        });
    }
}
