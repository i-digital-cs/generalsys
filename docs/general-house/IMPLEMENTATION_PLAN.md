# General House — Implementation Plan (Phase 0)

## Gate rule

One phase → tests green → UI/UX gate (if Blade) → completion report → **owner approval** → next phase.  
No silent phase skipping. Align with `AGENTS.md` §0 and `docs/BUILD_AND_TEST_GATES.md`.

## Phase map

| Phase | Task IDs | Deliverable | Depends on |
|---|---|---|---|
| 0 | T-GH-0.* | Docs + decisions | — |
| 1 | T-GH-1.* | Catalog CRUD + offerings + prices | Phase 0 approval |
| 2 | T-GH-2.* | Customers, zones, contracts, min-order audit | Phase 1 |
| 3 | T-GH-3.* | Orders, schedule, assign, history | Phase 2 |
| 4 | T-GH-4.* | State machine + 24h job | Phase 3 + exception decisions |
| 5 | T-GH-5.* | Invoice builder + calc engine | Phase 4 + financial decisions |
| 6 | T-GH-6.* | Delivery lists + payments | Phase 5 |
| 7 | T-GH-7.* | Driver workspace | Phase 4–6 core |
| 8 | T-GH-8.* | Dashboard & reports | Phase 6 |

## Per-phase DoD

- [ ] Services own business rules; Policies enforce
- [ ] Migrations + schema doc (if DB)
- [ ] Unit + feature tests green
- [ ] UI/UX gate if Blade
- [ ] No Gross/HR regressions (targeted packs)
- [ ] Completion report pasted in `docs/TASKS.md` Progress log
- [ ] Production deploy **not** implied

## Suggested first code slice after approval

T-GH-1.1 migrations for categories/products/treatments/offerings → T-GH-1.2 services → T-GH-1.3 admin UI → tests.

## Out of order caution

Do not build invoice UI before PricingResolver + calc engine contracts are locked.
