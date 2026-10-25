# General House — UI/UX Specification (Phase 0)

## 1. Design system

Follow `docs/UI_FRAMEWORK.md` + `AGENTS.md` §8:

- Brand primary `#79caf9`; soft cards; dark navy mode
- `__()` only; AR+EN; RTL+LTR; responsive 375/768/1280
- Input value color `--hp-input-value-color`; titles ≠ labels ≠ body
- KPI via `<x-stat-card>`; money via `<x-money>`
- No copy of third-party logos from reference screenshots

## 2. Navigation

Extend **General house** sidebar after Valuation:

```text
Overview (dashboard)
Catalog → Categories | Products | Treatments | Offerings
Customers → Customers | Zones | Contracts
Operations → Orders | Later | Pickup | Working | Delivery | Completed
Finance → Invoices | Payments | Outstanding
Drivers → (link / embedded)
Reports
```

Page ACL for non-Manager (Gross-like).

## 3. Invoice builder (Phase 5)

Two-panel desktop:

- RTL: summary **start**, catalog **end** (mirror LTR)
- Catalog: search, categories, product cards, treatment chips, qty ±
- Summary: customer, order ref, lines, discounts, VAT, min order, total, Finalize
- Mobile: stack catalog above summary or tab switch — confirm in Phase 5 mock

## 4. Driver workspace (Phase 7)

- Mobile-first cards; large touch targets
- Tabs: Later | Pickup | Working | Delivery | Completed | History
- Drag-and-drop + accessible “move up/down”
- Persist order via `gh_driver_list_orders`
- Confirm actions with confirmation dialogs
- Hide financial fields without permission

## 5. Feedback

- Flash success/error
- Validation inline
- Empty states
- Loading on finalize / confirm
- Confirm destructive/financial actions

## 6. Accessibility

- Focusable controls; keyboard for sort alternative
- Contrast per existing HR-Pro tokens
- `dir` from locale
