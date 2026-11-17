# GH Orders — Manual Transitions Phase 3.1 (Owner-approved)

**Environment:** TEST (`/home/d14098/generalsys.idigital-cs.com`)  
**Date:** 2026-11-15  
**LIVE:** not modified · **Commit/push:** not performed · **Financial data repair:** not performed

---

## Overall status

**PHASE 3.1 COMPLETE (TEST)** — all four owner decisions implemented with integrity guards.  
Previous Phase 3 “PASS (safe only / COMPLETED+CANCELLED blocked)” is superseded by this report.

**Follow-up (same day):** Task A board regression fixed; Task B reopen financial integrity strengthened; Phase 4 invoice-correction audit published at `docs/GH_COMPLETED_ORDER_INVOICE_CORRECTION_AUDIT.md` (no financial mutations).

---

## Task A — OrderBoardV2 danger badge regression

| Field | Value |
|-------|--------|
| Status | **PASS** |
| Cause | **Outdated test expectation** after smart-warning UI. Board cards render overdue via `gh-warning-badge--red` + `data-warning-code="overdue_pickup"` + card `is-overdue`, not legacy `gh-order-card__badge--danger`. `assertSee(__('gh.driver.overdue'))` previously passed only as a **substring** of `Overdue for pickup`. |
| Fix | Updated `OrderBoardV2Test` to assert current warning contract; removed unused `.gh-order-card__badge--danger` CSS (dead after warning badges). **Not** a business-logic overdue regression; warnings still fire for past `pickup_starts_at`. |
| Files | `tests/Feature/GeneralHouse/OrderBoardV2Test.php`, `resources/scss/_general-house.scss` |

---

## Task B — COMPLETED → DELIVERY → COMPLETED financial integrity

| Field | Value |
|-------|--------|
| Status | **PASS** |
| Verified | No duplicate payment/collection; original `delivered_at` / driver / notes preserved; invoice `ISSUED` + `total_payable` unchanged; balance SSOT `remaining_due` / `payment_status` unchanged (`0.00` / paid); reopen audited (`MANUAL_STATUS_TRANSITION` + `financial_untouched`); recomplete audited (`preserved_delivery` + `no_new_collection`); **drivers forbidden** from reopen (403); managers only. |
| Test | `OrderManualTransitionTest::test_completed_to_delivery_preserves_financials_and_holds` (strengthened) |

---

## Task C — Phase 4 invoice correction audit

| Field | Value |
|-------|--------|
| Status | **PASS** (audit only) |
| Deliverable | `docs/GH_COMPLETED_ORDER_INVOICE_CORRECTION_AUDIT.md` |
| Implementation | **Not started** — no issued-document mutations; owner decisions required for under-billing / void-reissue |

---

## Pickup-history integrity finding

| Question | Finding |
|----------|---------|
| Did WORKING → PICKUP previously clear `picked_up_at`? | **Yes (Phase 3).** Demotion nulled `picked_up_at` / `pickup_confirmed_by` / notes and only snapshot them into `gh_order_events.payload.cleared_pickup`. |
| Is original confirmation preserved in durable audit? | Event payload retained the snapshot, but the **order row** lost the live evidence, so subsequent PICKUP→WORKING could not use the original stamp without re-confirm. |
| Phase 3.1 fix | WORKING → PICKUP **no longer clears** pickup columns. Payload uses `preserved_pickup` with `cleared: false`. `confirmPickup` re-open path preserves existing `picked_up_at` / confirmer (no overwrite). |

**Never silently destroy pickup evidence — implemented.**

---

## Decision 1 — COMPLETED → DELIVERY

| Field | Value |
|-------|--------|
| Status | **IMPLEMENTED** |
| Files | `GhOrderManualTransitionService.php`, `GhOrderStateService.php` (ALLOWED + `confirmDelivery` preserve path), `GhDriverDeliveryPaymentService.php`, `OrderManualTransitionTest.php`, langs, logistics UI |
| Behavior | Manager may reopen COMPLETED → DELIVERY with reason. Original `delivered_at` / `delivered_by` / notes / drivers stay on the order. Invoice + payments untouched (`financial_untouched` in audit). Auto-promote held until Resume (or manual forward). Recomplete via `confirmDelivery` does **not** rewrite delivery stamps and **skips** driver collection when `delivered_at` already set / balance already paid. |
| Tests | `test_completed_to_delivery_preserves_financials_and_holds` — PASS |

---

## Decision 2 — CANCELLED → active (safe restore)

| Field | Value |
|-------|--------|
| Status | **IMPLEMENTED** (conditional) |
| Files | `GhOrderManualTransitionService.php` (`explainCancelRestoreBlocker`), `GhOrderStateService.php` ALLOWED, tests, langs |
| Behavior | Restore allowed only when financially/operationally safe: **blocked** if `invoice_issued_at` set, or invoice has payments / refunds / credit notes / financial corrections. Safe restore clears cancel fields into audit (`restored_from_cancel`) and targets LATER / PICKUP / WORKING per evidence (WORKING requires existing pickup; PICKUP blocked if pickup already confirmed — use WORKING). |
| Tests | `test_cancelled_restore_safe_path_and_blocks_with_invoice` — PASS |
| Remaining owner note | Orders cancelled **after** irreversible settlements stay blocked by design — no unsafe path shipped. |

---

## Decision 3 — Indefinite manager hold + Resume

| Field | Value |
|-------|--------|
| Status | **IMPLEMENTED** |
| Files | Migration `2026_11_15_170000_add_auto_promote_hold_to_gh_orders_table.php`, `GhOrder` casts, `GhOrderManualTransitionService` (`auto_promote_held*`, `resumeAutoPromote`, `isAutoPromoteSuppressed`), `GhOrderEventType::AUTO_PROMOTE_RESUMED`, route `gh.orders.resume-auto-promote`, logistics partial (hold banner + Resume), `OrderController` view data, schema doc, langs |
| Behavior | Replaces fixed 24h `suppress_auto_until`. Demotions (PICKUP→LATER, DELIVERY→WORKING, COMPLETED→DELIVERY) set indefinite `auto_promote_held`. Scheduler respects hold with **no time expiry**. Manager Resume clears hold and writes `AUTO_PROMOTE_RESUMED`. Manual forward / `markDeliveryReady` also clear hold. |
| Tests | `test_valid_backward_pickup_to_later_holds_indefinitely_until_resume`, `test_working_to_delivery_and_back_with_indefinite_hold`, `test_show_page_lists_resume_and_permitted_destinations_for_manager` — PASS |

**DATABASE CHANGES:** YES  
**SCHEMA MIGRATIONS CREATED:** YES  
**DATA MIGRATION OR BACKFILL:** NO  
**PRODUCTION DEPLOYMENT PROCEDURE:** NOT RUN (TEST only; migrate applied on TEST)  
**HARDCODED ID SAFETY:** NOT APPLICABLE  
**DATABASE MIGRATION POLICY:** PASS

---

## Decision 4 — Early LATER → PICKUP

| Field | Value |
|-------|--------|
| Status | **IMPLEMENTED** |
| Files | `GhOrderManualTransitionService.php` (`requiresReason` / `early_force` audit), logistics UI reason flags, langs, tests |
| Behavior | Authorized managers may force LATER → PICKUP before eligibility when a pickup driver is assigned and a reason is provided. Audit payload includes `early_force: true` + `pickup_driver_id`. Missing assignment or missing reason → validation error. |
| Tests | `test_early_later_to_pickup_requires_reason_assignment_and_audits` — PASS |

---

## Transition matrix (Phase 3.1)

| From → To | Notes |
|-----------|--------|
| LATER → PICKUP | Eligible **or** early force (reason + pickup driver) |
| PICKUP → LATER | Reason; indefinite hold |
| PICKUP → WORKING | Requires existing `picked_up_at` |
| WORKING → PICKUP | Reason; **preserves** pickup evidence |
| WORKING → DELIVERY | Requires pickup; clears hold |
| DELIVERY → WORKING | Reason; indefinite hold |
| COMPLETED → DELIVERY | Reason; preserve delivery + financials; hold |
| CANCELLED → LATER/PICKUP/WORKING | Reason; blocked if invoice/payments/refunds/credits/corrections |

Drivers remain forbidden (403).

---

## Commands / evidence

### Phase 3.1 implementation
```text
php artisan migrate --force
  → 2026_11_15_170000_add_auto_promote_hold_to_gh_orders_table DONE
php artisan test --filter=OrderManualTransitionTest
php artisan route:list --name=gh.orders.resume
```

### Follow-up (Task A + B + C audit)
```text
php artisan test --filter='OrderBoardV2Test::test_card_has_exact|OrderManualTransitionTest::test_completed_to_delivery'
  → 2 passed

php artisan test --filter=GeneralHouse
  → Tests: 326 passed (1858 assertions)

Docs:
  docs/GH_ORDER_MANUAL_TRANSITIONS_REPORT.md (updated)
  docs/GH_COMPLETED_ORDER_INVOICE_CORRECTION_AUDIT.md (created)
```

---

## End-of-step report

```text
Task ID: GH-Phase-3.1-followup-A-B-C
Commands: OrderBoardV2Test fix; reopen integrity assert; GeneralHouse filter; docs
Task A (board badge): PASS
Task B (reopen financial integrity): PASS
Task C (invoice correction audit): PASS (audit only; no mutations)
Automated tests: PASS — 326 passed (1858 assertions)
UI/UX: N/A for Task A test-contract fix; scss dead-class removal only
Next task allowed: yes (Phase 4 implementation awaits owner decisions)
DATABASE CHANGES: NO (follow-up)
PRODUCTION DEPLOYMENT PROCEDURE: BLOCKED (TEST only)
```
