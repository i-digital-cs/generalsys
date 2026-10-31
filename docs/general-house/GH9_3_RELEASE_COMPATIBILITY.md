# GH-9.3 — Release Compatibility

**Date:** 2026-10-31

---

## Isolated RC

| Item | Value |
|---|---|
| Branch | `release/general-house-gh1-gh8` |
| Worktree | `/tmp/gh91-rc` |
| Tip (start of GH-9.3) | `af4186f` |
| Tracking | ahead of `origin/main` (unpushed) |
| Attendance SSOT `7ed3ed2` ancestor? | **No** |

Working TEST tree: `release/attendance-ssot-grace-payroll-rc-20261022` @ `adede4b` (+ dirty `error_log` only; not committed).

---

## RC contents verified

| Requirement | Status |
|---|---|
| GH-1…GH-8 feature commits | Yes |
| GH-9 / GH-9.1 security + docs | Yes (`ad27b06` / `08c865d`) |
| GH-9.2 launch policy + zone migration + tests + docs | Yes (`4ce3db4`…) |
| All 9 GH migrations present in RC | Yes |
| Required shared deps (YearMonth, menu, AppModule, drivers) | Present |

### Non-GH files intentionally in RC (from GH-9.1)

| Path | Why |
|---|---|
| `app/Support/YearMonth.php` + callers (`HoursComparisonService`, dashboards, Approvals) | Month-end Carbon fix shared with Attendance dashboards |
| `tests/Feature/DriverRoleTest.php`, `EmployeeDashboardTest.php` | Align expectations after GH menu / YearMonth |
| Menu / lang / `AppModule` | GH navigation |

These are **not** the Attendance SSOT grace/payroll commit `7ed3ed2`.

---

## Production identity still required before LIVE

Do **not** assume `origin/main` equals LIVE.

Owner/ops must supply **before deploy**:

1. LIVE git SHA / tag / deploy artifact fingerprint.  
2. Confirmation LIVE DB engine/version (expected MariaDB 11.4.x).  
3. Confirmation LIVE has (or will receive) all GH migrations listed above.  
4. Explicit push + merge + deploy authorization.

**Unresolved:** LIVE host identity vs `origin/main` (LIVE not accessed this phase).
