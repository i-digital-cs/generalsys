# GH-9.2 — Release Candidate Verification

**Date:** 2026-10-31

---

## Branches

| Branch | Tip (local) | Role |
|---|---|---|
| `release/attendance-ssot-grace-payroll-rc-20261022` | includes GH-9.2 commits after this phase | Working TEST tree (Attendance preserved) |
| `release/general-house-gh1-gh8` | will receive cherry-picks of GH-9.2 | Isolated RC from `origin/main` |
| Attendance SSOT `7ed3ed2` in RC? | **No** (verified GH-9.1) | |

**Not pushed. Not merged.**

---

## RC must contain

| Item | Status |
|---|---|
| GH-1…GH-8 | Yes (cherry-picked in GH-9.1) |
| GH-9 / GH-9.1 docs + fixes | Yes |
| GH-9.2 launch config + migration + tests + docs | Applied on working tree; cherry-pick onto RC as part of GH-9.2 closeout |
| Migration `2026_10_31_170000_gh92_zones_validation_only_launch` | Yes |

---

## Production baseline compatibility

| Evidence | Conclusion |
|---|---|
| RC base = `origin/main` @ `7bc6c45` | Matches remote default branch tip known at GH-9.1 |
| LIVE not accessed | LIVE identity **unverified** against running host |
| Cherry-picks clean onto main | Compatible with **main** codebase |
| Attendance SSOT excluded | Avoids shipping Attendance RC with GH |

**Unresolved without LIVE access:** whether LIVE host currently runs exactly `origin/main` or an older tag. Report as **unresolved LIVE baseline** — do not assume.

---

## Automated results (working tree)

| Suite | Result |
|---|---|
| Full `php artisan test` | **1211 passed**, 1 skipped, 0 failed |
| `--filter=GeneralHouse` | **104+** (includes Gh92*) |

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

| Tip after GH-9.2 cherry-pick | `4ce3db4` |
| Cherry-pick source | `3781a22` on Attendance branch |
| Attendance SSOT `7ed3ed2` in RC? | **no** |
