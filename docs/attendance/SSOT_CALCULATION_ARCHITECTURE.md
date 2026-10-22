# Attendance SSOT calculation architecture

**Status:** TEST-validated · **LIVE deploy:** owner approval required  
**Policy version string:** `attendance-grace-v1` (`AttendanceCalculationResult::POLICY_VERSION`)

## Rule (non-negotiable)

All attendance business calculations must originate from the authoritative attendance calculation domain. Presentation layers are consumers, never independent calculation authorities.

Enforced in: `AGENTS.md` §1.1b · `ENGINEERING_CONSTITUTION.md` · `tests/Unit/Attendance/AttendanceSsotArchitectureTest.php`.

## Authoritative stack

| Layer | Class | Role |
|---|---|---|
| Façade / read model | `AttendanceCalculationEngine` | Entry point for consumers; builds `AttendanceCalculationResult` |
| Typed result | `AttendanceCalculationResult` | Canonical session facts (minutes + summary + provenance) |
| Persist | `AttendanceTimeCalculator::recalculate` | Writes counted late/early/status/hours on close |
| Display summary | `AttendanceTimeCalculator::summarize` → `AttendanceTimeSummary` | Breakdown for review UI |
| Grace | `AttendanceGracePolicy` | 15-min arrival/departure thresholds; duration balance; effective date |
| Windows / planned | `ShiftWindowResolver`, `ShiftConfig` | Expected start/end; planned hours |
| Counted metrics | `AttendanceService::exactLateMinutes`, `exactEarlyMinutes` | Date-aware; override wins on late |
| Punch facts | `AttendanceService::rawLateMinutes`, `rawEarlyMinutes` | Always pre-grace |
| Provenance | `CheckoutProvenance` | `OPEN` / `EMPLOYEE` / `SUPERVISOR` / `AUTO_STALE` |

Integer **minutes** are authoritative. Decimal hours use `AttendanceCalculationResult::hoursFromMinutes` / `minutesFromHours` (round half-up to 2 dp). Missing/open checkout is provenance `OPEN` and credited minutes `0` — not interchangeable with a verified zero-length session.

## Metric glossary (do not mix)

| Name | Meaning |
|---|---|
| Raw late / early | Punch vs planned window, before grace |
| Counted / exact late / early | After grace (or stored pre-effective date); payroll + cross-screen |
| Actual worked | Segment/raw hours (physical) |
| Credited / applied | What counts toward attendance credit (caps, OT approval) |
| Approved payroll minutes | Snapshotted on `payroll_records` when settled — **immutable** |

`HoursComparisonService` `shortfall_raw` / `shortfall_credited` remain **physical** planned−hours metrics (documented intentional), not grace deficit.

## Consumer contract

1. Inject `AttendanceCalculationEngine` (or call `exactLateMinutes` / `exactEarlyMinutes` already used by payroll).
2. Do not recompute `checkIn − shiftStart`, grace, or overnight boundaries in Blade/JS/controllers.
3. Aggregates (day/week/month) = sum of eligible session exact metrics with the same inclusion rules as payroll.
4. Manual credited/late overrides and OT admin decisions invalidate derived display via re-`summarize` / engine; settled payroll is never auto-rewritten.

## Historical & payroll safety

- Pre-`grace_policy_effective_date` (default `2026-10-01`): exact* returns **stored** columns (no silent reclassify).
- Fabricated auto-close suspects (17+1) are not rewritten by this SSOT work.
- `APPROVED` / `PAID` payroll `late_minutes_total` stays snapshot; live aggregator may differ until draft refresh.
