# GH-9.2 — Approved Launch Policy (TEST implemented)

**Date:** 2026-10-31  
**Authorization:** Owner-approved for **TEST implementation only** (not LIVE deploy/push/merge).

---

## Owner decisions (final for initial launch)

1. **CASH only**, with **partial payments** allowed.  
2. **Minimum-order = VALIDATION_ONLY** — **no surcharge**.

---

## Authoritative configuration

| Source | Role |
|---|---|
| `config/general_house.php` | Values + env overrides |
| `App\Support\GeneralHouse\GhLaunchConfig` | Single read API for services/policies/Blade |

### Actual values (after GH-9.2)

| Flag | Value |
|---|---|
| `payments.cash_enabled` | `true` |
| `payments.partial_payments_enabled` | `true` |
| `payments.online_initiation_enabled` | `false` |
| `payments.online_gateway_confirm_enabled` | `false` |
| `payments.bank_transfer_enabled` | `false` |
| `payments.driver_collection_enabled` | `false` |
| `payments.overpayment_allowed` | `false` |
| `payments.refunds_enabled` | `false` |
| `min_order.default_policy` | `VALIDATION_ONLY` |
| `min_order.surcharge_enabled` | `false` |
| `min_order_surcharge_tax_class` | `null` (C-GH-TAX-MIN unresolved) |
| `GhMinOrderPolicy::DEFAULT` | `VALIDATION_ONLY` |

Fail-closed: unset payment capabilities default **false** via `GhLaunchConfig`.

---

## Server-side enforcement

- `GhPaymentService` + `GhPaymentPolicy` reject online/bank when disabled.  
- Online UI removed from invoice payment panel.  
- Engine: if surcharge disabled, `SURCHARGE` zones calculate as `VALIDATION_ONLY` (no adjustment).  
- Legacy surcharge with unresolved tax still blocks when surcharge capability re-enabled.

---

## Explicitly not implemented

- LIVE deploy / push / merge  
- Refunds / credit notes / voids  
- Online gateway / bank / driver collection  
- C-GH-TAX-MIN tax class assignment  
