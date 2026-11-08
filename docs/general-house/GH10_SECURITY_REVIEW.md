# GH-10 — Security Review

## Authentication / authorization

- Web routes under existing `service/gh` manager middleware stack.
- Policies: `GhCreditNotePolicy`, `GhRefundPolicy`, `GhFinancialCorrectionPolicy`.
- `GhServiceMenu` Gate-filters new finance nav items; drivers never see them (regression: `Gh94DriverServiceMenuTest`).
- Status / money fields empty `$fillable` on correction models; services use `forceFill`.

## Threat mitigations

| Threat | Control |
|---|---|
| IDOR | Route model binding + policy on every show/mutation |
| Mass assignment | Empty fillable; validated Form/controller input only |
| Duplicate create | Unique `idempotency_key` |
| Concurrent issue/payout | `lockForUpdate` on note/refund + invoice; re-check caps |
| Unauthorized payout | Policy + SoD (approver ≠ payout) + feature flag |
| Unapproved ledger effect | Only ISSUED CN / PAID_OUT refund / EXECUTED FC affect SSOT |
| CSRF | Laravel CSRF on POST forms |
| Sensitive exposure | No payment secrets; reasons are operational text |

## Separation of duties

Config defaults (TEST):

- `require_distinct_approver = true`
- `require_distinct_payout_actor = true`
- `payment_correction_execute_enabled = false`

## Residual risks / open controls

1. Same role (MANAGER) can hold request/approve/payout **across different users**; there is no dedicated `REFUND_APPROVER` role yet — documented owner decision if finer RBAC required.
2. Payment-correction EXECUTE remains fail-closed until accounting policy signed off.
3. Evidence attachments for refunds are text-only in GH-10 (no file upload channel added).
