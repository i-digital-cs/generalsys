# Attendance RC — Git packaging report

**Date:** 2026-10-22  
**Repository:** `/home/d14098/generalsys.idigital-cs.com` (TEST)  
**Remote:** `origin` → `https://github.com/i-digital-cs/generalsys.git` (**not pushed**)

---

## Verdict

### **CONDITIONAL**

Release branch packaged and committed locally for owner review.  
**Not** pushed, **not** merged to `main`, **not** deployed to LIVE.  
Product CONDITIONAL factors (Oct stored drift backfill, overnight profiles) remain operational — outside this packaging task.

---

## 1. Repository verification

| Item | Value |
|---|---|
| Top-level | `/home/d14098/generalsys.idigital-cs.com` |
| Base `main` HEAD (unchanged) | `7bc6c45e0da6d3795b30b297a3bee3238907f5b4` |
| Release branch | `release/attendance-ssot-grace-payroll-rc-20261022` |
| RC commit SHA | **`7ed3ed2f61cf27e8c7f8ce3159a13a451e93fe37`** |
| Packaging-report commit | *(this file — follow-up docs commit on same branch)* |
| Parent of RC | `7bc6c45` (`main`) |
| Existing branches preserved | `main`, `release/attendance-forensic-rc1`, `backup/main-before-attendance-rc1-merge-20261022113557`, `fixd-hr-system` |

---

## 2. Porcelain handling

| Metric | Count |
|---|---:|
| Pre-package porcelain paths | **66** |
| Excluded | **1** (`error_log`) |
| Reviewed release paths intended | **65** |
| Staged/committed name entries | **62** |

**Why 62 ≠ 65:** Git recorded the three superseded Vite assets as **renames** (delete+add pairs collapsed):

- `app-D-MHGUTc.js` → `app-BBdTcOWU.js`
- `app-IzK3I7R8.css` → `app-DTRGYXEQ.css`
- `app-ClkB_cEM.css` → `app-gX4Nzyks.css`

All 65 logical release paths are in the commit; no release file was dropped.

**Explicit exclude:** `error_log` (generated PHP CLI noise).  
**Never staged:** `.env` / secrets.

**Staging method:** explicit `git add <paths>` only — **no** `git add .`.

---

## 3. Secret / unrelated scan

| Check | Result |
|---|---|
| Sensitive path names in index | None |
| Secret content patterns in staged diff | None |
| Unrelated application features | None observed |
| `error_log` in commit | **No** |

---

## 4. File manifest (RC commit `7ed3ed2`)

### Source (app / routes / lang / views / assets sources)

- `AGENTS.md`, `ENGINEERING_CONSTITUTION.md`
- Controllers: `Admin/PayrollController.php`, `Employee/ClockController.php`
- Attendance services: `AttendanceCalculationEngine`, `AttendanceCalculationResult`, `AttendanceReviewPresentation`, `CheckoutProvenance`, plus updates to History/Service/TimeCalculator/ClockPage/HoursComparison
- Payroll: `PayrollAggregator`, `PayrollDraftService`
- Portal: `PortalStatsService`
- `routes/web.php` (`admin.payroll.apply-suggested-late`)
- Lang EN/AR: `attendance.php`, `payroll.php`
- Blade: TL attendance index, payroll show, attendance components + new metric/review/session/`hp-tooltip`
- `resources/js/app.js`, `resources/scss/_hr-pro.scss`

### Compiled assets (Hostinger deploy convention: upload `public/build`)

- `public/build/manifest.json`
- `public/build/assets/app-BBdTcOWU.js`
- `public/build/assets/app-DTRGYXEQ.css` (LTR)
- `public/build/assets/app-gX4Nzyks.css` (RTL)

### Tests

- New: Engine, SSOT arch, UI tooltips, FinalTargeted, Grace E2E, Historical presentation, CrossScreen parity, PayrollGraceFinancialIntegrity
- Updated: `PortalStatsServiceTest`, `PayrollDraftTest`

### Documentation

- SSOT / consumer / discrepancy / provenance / parity / historical / targeted / tooltip / grace / payroll financial / final RC audit docs
- Updated `ATTENDANCE_CONSUMER_MATRIX.md`

**Stats:** 62 files, +4726 / −136 lines.

---

## 5. Working tree after packaging

| State | Detail |
|---|---|
| Current branch | `release/attendance-ssot-grace-payroll-rc-20261022` |
| Intentionally dirty | `error_log` only (excluded) |
| `main` | Still at `7bc6c45` — **not merged** |

---

## 6. Test baseline (from FINAL RC audit)

| Item | Result |
|---|---|
| Inventory | **311 passed / 1451 assertions** |
| `npm run build` | PASS → assets committed above |

Re-run before LIVE cutover is recommended; packaging itself did not re-execute the suite.

---

## 7. Deployment prerequisites (owner)

1. **Owner review** of branch `release/attendance-ssot-grace-payroll-rc-20261022` @ `7ed3ed2`.  
2. Explicit approval to **push** remote (not done).  
3. Explicit approval to **merge** to `main` (not done).  
4. TEST deploy: upload code + `vendor` (unchanged packages) + **`public/build`**; clear view/config cache on TEST only.  
5. Do **not** run Oct attendance backfill unless separately approved.  
6. Do **not** modify LIVE or APPROVED payroll.  
7. Operational follow-ups remain: 30 real Oct stored-vs-exact drifts; overnight/window product decisions (see `FINAL_RELEASE_CANDIDATE_AUDIT.md`).

---

## 8. Stop line

| Action | Status |
|---|---|
| Push | **NOT done** |
| Merge to `main` | **NOT done** |
| LIVE deploy | **NOT done** |
| Historical attendance rewrite | **NOT done** |
| Backfill | **NOT done** |

---

## 9. Final packaging verdict

### **CONDITIONAL**

Git packaging **succeeded** on a dedicated TEST release branch with only reviewed RC paths. Remaining CONDITIONAL status reflects product/ops gates (drift backfill, LIVE approval), not a packaging failure.
