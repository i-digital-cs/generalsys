# Regression baseline — Task 2

**Updated:** 2026-10-13 (Phase 1 exit)  
**Environment:** PHPUnit sqlite `:memory:`

---

## Latest verified baseline (Phase 3 exit)

```bash
php artisan test
# → 932 passed (10097 assertions), 0 failed, 0 skipped
```

| Suite | Result |
|---|---|
| Full suite | **PASS** (932) |
| Prior Phase 2 | 925 passed |
| Prior Phase 1 | 922 passed |

Phase 0 snapshot (superseded): 914 pass / 7 fail — see `PHASE_1_EXIT_REPORT.md`.

---

## Behavior-preservation criteria (unchanged)

1. Protected 95 remain green after every Task 2 phase.  
2. Grace effective date / historical freeze / settled payroll invariants unchanged.  
3. TL payroll money routes remain 403.  
4. Schema only via new migrations; no historical attendance backfill in Task 2.  
5. Do not weaken assertions to force green.

---

## Per-module packs (for Phase 2+)

Unchanged from Phase 0 master plan — see `REFACTORING_MASTER_PLAN.md`.
