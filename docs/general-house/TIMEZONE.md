# General House — Timezone Policy (GH-4)

**Business timezone:** `Asia/Dubai` (`config('general_house.timezone')`, overridable via `GH_TIMEZONE`).  
**Global Laravel / Attendance / Payroll / Gross:** unchanged (`config('app.timezone')`, currently `Africa/Cairo` on this host).

## Canonical representation (UTC)

| Layer | Rule |
|---|---|
| Form input | Date + `HH:MM` interpreted in GH business timezone |
| Service parse | `GhOrderService::parsePickupWindow` builds timezone-aware Carbon in GH zone |
| Eloquent | `App\Casts\GhUtcDateTime` — **set** writes `Y-m-d H:i:s` in **UTC**; **get** hydrates Carbon in **UTC** |
| MySQL | `TIMESTAMP` columns; connection `SYSTEM`/`UTC` on TEST — stored string is UTC |
| Display | `->timezone($order->timezone \|\| GH zone)` in Blade (create/edit/show/index) |
| Scheduler SQL | Bind UTC strings via `GhPickupEligibilityService::toUtcSql()` |
| Instant math | Absolute Carbon comparisons (eligibility lead hours) |

**Never** change `app.timezone`. **Never** add/subtract fixed hour offsets.

## Conversion chain (defect → repair)

**Bug (pre-repair):**

1. Parse: Dubai `14:00` → Carbon `+04:00`.
2. Default Eloquent `datetime` cast formatted in the Carbon’s own zone → wrote `14:00:00` digits.
3. MySQL `TIMESTAMP` + session UTC stored those digits as **14:00 UTC** (wrong; true Dubai 14:00 is **10:00 UTC**).
4. On read, Eloquent parsed digits as `app.timezone` (`Africa/Cairo`) → instant `14:00+02`.
5. Blade `->timezone('Asia/Dubai')` → **16:00** (+2h display shift).

**Fix:** `GhUtcDateTime` + idempotent data repair `utc_timestamp_repair_v1` (see `GhUtcTimestampRepairService`).

## Existing TEST data repair

- Pickup windows / logistics actor times written as **order timezone** wall-clock → reinterpret as that zone → UTC.
- Event `recorded_at`, most `occurred_at`, assignments → reinterpret as **Africa/Cairo** wall-clock → UTC.
- `PICKUP_CONFIRMED` (and similar) `occurred_at` → order timezone (evidence: +2h vs `recorded_at`).
- Laravel `created_at` / `updated_at` **not** rewritten (remain app-timezone cast).

Marker: `gh_sequences.name = utc_timestamp_repair_v1`.  
Command: `php artisan gh:repair-utc-timestamps` (dry-run) / `--apply`.

## Migration history

GH-1..GH-4 bookkeeping anomaly remains documented in `MIGRATION_INTEGRITY_AUDIT.md`. This repair adds only a **forward data migration** (`2026_10_31_120000_repair_gh_utc_timestamps`); it does not reorder batches or recreate tables.
