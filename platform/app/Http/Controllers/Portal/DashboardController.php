<?php

namespace App\Http\Controllers\Portal;

use App\Enums\OrderStatus;
use App\Enums\OrganizationType;
use App\Enums\QuotationStatus;
use App\Services\Portal\RateCard;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Overview of the business or reseller dashboard: KPIs and recent orders of THIS organization.
 */
class DashboardController extends PortalController
{
    public function __invoke(Request $request, RateCard $rateCard): View
    {
        $organization = $this->organization($request);
        $monthStart = now('Asia/Dhaka')->startOfMonth()->utc();
        $orders = $organization->orders()->where('status', '!=', OrderStatus::Cancelled->value);

        $kpis = [
            ['label' => __('Orders this month'), 'value' => (clone $orders)->where('placed_at', '>=', $monthStart)->count(), 'money' => false],
            ['label' => __('Spent this month'), 'value' => (int) (clone $orders)->where('placed_at', '>=', $monthStart)->sum('total_paisa'), 'money' => true],
        ];

        if ($organization->type === OrganizationType::Business) {
            $kpis[] = ['label' => __('Open quotations'), 'value' => $organization->quotations()->whereIn('status', [QuotationStatus::Requested->value, QuotationStatus::Quoted->value, QuotationStatus::Accepted->value])->count(), 'money' => false];
        } else {
            $kpis[] = ['label' => __('Rate card items'), 'value' => count($rateCard->rows($this->context($request))), 'money' => false];
        }
        $kpis[] = ['label' => __('Team members'), 'value' => $organization->members()->count(), 'money' => false];

        return $this->page($request, 'portal.dashboard', 'overview', [
            'kpis' => $kpis,
            'recentOrders' => $organization->orders()->limit(5)->get(),
            'quotesAwaiting' => $organization->type === OrganizationType::Business
                ? $organization->quotations()->where('status', QuotationStatus::Quoted->value)->where('valid_until', '>', now())->get()
                : collect(),
        ]);
    }
}
