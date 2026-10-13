# Attendance test matrix (Task 1 + Task 1.6)

| Scenario | Test | Expected |
|---|---|---|
| Late 0/5/10/15 → counted 0 | `AttendanceGracePolicyTest::test_late_grace_threshold_not_deduction` | pass |
| Late 16/20/30 → full raw | same | pass |
| Early 15 → 0; 16 → 16 | `test_early_grace_threshold_not_deduction` | pass |
| Example both within grace | `test_example_both_within_grace_zero_deficit` + summary/history | balanced, deficit 0 |
| Late exceeds grace | `test_example_late_exceeds_grace` + `test_late_beyond_grace_counts_full_and_deficit` | deficit 16 |
| Early exceeds grace | `test_example_early_exceeds_grace` | deficit 16 |
| Both exceed | `test_example_both_exceed_grace` | deficit 40 |
| Surplus | `test_example_surplus_not_deficit` + history surplus kind | surplus, not OT |
| Independent graces | `test_graces_are_independent` | late exempt, early counted |
| Effective date boundary | `AttendanceGracePolicyTest::test_effective_date_boundary` | Sep inactive; Oct 1+ active |
| Sep summarize stored | `AttendanceGraceEffectiveDateTest::test_september_summarize_uses_stored_late_not_new_grace` | grace off; stored 10 |
| Sep recalculate freeze | `test_recalculate_does_not_rewrite_september_derived_fields` | late/early/status unchanged |
| Oct 1 grace close | `test_october_first_applies_grace_on_clock_out` | 0/0 balanced |
| Oct 16-min late | `test_october_sixteen_minute_late_counts_full` | late 16 |
| Sep history kind | `test_september_history_kind_stays_legacy_shortfall_not_grace_balanced` | shortfall |
| APPROVED refresh blocked | `test_approved_payroll_cannot_refresh_from_attendance` | 403 |
| PAID edit blocked | `test_paid_payroll_cannot_be_edited` | 403 |
| Open shift no LATE status | `AttendanceServiceTest::test_flexible_start_never_auto_marks_late_on_clock_in` | late_minutes 0 while open |
| TL late override | `AttendanceTimeSummaryTest::test_summarize_shows_override_after_tl_adjustment` | applied ≠ system |
| Register shortfall vs surplus | `AttendanceHistoryServiceTest` | pass |
| Payroll draft / OT refresh | `PayrollDraftTest` filter | pass (no money policy change) |
| Multi-punch / gaps | `AttendanceMultiPunchTest` | pass |
| Breakdown UI | `AttendanceTimeBreakdownTest` | pass (Oct fixtures for grace UI) |
