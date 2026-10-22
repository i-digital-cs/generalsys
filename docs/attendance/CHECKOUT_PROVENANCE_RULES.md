# Checkout provenance rules

**SSOT type:** `App\Services\Attendance\CheckoutProvenance`  
**Consumed via:** `AttendanceCalculationResult` / `AttendanceCalculationEngine`

## Values

| Provenance | Meaning | Checkout verified? | Duration verified? |
|---|---|---|---|
| `OPEN` | No checkout (open segment / null clock_out) | No | No — missing ≠ zero |
| `EMPLOYEE` | Employee closed the punch (not auto, not force) | Yes | Yes → **verified worked hours** |
| `SUPERVISOR` | `force_closed_by` set | No (supervisor) | No — **recorded** only |
| `AUTO_STALE` | `is_auto_clock_out` | No | No — **recorded / unverified** (may be fabricated) |

## Metric glossary

| Metric | Definition |
|---|---|
| Verified checkout | Provenance `EMPLOYEE` only |
| Auto-generated checkout | `AUTO_STALE` |
| Supervisor checkout | `SUPERVISOR` |
| Missing checkout | `OPEN` |
| Recorded duration | Raw span / stored raw hours (never silently zeroed) |
| Verified worked duration | Recorded duration only when provenance is `EMPLOYEE` |
| Credited duration | Applied/credited hours on the attendance row (cap/OT rules) |
| Payroll-approved duration | Immutable values on settled `payroll_records` |

## Presentation rules

1. Never label `AUTO_STALE` or `SUPERVISOR` duration as employee-verified worked time.
2. Show recorded hours with an **unverified** label when duration is not verified.
3. Do not replace unverified hours with `0.00` automatically.
4. Do not rewrite historical punches or APPROVED/PAID payroll to “fix” presentation.
5. Late minutes from SSOT `exactLateMinutes` remain window-relative facts; when `shiftReviewRequired()` is true, UI must say **late vs planned window (review)**, not plain “late”.

## Shift review required

`AttendanceCalculationResult::shiftReviewRequired()` is true when clock-in is `after_window` relative to the resolved planned window (and related AUTO_STALE misalignment).  
Do **not** invent a replacement shift in UI — show review-required and keep the historical assignment/profile window visible.
