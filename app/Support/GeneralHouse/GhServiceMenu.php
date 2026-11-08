<?php

namespace App\Support\GeneralHouse;

use App\Models\GeneralHouse\GhB2bContract;
use App\Models\GeneralHouse\GhCreditNote;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhFinancialCorrection;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhPayment;
use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhRefund;
use App\Models\GeneralHouse\GhServiceCategory;
use App\Models\GeneralHouse\GhTreatment;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Models\ValuationSheet;
use App\Support\AppModule;
use Illuminate\Support\Facades\Gate;

/**
 * Filter Service (General House) sidebar items using existing policies / access helpers.
 * Unauthorized finance links must not render; server-side 403 remains the enforcement backstop.
 */
final class GhServiceMenu
{
    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function filterForUser(?User $user, array $items): array
    {
        if ($user === null) {
            return [];
        }

        $visible = [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'heading') {
                $visible[] = $item;

                continue;
            }

            $route = (string) ($item['route'] ?? '');
            if ($route === '' || ! self::userMaySeeRoute($user, $route)) {
                continue;
            }

            $visible[] = $item;
        }

        $visible = self::ensureDriverWorkspaceLink($user, $visible);

        return self::dropEmptyHeadings($visible);
    }

    public static function userMaySeeRoute(User $user, string $route): bool
    {
        return match ($route) {
            'portal.service' => AppModule::mayAccessModule($user, AppModule::SERVICE),
            'valuation.sheets.index' => AppModule::mayAccessModule($user, AppModule::SERVICE)
                && Gate::forUser($user)->allows('viewAny', ValuationSheet::class),
            'gh.dashboard' => GhCatalogAccess::mayManage($user),
            'gh.settings' => GhCatalogAccess::mayManage($user),
            'gh.categories.index' => Gate::forUser($user)->allows('viewAny', GhServiceCategory::class),
            'gh.products.index' => Gate::forUser($user)->allows('viewAny', GhProduct::class),
            'gh.treatments.index' => Gate::forUser($user)->allows('viewAny', GhTreatment::class),
            'gh.offerings.index' => Gate::forUser($user)->allows('viewAny', GhProductOffering::class),
            'gh.customers.index' => Gate::forUser($user)->allows('viewAny', GhCustomer::class),
            'gh.zones.index' => Gate::forUser($user)->allows('viewAny', GhZone::class),
            'gh.contracts.index' => Gate::forUser($user)->allows('viewAny', GhB2bContract::class),
            'gh.orders.index' => Gate::forUser($user)->allows('viewAny', GhOrder::class),
            'gh.orders.create' => Gate::forUser($user)->allows('create', GhOrder::class),
            'gh.invoices.index' => Gate::forUser($user)->allows('viewAny', GhInvoice::class),
            'gh.payments.index' => Gate::forUser($user)->allows('viewAny', GhPayment::class),
            'gh.credit-notes.index' => Gate::forUser($user)->allows('viewAny', GhCreditNote::class),
            'gh.refunds.index' => Gate::forUser($user)->allows('viewAny', GhRefund::class),
            'gh.financial-corrections.index' => Gate::forUser($user)->allows('viewAny', GhFinancialCorrection::class),
            'gh.reports.index',
            'gh.reports.outstanding',
            'gh.reports.collections',
            'gh.reports.invoices',
            'gh.reports.orders',
            'gh.reports.b2b',
            'gh.reports.pickup-performance',
            'gh.reports.delivery-performance' => GhCatalogAccess::mayManage($user),
            'gh.driver.dashboard',
            'gh.driver.history',
            'gh.driver.pickup',
            'gh.driver.delivery' => GhOrderAccess::mayUseDriverWorkspace($user),
            default => str_starts_with($route, 'gh.')
                ? GhCatalogAccess::mayManage($user)
                : AppModule::mayAccessModule($user, AppModule::SERVICE),
        };
    }

    /**
     * Drivers land on SERVICE module for GH tasks; surface workspace without finance links.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function ensureDriverWorkspaceLink(User $user, array $items): array
    {
        if (! GhOrderAccess::mayUseDriverWorkspace($user)) {
            return $items;
        }

        foreach ($items as $item) {
            if (($item['route'] ?? '') === 'gh.driver.dashboard') {
                return $items;
            }
        }

        array_unshift($items, [
            'key' => 'nav.gh_driver_workspace',
            'route' => 'gh.driver.dashboard',
            'icon' => 'bi-truck',
        ]);

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function dropEmptyHeadings(array $items): array
    {
        $out = [];
        $pendingHeading = null;

        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'heading') {
                $pendingHeading = $item;

                continue;
            }

            if ($pendingHeading !== null) {
                $out[] = $pendingHeading;
                $pendingHeading = null;
            }
            $out[] = $item;
        }

        return $out;
    }
}
