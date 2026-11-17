# GH Production Delivery Migration Safety Report

**Environment:** TEST only (`https://generalsys.idigital-cs.com/`)  
**Release branch:** `release/generalsys-full-20261108`  
**LIVE baseline:** `94426fc` (neither delivery-schedule migration present/run)  
**Date:** 2026-11-17  
**LIVE changes / push / deploy:** not performed  

---

## Exact root cause

Two migrations formed an unsafe pair for production:

| Migration | Risky behaviour |
|-----------|-----------------|
| `2026_11_15_135901_add_scheduled_delivery_at_to_gh_orders_table` | Added `scheduled_delivery_at` **and** backfilled **every** order to pickup-day **08:00 Asia/Dubai** (synthetic appointments). |
| `2026_11_15_160000_clear_assumed_same_day_scheduled_delivery_at` | Cleared **`WHERE scheduled_delivery_at IS NOT NULL`** — wiping synthetic **and** any legitimate appointments. |

On a LIVE upgrade from `94426fc` (column absent), running the old pair would: invent fake schedules, then blank all schedules. That violates “never create synthetic appointments” and “never clear legitimate appointments,” and is especially dangerous if managers set real schedules between the two steps or if the clear ran against a DB that already had real data.

---

## Fix applied (TEST codebase)

| File | Change |
|------|--------|
| `database/migrations/2026_11_15_135901_…php` | **Schema only** — nullable column + index. **No backfill.** Idempotent if column exists. |
| `database/migrations/2026_11_15_160000_…php` | Clears **only** rows whose `scheduled_delivery_at` exactly equals pickup local day @ 08:00 Asia/Dubai (UTC). Legitimate other timestamps preserved. Idempotent. |
| `docs/DATABASE_SCHEMA.md` | Clarifies NULL = legacy allowed; new orders enforced in app validation. |
| `tests/Feature/GeneralHouse/DeliveryScheduleMigrationSafetyTest.php` | Fresh migrate, synthetic-only clear, partial state, create-requires-schedule, idempotency. |
| This report | — |

Application rules unchanged: create/full-edit still **require** delivery date+time; column remains **nullable** for history; WORKING→DELIVERY timing rules unchanged.

---

## Database safety analysis

### Desired production end state

- `gh_orders.scheduled_delivery_at` exists, **nullable**.
- All pre-existing LIVE orders remain **`NULL`** until a manager sets a real appointment.
- No financial / payment / invoice / custody rows touched.
- No truncate / refresh / reseed.

### Fresh LIVE path (`94426fc` → this release)

1. `135901` adds column → all existing rows stay `NULL` (no synthetic write).
2. `160000` scans for synthetic 08:00 pattern → **0 rows** updated on a clean add.
3. App continues to require schedule on **new** orders only.

### Compatibility with current LIVE DB

LIVE at `94426fc` does not have these migrations. Upgrading with the **corrected** files is the safe path. Do **not** deploy the pre-fix migration contents.

---

## TEST migration history

| Check | Result |
|-------|--------|
| `2026_11_15_135901_…` | **Already Ran** (batch 17) |
| `2026_11_15_160000_…` | **Already Ran** (batch 18) |
| Column present | **Yes** |
| Orders with `scheduled_delivery_at` | **0** (`GH-ORD-000001` → `NULL`, status `DELIVERY`) |
| `migrations` table edited | **No** |
| DB reset / reseed | **No** |

### Implications of editing already-run TEST migrations

- Laravel will **not** re-execute them on TEST (`migrate` sees them as Ran).
- TEST end state already matches the desired outcome (column present, no synthetic schedules).
- Changing the PHP files only affects **future** environments (LIVE / new clones) that have not run these batch numbers yet.
- No silent rewrite of `migrations` rows; no `migrate:fresh` on TEST.

If a **non-TEST** environment had run the **old** `135901` backfill but **not** yet `160000`, deploying the fixed `160000` clears only synthetic 08:00 values and keeps legitimate appointments — safer than the blanket NULL update.

---

## Test results

```text
php artisan test --filter=DeliveryScheduleMigrationSafetyTest
→ PASS (6 tests, 17 assertions)

php artisan test --filter=GeneralHouse
→ PASS — 371 passed (2111 assertions), 16.79s

vendor/bin/pint --dirty → passed (auto-fixed clear migration style)
```

Covered: fresh migrate no synthetic; clear only synthetic; partial old-backfill + manager appointment; create still requires schedule; add-column idempotent; clear idempotent.

Frontend: not affected (no asset change). `npm run build` not required for this fix.

---

## Release commit

| Field | Value |
|-------|--------|
| Branch | `release/generalsys-full-20261108` |
| Fix commit (migrations + tests + initial report) | `ef16713a834411f13eaee7543141aa3ac9ccb4e9` (`ef16713`) |
| Docs follow-up | on branch tip — run `git rev-parse HEAD` / `git log -2` |
| Ahead of origin | 2 commits (not pushed) as of report authoring |

**Push / production deploy:** blocked pending owner authorization.

---

## Is the release safe for production deployment?

| Question | Answer |
|----------|--------|
| Safe to migrate LIVE from `94426fc` with **corrected** migrations? | **YES** — adds nullable column, no synthetic backfill, no blanket clear. |
| Safe if old migration PHP were deployed unchanged? | **NO** — do not ship the pre-fix pair. |
| TEST DB needs repair migrate? | **No** — already at safe end state. |
| Overall for this concern | **SAFE FOR PRODUCTION** after this commit is what LIVE runs |

Owner still needs to authorize push + LIVE deploy separately from this TEST work.
