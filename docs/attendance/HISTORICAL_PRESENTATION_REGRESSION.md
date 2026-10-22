# Historical presentation regression

## Commands

```bash
php artisan test --filter='HistoricalPresentationIntegrityTest|AttendanceHistoryServiceTest|AttendanceCalculationEngineTest|CrossScreenAttendanceParityTest|AttendanceSsotArchitectureTest'
# → 26 passed (includes 5 new HistoricalPresentationIntegrityTest cases)

php artisan test --filter='Attendance'
# → 183 passed (794 assertions)
```

## Fixtures (anonymized 680 / 681)

| Test | Asserts |
|---|---|
| `test_fabricated_auto_close_is_unverified_with_shift_review` | AUTO_STALE; verified hours null; recorded 12; shift review; late 1383 untrusted |
| `test_verified_overnight_peer_is_trusted` | EMPLOYEE; verified 12.7; late 183 trusted |
| `test_missing_and_supervisor_checkout_semantics` | OPEN / SUPERVISOR |
| `test_history_row_flags_unverified_fabricated_session` | History flags + sessions provenance |
| `test_approved_payroll_snapshot_unchanged_when_live_late_differs` | Snapshot 50 vs live 1383 |

## Before / after (680-class presentation)

| Field | Before | After |
|---|---|---|
| Checkout | Times + “auto” badge | Explicit **Auto-closed (not employee verified)** |
| Hours | “Spent 12.00h” | **Recorded hours (unverified) 12.00h** + credited labeled separately |
| Late | “Late 1383” | **Late vs planned window (review) 1383** |
| Shift | Type only | Window `00:00–12:00` + **Shift review required** |
| DB / payroll | — | Unchanged |

## Files changed (this repair)

- `AttendanceCalculationResult.php`, `CheckoutProvenance.php`, `AttendanceHistoryService.php`
- `HoursComparisonService.php` (interval provenance meta)
- Blade: `attendance-punch-range`, `attendance-session-integrity` (new), `attendance-time-metrics`, `team-leader/attendance/index`
- Lang: `lang/en|ar/attendance.php`
- Tests: `HistoricalPresentationIntegrityTest.php`
- Docs: this file, `HISTORICAL_PRESENTATION_AUDIT.md`, `CHECKOUT_PROVENANCE_RULES.md`

**Migrations:** none.
