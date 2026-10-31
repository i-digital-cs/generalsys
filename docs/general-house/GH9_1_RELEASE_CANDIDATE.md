# GH-9.1 — Release Candidate

**Date:** 2026-10-31  
**Status:** Local RC prepared — **not pushed**, **not merged**, **not deployed**

---

## 1. Base selection

| Candidate | Verdict |
|---|---|
| Attendance RC tip | Rejected as LIVE ship vehicle (includes Attendance SSOT ancestry) |
| `origin/main` @ `7bc6c45` | **Selected** — LIVE-compatible base; Attendance SSOT commits (`7ed3ed2`+) are **not** ancestors of this tip relative to GH isolation |

`git rev-list --left-right --count origin/main...attendance-RC-tip` showed main is behind Attendance+GH; cherry-picking only GH onto main excludes Attendance SSOT.

---

## 2. Isolated branch

| Field | Value |
|---|---|
| Branch | `release/general-house-gh1-gh8` |
| Worktree | `/tmp/gh91-rc` (local) |
| Tip (after GH-9.1 fix cherry-pick) | `ad27b06` (remediation); GH-9.1 docs commit may sit above |
| Tracks | created from `origin/main` |
| Attendance SSOT `7ed3ed2` in ancestry? | **No** |
| Original Attendance RC | **Preserved** untouched history (`release/attendance-ssot-grace-payroll-rc-20261022`) |

### Commit stack on RC (`origin/main..HEAD`)

```
(docs GH-9.1 — if committed)
ad27b06 fix(gh): GH-9.1 remediation …
25ca832 docs(gh): GH-9 audit …
61b1c16 GH-8
f06ecc1 GH-7
e564b98 GH-6
fc91faa GH-5
5463dff UTC fix
76499a9 / 04b0384 docs
57da920 GH-4
bf3d8d0 GH-3
2592c91 GH-2
d401e04 GH-1
```

Cherry-pick of `c8d2938^..fd9846a` onto main: **clean** (auto-merge on `routes/web.php` / HoursComparison only).

---

## 3. Diff summary vs `origin/main`

- ~236 files, ~22k insertions (GH domain + menus + schema docs + tests)  
- **No** `AttendanceCalculationEngine` / payroll calculator accidental paths in RC diff name scan  
- Shared wiring: `AppServiceProvider`, `config/menu.php`, `module_menu.php`, nav lang, `routes/web.php`  
- Excludes: credentials, `storage/backups/*`, `error_log`

---

## 4. Verification on RC worktree

| Check | Result |
|---|---|
| `php artisan test --filter=GeneralHouse` | **96 passed** |
| Fresh `migrate:fresh` on disposable sqlite | **PASS** (all GH migrations applied in order) |
| Attendance SSOT ancestor | Absent |

Full suite was verified on the Attendance working tree after remediation (**1203 passed**). RC shares the same GH+fix commits atop main.

---

## 5. Explicit non-actions

- Not pushed to `origin`  
- Not merged to `main`  
- Not deployed to LIVE  
- Attendance RC branch not reset/rewritten  

---

## 6. Next steps (require owner)

1. Review RC diff / this document  
2. Approve push of `release/general-house-gh1-gh8` (optional remote backup)  
3. Approve LIVE deploy plan separately (`GH9_DEPLOYMENT_PLAN.md`)  
4. Decide financial launch matrix + C-GH-TAX-MIN  
