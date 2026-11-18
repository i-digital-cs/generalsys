# GH — Premature LATER→PICKUP and Pre-Pickup Invoice Warning — Fix Report

**Environment:** TEST only · **Date:** 2026-11-18 · **LIVE:** untouched (no deploy, no production commands) · **Commit:** local TEST commit on `release/generalsys-full-20261108` (not pushed)

## 1. Root cause

### Issue 1 — Orders for tomorrow became PICKUP today (backend transition defect)

`GhPickupEligibilityService::isEligibleForPickup()` used a rolling window: eligible when `now >= pickup_starts_at − GH_PICKUP_LEAD_HOURS` (default 24h, decision D-GH-017). An order created at 18:30 Dubai for 10:00 the next morning is only 15.5h away, so:

- `initialStatus()` stored it as `PICKUP` at creation, and
- `promoteEligibleLaterOrders()` (scheduler `gh:promote-pickup-eligible`, every 5 min) promoted any LATER order within 24h.

The same rolling cutoff was duplicated in driver read-models (`GhDriverWorkspaceService::actionablePickupsQuery`, upcoming list, `GhOrderBoardService::driverLaterQuery`), so drivers also saw tomorrow's pickups as actionable. The UI only rendered the stored `gh_orders.status` — not a frontend mapping bug.

### Issue 2 — "Please create an invoice" shown in LATER / PICKUP (backend warning rule)

`GhOrderWarningService::isMissingIssuedInvoice()` flagged every non-cancelled order without an issued invoice, for the admin audience — including LATER and PICKUP, where nothing has been collected and an invoice cannot even be drafted (the draft action requires `picked_up_at`). Board cards, order show, and driver pages only render the service's warning list.

## 2. Fix

| Area | Change |
|---|---|
| `GhPickupEligibilityService` | Eligible from **00:00 Asia/Dubai of the pickup calendar date** (`eligibleFrom()`); `eligibleCutoffUtcSql()` = start of tomorrow (Dubai) in UTC for SQL. `leadHours()` removed. |
| `GhOrderStateService::promoteEligibleLaterOrders` | SQL `pickup_starts_at < cutoff` + authoritative PHP check under row lock (unchanged). Event payload now `rule=pickup_calendar_day`, `pickup_date`. |
| `GhDriverWorkspaceService`, `GhOrderBoardService` | Driver actionable / upcoming / later lists use the same calendar-day cutoff. |
| `GhOrderWarningService::isMissingIssuedInvoice` | Returns false for LATER and PICKUP; warning shows from WORKING onward (unchanged there). |
| `config/general_house.php` | `pickup_eligibility_lead_hours` / `GH_PICKUP_LEAD_HOURS` removed (no longer read). |
| Lang AR/EN | `gh.orders.pickup_not_eligible` (now `:date`), `gh.orders.driver_visibility_auto_hint` reworded. |
| Command description | `gh:promote-pickup-eligible` text updated. |

Unchanged on purpose:
- Manager manual LATER→PICKUP before the date: still allowed, **reason required**, `MANUAL_STATUS_TRANSITION` event with `early_force`, actor, reason. Drivers/employees get 403 (`ManualGhOrderStatusRequest::authorize`).
- Auto promotion sets status only — `picked_up_at`, `pickup_confirmed_by`, invoice fields untouched. PICKUP→WORKING still requires real pickup confirmation (manual WORKING without confirmation is rejected).
- Auto-promote hold (`auto_promote_held`) still blocks the scheduler.
- No invoice is created, issued, or modified; no amounts, VAT, discounts, payments, or history touched.

## 3. Owner decision — invoice behaviour unchanged (2026-11-18)

Owner confirmed: fix the order lifecycle only; **keep existing invoice behaviour unchanged**. WORKING → DELIVERY scheduling rules (invoice-independent, `InvoiceIndependentDeliveryTest`) and delivery confirmation / completion rules are untouched. The Issue 2 fix is display-only: `GhOrderWarningService` is a read-only badge service; no invoice creation, issuance, amounts, VAT, discounts, payments, or financial history logic was changed.

## 4. Tests

New: `tests/Feature/GeneralHouse/PickupCalendarDayPromotionTest.php` (9 tests) — same-day → PICKUP; tomorrow morning stays LATER through 23:59:59 and promotes at 00:00 Dubai; owner incident (18:30 for next morning); future date waits for its own date; scheduler command idempotent with one audit event and no pickup/invoice side effects; promoted PICKUP cannot reach WORKING without confirmation; manager early promotion requires reason and is audited; driver/employee 403; reschedule to tomorrow returns to LATER.

Updated (intentional contract change from 24h to calendar day): `TimezoneIntegrityTest`, `ScheduledPickupTest`, `LogisticsTest`, `OrdersTest`, `DriverWorkspaceTest` (frozen clock; tomorrow <24h now "upcoming"), `OrderManualTransitionTest` (removed dead config), `OrderWarningBadgesTest` (PICKUP no longer shows missing invoice; new LATER/PICKUP/WORKING/DELIVERY test).

| Command | Result |
|---|---|
| `php artisan test --filter=GeneralHouse` | **PASS** — 381 tests |
| `php artisan test` (full) | 1521 pass, 1 skipped, **1 pre-existing failure** `LangParityTest` (AR/EN key *order* mismatch around `gh.orders.created_from`/`pickup_from` — also fails on committed HEAD; not touched here) |
| `vendor/bin/pint --dirty` | PASS |
| `php -l` touched files | clean |
| `php artisan route:list` | OK |

## 5. Data / migrations

- **DATABASE CHANGES: NO** · **SCHEMA MIGRATIONS: NOT APPLICABLE** · **DATA MIGRATION OR BACKFILL: NOT APPLICABLE** · **HARDCODED ID SAFETY: NOT APPLICABLE**
- TEST read-only check (2026-11-18 17:03 Dubai): 0 unconfirmed PICKUP orders with a future pickup date; 0 LATER orders. No repair needed on TEST.
- LIVE (not executed): orders already promoted early under the 24h rule stay PICKUP after deploy (no automatic demotion). If any exist, a manager can move them back with PICKUP→LATER (reason + hold) — or request a reviewed command. Deploy requires `php artisan config:clear` (config key removed); remove `GH_PICKUP_LEAD_HOURS` from `.env` if present (harmless if left).

## 6. Changed files

`app/Services/GeneralHouse/Orders/{GhPickupEligibilityService,GhOrderStateService,GhDriverWorkspaceService,GhOrderBoardService,GhOrderWarningService}.php`, `app/Console/Commands/GhPromotePickupEligibleCommand.php`, `config/general_house.php`, `lang/{ar,en}/gh.php`, tests listed above, docs: `docs/general-house/{DECISION_LOG,STATE_MACHINE,GH9_DEPLOYMENT_PLAN}.md`, `docs/DATABASE_SCHEMA.md`, `docs/TASKS.md`, this report.

UI/UX: text-only changes (two lang values, AR+EN); no layout/markup changed.
