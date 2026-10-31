# GH-9.2 — Release Candidate Verification

**Date:** 2026-10-31

---

## Branches

| Branch | Tip (local) | Role |
|---|---|---|
| `release/attendance-ssot-grace-payroll-rc-20261022` | `7d9496f` | Working TEST tree (Attendance preserved; GH-9.2 committed) |
| `release/general-house-gh1-gh8` @ `/tmp/gh91-rc` | `a0b9e67` | Isolated RC from `origin/main` |
| Attendance SSOT `7ed3ed2` in RC? | **No** | Verified (`merge-base --is-ancestor` = false) |

**Not pushed. Not merged.**

---

## RC must contain

| Item | Status |
|---|---|
| GH-1…GH-8 | Yes |
| GH-9 / GH-9.1 docs + fixes | Yes (`08c865d` and earlier) |
| GH-9.2 launch config + migration + tests + docs | Yes (`4ce3db4` + tip doc `a0b9e67`) |
| Migration `2026_10_31_170000_gh92_zones_validation_only_launch` | Yes |
| Cherry-pick source on Attendance branch | `3781a22` / `7d9496f` |

---

## Production baseline compatibility

| Evidence | Conclusion |
|---|---|
| RC base = `origin/main` | Compatible with remote default branch tip known at GH-9.1 |
| LIVE not accessed | LIVE identity **unverified** against running host |
| Cherry-picks clean onto main | Compatible with **main** codebase |
| Attendance SSOT excluded | Avoids shipping Attendance RC with GH |

**Unresolved without LIVE access:** whether LIVE host currently runs exactly `origin/main` or an older tag. Report as **unresolved LIVE baseline** — do not assume.

---

## Automated results (working tree)

| Suite | Result |
|---|---|
| Full `php artisan test` | **1211 passed**, 1 skipped, 0 failed |
| `--filter=GeneralHouse` (post-commit recheck) | **106 passed** (402 assertions) |

---

## Browser UAT (TEST, manager session)

| Check | Result |
|---|---|
| GH dashboard Arabic | PASS |
| Invoice show cash form | PASS (`تأكيد الدفع النقدي`) |
| Online initiation UI absent | PASS |
| Bank disabled note | PASS |
| English LTR full click-through | Partial (HTTP EN tests PASS; browser EN toggle not completed) |
| Viewport 375/768/1280 | Not fully instrumented — layout uses existing Bootstrap responsive shells |

Full end-to-end create-order→pay on shared TEST data **not** force-mutated beyond viewing; cash partial/full proven in isolated automated HTTP UAT.
