# GH — Driver Cash Custody, Invoice Settlement & Financial Reconciliation

**Environment:** TEST only (`/home/d14098/generalsys.idigital-cs.com`, DB `d14098_general`)
**Date:** 2026-11-18 (final QA same day)
**LIVE / push / deploy:** not performed. **Commit:** not performed (awaiting owner request).
**Owner decisions:** D-GH-024 … D-GH-027 in `docs/general-house/DECISION_LOG.md`.

---

## A. Existing system audit

| Area | Before | Gap |
|------|--------|-----|
| Collection | `gh_driver_cash_collections` written at delivery confirmation (actual cash amount, idempotent, `GH-DCC-######`), optional link to the invoice `gh_payments` row | — (kept as is) |
| Settlement | `gh_driver_cash_settlements` = **lump sum per driver**; optional backdated `occurred_at` | No link to invoices; no per-invoice settled / remaining; backdating possible |
| Balance | `gh_driver_cash_balances` materialized, row-locked | No reconciliation check against source rows |
| Reversal | none | Mistakes could not be corrected without editing data |
| Invoice list / order page | Customer payment status only | No driver collection / settlement columns, filters or status |
| Dashboard / reports | Driver list + simple settle form | No tabs, ledger, as-of balance, period activity, CSV |
| Timezone | Mixed display | Settlement times not shown explicitly in Asia/Dubai |

## B. Implemented changes

**Migration** `2026_11_18_190000_add_gh_driver_cash_settlement_allocations_and_reversals.php` (additive): `gh_driver_cash_settlement_allocations`, `gh_driver_cash_settlement_reversals`, `(id, driver_id)` unique keys on collections/settlements for same-driver composite FKs, `amount > 0` CHECKs (MySQL/MariaDB), sequence `driver_cash_settlement_reversals`. Schema: `docs/DATABASE_SCHEMA.md` → "Manual settlement allocations & reversals".

**Services** (`app/Services/GeneralHouse/…`)
- `Payments/GhDriverCashSettlementService` — settle with manual allocations, reverse, allocate legacy; `mayAllocate` / `mayReverse`.
- `Payments/GhDriverCashAllocator`, `Payments/GhDriverCashLedgerService` (balance lock/delta + events, extracted from the custody service).
- `Payments/GhCashSettlementQueryService` — **SSOT** for settled / remaining / status (SQL subqueries + collection annotation).
- `Payments/GhDriverCashDashboardService`, `Payments/GhDriverCashActivityService` (collections, outstanding, settlement history, adjustments, ledger with running balance, as-of balance).
- `Payments/GhOrderFinancialSummaryService` — order / invoice financial section + chronological history.
- `Invoices/GhInvoiceListService` — invoice list columns + filters.
- `Reporting/GhDriverCashReportService` — totals, by driver / invoice / date / manager, CSV.
- `Payments/GhDriverCashCustodyService` — now collection-side only (lump-sum settle removed; no competing path).

**Controllers / requests**: `DriverCashCustodyController`, `DriverCashSettlementController`, `DriverCashReportController`, `InvoiceController` (index/show), `OrderController::show`; `SettleGhDriverCashRequest`, `AllocateGhDriverCashSettlementRequest`, `ReverseGhDriverCashSettlementRequest`, `GhDriverCashFilterRequest`, `GhInvoiceIndexRequest`, `Concerns/CollectsGhCashAllocations`.

**Routes** (`gh.` prefix, manager only): `driver-cash.index|show`, `driver-cash.settlements.create`, `driver-cash.settle` (POST, throttled), `driver-cash.settlements.index|show`, `driver-cash.settlements.reverse|allocate` (POST, throttled), `reports.driver-cash` (+ `?export=outstanding|by_driver|by_date|by_manager`).

**UI**: driver custody dashboard (KPIs, counts, reconciliation, period vs as-of, 5 tabs), settlement create (manual allocation table, live totals, confirm modal), settlement show (allocations, legacy allocate, reversal), settlements index, invoice list (14 columns + filters, both display modes), invoice/order financial section, driver cash report. Nav: Financial settlements, Driver cash report. AR + EN keys.

## C. Financial rules

1. **Manual allocation (no FIFO):** the manager ticks collections and types an amount for each. Server rules: amount received > 0; `Σ allocations = received`; each allocation ≤ that collection's remaining; collection must belong to the settlement's driver (also enforced by composite FKs); no duplicate rows; received ≤ driver outstanding.
2. **Partial / multiple:** a collection can be covered by many settlements until remaining = 0; one settlement can cover many collections.
3. **Remaining per collection** = `collection.amount − Σ effective allocations` (allocations of reversed settlements excluded).
4. **Status** (collection / order / invoice): `NOT_APPLICABLE` (no driver cash — e.g. paid directly to the company or online), `NOT_SETTLED`, `PARTIAL`, `SETTLED`.
5. **Driver custody** = `Σ collections − Σ settlements + Σ reversals`; materialized in `gh_driver_cash_balances`, recomputed from source rows on the dashboard → mismatch alert if they diverge.
6. **Reversal (final simple workflow, D-GH-026):** any authorized GH manager — **including the manager who recorded the settlement** — reverses in one step with a mandatory reason. No second-manager approval, no pending state. Only the settlement's own driver is excluded. One reversal per settlement; adds the amount back to custody; settlement + allocations kept for audit.
7. **Legacy lump-sum settlements:** shown as unallocated; manual allocation later moves no money (balance unchanged), only links invoices.
8. **Integrity:** one transaction; lock order driver balance → settlement → collections; idempotency keys (unique); unique references (`GH-DCS`, `GH-DCR`); `settled_at` / `reversed_at` = server time (UTC), displayed in Asia/Dubai.
9. Customer AR, invoice payments, credit notes, debit adjustments and refunds are **not** changed by custody operations (credit notes / debit adjustments / refunds are listed under the driver's adjustments tab for context only).

## D. Tests (final run 2026-11-18)

| Class | Result |
|-------|--------|
| `DriverCashSettlementServiceTest` | 17 passed (incl. same manager reverses own settlement in one step) |
| `DriverCashDashboardServiceTest` | 7 passed (incl. credit note does not change custody) |
| `DriverCashReconciliationTest` | 1 passed, 32 assertions — one scenario checked on every surface (see §I) |
| `DriverCashSettlementHttpTest` | 10 passed (forms, 422s, same-manager reversal via HTTP, legacy allocate, tabs ≤ 30 queries AR/EN, Dubai display, order/invoice panels, both invoice modes, report + CSV, 403 for driver/employee/TL; lazy loading prevented) |
| `DriverCashCustodyTest` | 5 passed (updated to the allocation contract on purpose) |
| `DriverCashSettlementConcurrencyTest` — SQLite forks | 4 passed |
| `DriverCashSettlementConcurrencyTest` — **MySQL/MariaDB** (real InnoDB row locks) | **4 passed**, repeated 4 runs, 0 failures |
| `DriverCashSettlementConcurrencyTest` — target guard | 1 passed (refuses `d14098_general` / `.env` `DB_DATABASE`) |
| GH financial regression subset (`DriverCash|DriverDelivery|LangParity|AdminOrderDeletion|Invoice|Payment|CreditNote|Refund|DebitAdjustment|FinancialCorrection`) | 250 passed, 4 skipped (the MySQL tests, run without the scratch env) |
| Full suite (default env) | **1589 passed, 5 skipped** (4 MySQL tests skipped without the scratch env + 1 pre-existing Gross MySQL test) |

**MySQL concurrency: PASS** — on MariaDB 11.4.14 (same server/version as TEST), InnoDB, against the isolated scratch database `d14098_ghconc` (dedicated user `d14098_ghconc`, privileges on that database only; access to `d14098_general` verified **denied**). Four forked workers per scenario, no write-lock gate:

| Scenario | Result |
|---|---|
| 4 × full 100.00 settlement of one 100.00 invoice | exactly 1 settled, 3 rejected (exceeds outstanding); 1 settlement, 1 allocation; custody 0.00 |
| 4 × 30.00 against one 100.00 invoice | 3 settled, 1 rejected; allocations 90.00; custody 10.00 (never negative) |
| 4 × reversal of one settlement | exactly 1 reversal, 3 rejected (already reversed); custody restored 100.00 |
| 4 × same idempotency key | 1 settlement; all 4 callers get it back |

Safety of the gated tests: they run only with `MYSQL_TEST_DATABASE` + `MYSQL_TEST_DISPOSABLE=1`; refuse `d14098_general` and the `DB_DATABASE` read from the `.env` file (phpunit.xml overrides it to `:memory:`, which made the earlier name check ineffective — fixed); refuse any non-empty database that lacks their own marker table `gh_disposable_concurrency_marker`; then `migrate:fresh` that scratch DB only.

Re-run on TEST:
```bash
cd /home/d14098/generalsys.idigital-cs.com
( set -a; . ~/.gh_mysql_concurrency.env; set +a; php artisan test --filter=DriverCashSettlementConcurrencyTest )
```
`~/.gh_mysql_concurrency.env` (mode 600, outside the web root) holds `MYSQL_TEST_DATABASE=d14098_ghconc`, `MYSQL_TEST_DISPOSABLE=1`, `MYSQL_TEST_HOST=localhost`, `MYSQL_TEST_PORT=3306`, `MYSQL_TEST_USERNAME=d14098_ghconc`, `MYSQL_TEST_PASSWORD=…`.

The TEST server is **cPanel** (`d14098.usc1.stableserver.net`), not Hostinger hPanel; the scratch DB was created with the account's own cPanel API (`uapi Mysql create_database / create_user / set_privileges_on_database`). Remove it when no longer needed: `uapi Mysql delete_database name=d14098_ghconc && uapi Mysql delete_user name=d14098_ghconc && rm ~/.gh_mysql_concurrency.env`. On a Hostinger hPanel host the equivalent is the hPanel *Databases → MySQL Databases* page: create a new empty database + a new user with access to that database only (never an application database), then fill the same six variables. Never point these tests at a LIVE server.

**Limitation (SQLite variant):** SQLite fork tests serialize workers through SQLite's database write lock; they prove re-validation inside each transaction but not row-lock behaviour — that proof comes from the MySQL variant above.

## E. Database safety

- Additive migration; no existing row updated or deleted; no backfill. Guarded steps (re-run safe after partial MySQL DDL failure).
- TEST backup before migrate: `~/backups/gh_driver_cash_pre_alloc_20261118T184040Z.sql` (sha256 `354405ac…bc4f9`; custody tables + `gh_sequences`).
- Before / after on TEST: collections 1 (2000.00), settlements 1 (2000.00), balances 1 (outstanding 0.00), events 2 — unchanged. New tables empty. The existing settlement now shows as legacy **unallocated** (not auto-allocated, per D-GH-025).
- Historical timestamps untouched; settlement/reversal times are always server UTC now (no backdating).

## F. Manual QA (TEST)

URLs (manager login): `/service/gh/driver-cash`, `/service/gh/driver-cash/{driver}`, `/service/gh/driver-cash/{driver}/settlements/create`, `/service/gh/driver-settlements`, `/service/gh/driver-settlements/{id}`, `/service/gh/invoices`, `/service/gh/orders/{id}`, `/service/gh/reports/driver-cash`.

Scenario:
1. Driver confirms 3 cash deliveries (e.g. 120.00, 250.50, 80.00).
2. Driver custody → Record settlement; amount 150.00; tick 120.00 → Full; tick 250.50 → 30.00; confirm.
3. Expect: statuses SETTLED / PARTIAL / NOT_SETTLED; outstanding 300.50; invoice list + order page show the same.
4. Try 100.00 with 60.00 allocated → blocked (totals must match); try more than remaining → field error.
5. Open the settlement → Reverse with a reason → custody back to 450.50; original kept with reversal reference.
6. Existing legacy settlement → "Allocate remaining settlement cash" → pick invoices → Allocate; balance unchanged.
7. Report → check totals and CSV exports.

Browser check (scratch SQLite copy, headless Chromium): AR/RTL + EN/LTR × 375/768/1280 on 10 pages + settle form — 60/60 pages HTTP 200, correct `dir`, no horizontal overflow, no JS errors; live totals, mismatch warning, confirm modal and real post verified.

## G. Deployment readiness

**Ready for owner acceptance on TEST.** Financial reconciliation PASS, MySQL/MariaDB concurrency PASS, full suite green. Not deployed and not claimed production-deployed: LIVE needs its own release checklist, backup and owner approval; the migration has run on TEST only.

## H. Pending decisions

None. (Operational follow-up, not a decision: allocate the TEST legacy settlement — see §I.4.)

## I. Final financial QA (2026-11-18)

1. **Approved manager workflow — PASS, no code change needed.** `GhDriverCashSettlementService::reverse` / `mayReverse` require only a GH manager who is not the settlement's driver; there is no settler ≠ reverser rule, no approval table, no pending status. Proven by `test_same_manager_reverses_own_settlement_in_one_step`, the HTTP reversal test (same manager settles and reverses through the routes) and the reconciliation test. D-GH-026, `docs/DATABASE_SCHEMA.md` and `docs/TASKS.md` updated to the final wording.
2. **Reconciliation across surfaces — PASS** (`DriverCashReconciliationTest`). Two drivers; full, partial and repeated settlements; same-manager reversal; legacy lump sum allocated later; an invoice with no driver cash. All of these agree to the cent: `gh_driver_cash_balances`, dashboard summary (collected 450.50 / net settled 280.00 / reversed 100.00 / outstanding 170.50 / unallocated 0.00, reconciled), the order financial panel and the invoice list row of every order (collected / settled / remaining / status), report totals (collected − gross settled + reversed = outstanding), status counts, outstanding-invoices report, by-manager report, ledger closing balance, and the audit events (`COLLECTION_RECORDED`, `SETTLEMENT_RECORDED`, `SETTLEMENT_REVERSED`, `SETTLEMENT_ALLOCATED` with the acting manager). A direct-to-company invoice shows `NOT_APPLICABLE` on both the order panel and the invoice list. No negative balance anywhere.
3. **Historical data on TEST — unchanged.** Read-only comparison with the pre-migration backup: `GH-DCC-000001` (2000.00, collected 2026-11-18 17:21 UTC) and `GH-DCS-000001` (2000.00, settled 17:23:16 UTC by user 1) are identical, including timestamps and idempotency key; driver 9 balance 2000.00 / 2000.00 / 0.00 matches its source rows; events still 2; 0 allocations, 0 reversals; 0 negative balances. The service checks ran with **0 write statements**.
4. **Legacy unallocated settlement.** On TEST, `GH-DCS-000001` shows 2000.00 unallocated and may be allocated (`mayAllocate = true`). Until a manager allocates it to `GH-DCC-000001`, order 3 shows *Not settled* and appears in the outstanding lists while the driver's custody balance is correctly 0.00. That is the approved behaviour (D-GH-025, no automatic backfill). Manual allocation without a balance change is proven by `test_legacy_unallocated_settlement_can_be_allocated_manually_without_balance_change` and the reconciliation test. I did not allocate it on TEST — that is a manager's business action.
5. **Permissions — PASS.** Every custody, settlement, report and export route returns 403 for driver, employee and team leader (`test_non_managers_are_forbidden_everywhere`). The driver cannot settle or reverse their own custody. The order and invoice financial panels render for managers only.
6. **Arabic / English — PASS.** Lang parity green; HTTP tests render the dashboard tabs in AR and EN; browser pass (AR/RTL + EN/LTR × 375/768/1280): 60/60 pages OK. No Blade changes since that pass.

**Remaining defects in this feature:** none known.

**Observations (outside this feature, unchanged):** the pre-existing `tests/Feature/LoginCsrfRoundTripTest.php` writes two `testing.ERROR … no such table: users` lines into `storage/logs/laravel.log` on every full-suite run (test noise, not an application error). On phones the dashboard tab strip scrolls sideways by design.
