<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Chatify\ChatifyMessenger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

/**
 * Unified Dynamic Sidebar Engine (32-phase SaaS brief, Phase 3): resolves config/sidebar.php's
 * per-role nav tree for the current user - permission, feature-flag, and "route exists yet" checks -
 * then computes active/expanded state. See config/sidebar.php for the node schema and
 * resources/views/components/sidebar/tree.blade.php for the renderer.
 *
 * Deliberately conservative about what it enforces: this app has no generic "does this seller's
 * subscription plan include feature X" mechanism yet (docs/PHASE_11_SUBSCRIPTIONS.md only enforces
 * max_products and commission_rate), so 'subscription_feature' is accepted on a node but not yet
 * gated on anything - wiring it up is Phase 11 of the master architecture prompt, not this pass.
 * UI visibility here is not a security boundary; every route stays protected by its own
 * middleware/policy regardless of whether a sidebar item is shown.
 */
class SidebarService
{
    public function build(?User $user, string $roleKey): array
    {
        $config = config("sidebar.{$roleKey}", []);
        $cacheKey = $this->cacheKey($user, $roleKey);

        $resolved = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($config, $user) {
            return $this->resolveNodes($config, $user);
        });

        // Active/expanded state depends on the current request, so it's computed fresh on every
        // call rather than cached alongside the (user, role, locale)-scoped permission resolution.
        return $this->applyActiveState($resolved);
    }

    protected function cacheKey(?User $user, string $roleKey): string
    {
        $userId = $user->id ?? 'guest';

        return "sidebar:v1:{$roleKey}:{$userId}:" . app()->getLocale();
    }

    protected function resolveNodes(array $nodes, ?User $user): array
    {
        $resolved = [];

        foreach ($nodes as $node) {
            if (!$this->routeResolves($node)) {
                continue;
            }

            if (!$this->permissionAllows($node, $user)) {
                continue;
            }

            if (!$this->featureFlagAllows($node)) {
                continue;
            }

            if (!empty($node['children'])) {
                $node['children'] = $this->resolveNodes($node['children'], $user);

                // A subtitle divider or a group with nothing left under it after resolution has
                // nothing to show - drop it rather than render an empty header.
                $onlyDividers = collect($node['children'])->every(fn ($c) => !empty($c['is_subtitle']));
                if (empty($node['children']) || $onlyDividers) {
                    continue;
                }
            } elseif (empty($node['route']) && empty($node['is_subtitle'])) {
                // A leaf node with no route and no children is a config mistake, not a valid item.
                continue;
            }

            if (($node['badge'] ?? null) === 'unread_messages') {
                $node['badge_count'] = $this->unreadMessages();
            }

            $resolved[] = $node;
        }

        return $resolved;
    }

    /**
     * Routes for not-yet-built future phases (e.g. Creator Marketplace) are already listed in
     * config/sidebar.php so they appear automatically once built, with no further sidebar change -
     * until then, silently skip them instead of throwing a RouteNotFoundException.
     */
    protected function routeResolves(array $node): bool
    {
        if (empty($node['route'])) {
            return true;
        }

        return Route::has($node['route']);
    }

    protected function permissionAllows(array $node, ?User $user): bool
    {
        if (!empty($node['super_admin_only']) && (!$user || !$user->isSuperAdmin())) {
            return false;
        }

        $permission = $node['permission'] ?? null;
        if (!$permission || !$user) {
            return true;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermissionTo($permission);
    }

    protected function featureFlagAllows(array $node): bool
    {
        $flag = $node['feature_flag'] ?? null;
        if (!$flag) {
            return true;
        }

        // No flag explicitly disabled = on by default, matching how every pre-engine sidebar item
        // behaved (always shown unless a permission/role check said otherwise).
        return (bool) config("sidebar_features.{$flag}", true);
    }

    protected function unreadMessages(): int
    {
        try {
            return (new ChatifyMessenger())->totalUnseenMessages();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function applyActiveState(array $nodes): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $childrenActive = false;

            if (!empty($node['children'])) {
                $node['children'] = $this->applyActiveState($node['children']);
                $childrenActive = collect($node['children'])->contains(fn ($c) => $c['active'] ?? false);
            }

            $selfActive = $this->matchesCurrentRequest($node['match'] ?? []);
            $node['active'] = $selfActive || $childrenActive;

            $result[] = $node;
        }

        return $result;
    }

    protected function matchesCurrentRequest(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Request::is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
