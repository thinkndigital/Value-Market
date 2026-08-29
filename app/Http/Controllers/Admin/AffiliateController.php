<?php

namespace App\Http\Controllers\Admin;

use App\Models\AffiliateLink;
use App\Models\ReferralConversion;
use App\Models\User;
use Illuminate\Routing\Controller;

/**
 * Phase 7 (docs/PHASE_7_AFFILIATE_ENGINE.md) built the affiliate link/click/conversion tracking and the
 * commission rule engine, but never gave the admin panel any visibility into it - the underlying data
 * (who has affiliate links, how many clicks/conversions, how much commission is pending vs. approved) was
 * only ever reachable via App\Http\Controllers\AffiliateController's own self-service JSON endpoints
 * (a user's own links) and direct database access. Read-only reporting: nothing here can change a link's
 * standing or a conversion's status - those stay driven by the order lifecycle
 * (AffiliateService::approveConversionsForOrder()/reverseConversionsForOrder()), not an admin action.
 */
class AffiliateController extends Controller
{
    public function index()
    {
        return view('admin.pages.tables.affiliate_links');
    }

    public function list()
    {
        $search = trim(request('search', ''));
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $limit = request('limit', 10);
        $offset = $search || request('pagination_offset') ? request('pagination_offset') : 0;

        $query = AffiliateLink::query()
            ->leftJoin('users', 'users.id', '=', 'affiliate_links.user_id')
            ->select('affiliate_links.*', 'users.username as affiliate_name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('affiliate_links.id', 'like', "%$search%")
                    ->orWhere('affiliate_links.code', 'like', "%$search%")
                    ->orWhere('users.username', 'like', "%$search%");
            });
        }

        $total = (clone $query)->count();

        $links = $query->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $conversionSums = ReferralConversion::whereIn('affiliate_link_id', $links->pluck('id'))
            ->selectRaw('affiliate_link_id, status, SUM(commission_amount) as total')
            ->groupBy('affiliate_link_id', 'status')
            ->get()
            ->groupBy('affiliate_link_id');

        $rows = $links->map(function ($link) use ($conversionSums) {
            $sums = $conversionSums->get($link->id, collect());
            $approved = $sums->firstWhere('status', ReferralConversion::STATUS_APPROVED)->total ?? 0;
            $pending = $sums->firstWhere('status', ReferralConversion::STATUS_PENDING)->total ?? 0;

            return [
                'id' => $link->id,
                'affiliate_name' => $link->affiliate_name ?? ('#' . $link->user_id),
                'code' => $link->code,
                'target_type' => ucfirst($link->target_type) . ($link->target_id ? ' #' . $link->target_id : ''),
                'clicks_count' => $link->clicks_count,
                'conversions_count' => $link->conversions_count,
                'approved_commission' => app(\App\Services\CurrencyService::class)->formateCurrency(formatePriceDecimal($approved)),
                'pending_commission' => app(\App\Services\CurrencyService::class)->formateCurrency(formatePriceDecimal($pending)),
                'status' => $link->status == AffiliateLink::STATUS_ACTIVE
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-secondary">Inactive</span>',
            ];
        });

        return response()->json(['total' => $total, 'rows' => $rows]);
    }
}
