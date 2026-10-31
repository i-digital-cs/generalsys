# General House — Timezone Policy (GH-4)

**Business timezone:** `Asia/Dubai` (`config('general_house.timezone')`).  
**Global Laravel / Attendance / Payroll / Gross:** unchanged (`config('app.timezone')`, currently `Africa/Cairo` on this host).

## Conversion policy

1. **Input (create/reschedule):** Calendar date + `HH:MM` pickup window fields are interpreted in the **GH business timezone** (`Asia/Dubai`) for new operations.
2. **Storage:** Eloquent datetime columns persist absolute instants (MySQL `TIMESTAMP` / app casting). Do not store ambiguous local strings without a zone.
3. **Display:** Format using the order’s stored `gh_orders.timezone` column (the zone used when the window was captured). Never rewrite historical windows by applying today’s GH zone.
4. **Eligibility / scheduler “now”:** Compare absolute `pickup_starts_at` against `Carbon::now(config('general_house.timezone'))` with the −24h lead rule.
5. **No silent reinterpretation:** Changing `GH_TIMEZONE` must not mutate existing `pickup_starts_at` / `pickup_ends_at` values. Rows keep their `timezone` label for display.

## GH-3 → GH-4 audit (TEST `d14098_general`, 2026-10-31)

| Check | Result |
|---|---|
| Existing `gh_orders` rows | **0** — no historical conversion required |
| Ambiguous data | **None** |
| Action taken | Default for **new** rows → `Asia/Dubai`; eligibility service reads `config('general_house.timezone')` |

If a future environment has GH-3 rows with `timezone = Africa/Cairo`, leave timestamps and the column as-is; only display and eligibility use absolute instants + per-row zone for UI.
