# GH-9.4 — Release Artifact Verification

**Date:** 2026-10-31

---

## Trees

| Tree | Branch | Tip (after GH-9.4 docs/code) | Notes |
|---|---|---|---|
| Working TEST | `release/attendance-ssot-grace-payroll-rc-20261022` | (see git after commit) | Attendance RC preserved |
| Isolated GH RC | `release/general-house-gh1-gh8` @ `/tmp/gh91-rc` | cherry-picked after commit | Unpushed |
| Attendance SSOT `7ed3ed2` in RC? | **No** | |

**Not pushed. Not merged. LIVE not accessed.**

---

## RC must contain

| Item | Status |
|---|---|
| GH-1…GH-8 | Yes |
| GH-9.1 security fixes + docs | Yes |
| GH-9.2 launch policy + zone migration | Yes |
| GH-9.3 verification docs | Yes |
| GH-9.4 driver sidebar filter + tests + docs | Applied on working tree; cherry-pick onto RC in closeout |
| All GH migrations including `2026_10_31_170000_gh92_zones_validation_only_launch` | Yes in RC |

---

## Baseline comparison

RC is based on `origin/main` with clean GH cherry-picks.  
Shared YearMonth / dashboard call-site fixes from GH-9.1 remain (not Attendance SSOT).

**LIVE compatibility:** unresolved without LIVE SHA / artifact evidence.

---

## Financial launch policy (unchanged)

Verified via `GhLaunchConfig` on TEST:

| Flag | Value |
|---|---|
| cash | true |
| partial | true |
| online initiation | false |
| bank transfer | false |
| driver collection | false |
| overpayment | false |
| refunds | false |
| min-order surcharge | false |
| default policy | VALIDATION_ONLY |
