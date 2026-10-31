# GH-9 — Release Isolation Plan (NOT EXECUTED)

**Phase:** GH-9  
**Date:** 2026-10-31  
**Constraint:** Prepare strategy only — **do not** merge, cherry-pick, push, or deploy in this phase.

---

## 1. Current situation

| Fact | Value |
|---|---|
| Working branch | `release/attendance-ssot-grace-payroll-rc-20261022` |
| HEAD | `fd9846a` (GH-8) |
| vs origin same branch | **ahead 11** (all GH commits) |
| Dirty | `M error_log` only |
| Known GH-8 | `fd9846a` confirmed |

### Commit stack (newest first)

```
fd9846a feat(gh): Phase GH-8 …
7ae2819 feat(gh): Phase GH-7 …
a26358e feat(gh): Phase GH-6 …
c03bce9 feat(gh): Phase GH-5 …
54d2964 fix(gh): UTC store + repair …
a1fcd00 docs(gh): pickup display TZ …
f3694b0 docs(gh): GH-4 migration integrity …
794f01f feat(gh): Phase GH-4 …
57035e9 feat(gh): Phase GH-3 …
6ed4b38 feat(gh): Phase GH-2 …
c8d2938 feat(gh): Phase GH-1 …
──────── attendance (still on this branch ancestry vs main) ────────
ff9e36a docs(attendance): …
5a34058 docs(attendance): …
7ed3ed2 feat(attendance): SSOT grace tooltips and payroll suggestion integrity
… (rest of attendance RC / main history)
```

---

## 2. Path purity of GH feature range

Range examined: `c8d2938^..fd9846a` (GH-1 through GH-8 inclusive of TZ/docs fixes).

**No attendance/payroll calculation source paths** appeared in that range’s name filter for attendance/payroll/Gross/valuation.

GH commits **do** touch shared wiring (expected):

- `app/Providers/AppServiceProvider.php` (policy/bindings registration)
- `config/module_menu.php` / `config/menu.php`
- `lang/*/nav.php`
- `docs/DATABASE_SCHEMA.md`, `docs/TASKS.md`
- `.gitignore` (GH-1)
- `tests/Feature/MenuConfigTest.php` (GH-7)
- Factories under `database/factories/GeneralHouse/`

**Implication:** GH is not a pure subtree cherry-pick of only `app/Services/GeneralHouse/**`. Isolation must include shared menu/provider/schema docs/tests.

---

## 3. Risk of “just deploy this branch tip”

| Risk | Severity | Why |
|---|---|---|
| Ships Attendance RC commits (`7ed3ed2` et al.) with GH | **HIGH** | Tip ancestry includes attendance SSOT changes not necessarily LIVE-approved in the same window |
| Operator confuses TEST APP_ENV=production with LIVE | **HIGH** | Process risk |
| Cherry-pick without conflict verification | **MEDIUM** | Shared files (`AppServiceProvider`, menus) may conflict with LIVE `main` |

---

## 4. Recommended strategies (safest first)

### Strategy A — Dedicated branch from LIVE-tracking base + verified GH range (PREFERRED)

1. Identify LIVE-tracking base (typically `main` or the commit LIVE currently runs). **Confirm with owner** — do not assume.  
2. Create `release/general-house-gh1-gh8` from that base (**not** from attendance RC tip).  
3. Bring GH commits via **one** of:  
   - **A1.** `git cherry-pick c8d2938^..fd9846a` onto the new branch, resolve conflicts, run full test suite.  
   - **A2.** If cherry-pick conflicts heavily: create patch series / replay only GH paths + shared wiring with manual merge review.  
4. Verify diff vs base contains:  
   - All `gh_*` migrations  
   - GeneralHouse app/tests/views/lang/config  
   - Intended menu/provider changes  
   - **Zero** unintended attendance engine/payroll calculator diffs unless explicitly approved  
5. Tag `gh-1-to-8-rc1` when green.  
6. Push/tag **only** after owner approval (not part of GH-9).

**Do not assume cherry-pick is clean** without executing dry-run on a throwaway worktree (when owner authorizes isolation work).

### Strategy B — Split windows (Attendance first XOR GH first)

If Attendance `7ed3ed2` is already destined for LIVE:

1. Ship Attendance RC on its own approved release.  
2. Then apply Strategy A from the post-Attendance LIVE base (cherry-pick may be cleaner).

If Attendance must **not** ship yet:

1. Strategy A from pre-Attendance LIVE base.  
2. Keep Attendance RC branch untouched for its own audit.

### Strategy C — Deploy attendance branch tip (DISCOURAGED)

Only if owner **explicitly** accepts Attendance SSOT + GH in one LIVE window and both are release-ready. Still requires separate Attendance readiness sign-off (out of GH-9 scope).

---

## 5. Cherry-pick verification checklist (when authorized)

On throwaway worktree:

```text
[ ] Branch from agreed LIVE base
[ ] Cherry-pick c8d2938..fd9846a (or equivalent range including 54d2964 docs)
[ ] Conflicts resolved without dropping payment/invoice guards
[ ] php artisan test --filter=GeneralHouse → 93+ pass
[ ] php artisan test (full) → triage failures
[ ] git diff BASE...HEAD --stat reviewed for non-GH paths
[ ] migrate on scratch DB forward-only
[ ] No production .env committed
```

---

## 6. Dependencies between GH commits and existing app

| Dependency | Nature |
|---|---|
| Auth / roles (`User::isManager`, DRIVER role) | Existing HR-Pro identity |
| Menu system `config/menu.php` / `module_menu.php` | Shared; GH-7 adds DRIVER entry |
| Blade layout / Bootstrap RTL | Existing UI framework |
| `<x-money>`, lang, policies pattern | Existing conventions |
| Scheduler host cron | Existing Hostinger pattern |
| Attendance calculation SSOT | **Must remain untouched** by GH deploy |
| Mohra Gross tables | **Must not** receive GH payment writes (enforced by design) |

GH is **additive** schema (`gh_*`) — low FK coupling to HR tables except `users.id` references.

---

## 7. Untracked / ignored artifacts

| Item | Action |
|---|---|
| `error_log` (dirty) | Do not commit |
| `storage/backups/d14098_general_pre_gh*.sql` | Keep out of release artifact; local TEST backups only |
| `storage/logs/*` | Not part of release |

---

## 8. Safest one-sentence strategy

**Cut a dedicated GH release branch from the LIVE-approved base, replay `c8d2938..fd9846a` with conflict verification and isolated tests, and refuse to ship the Attendance RC tip as a substitute for a GH release.**

---

## 9. Explicit non-actions (GH-9)

- No merge  
- No cherry-pick execution  
- No push  
- No deployment  
- No LIVE checkout
