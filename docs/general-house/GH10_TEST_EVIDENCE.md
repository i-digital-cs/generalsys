# GH-10 — Test Evidence

## Environment

| Item | Value |
|---|---|
| App path | `/home/d14098/generalsys.idigital-cs.com` |
| Database | `d14098_general` (verified via config bootstrap) |
| MariaDB | 11.4.x |
| Pre-migrate backup | `storage/backups/d14098_general_pre_gh10_20261031T135504Z.sql.gz` (gzip -t OK) |
| Migration | `2026_10_31_180000_create_gh_financial_correction_tables` → DONE |
| LIVE | Not touched |

## Automated tests

```text
php artisan test --filter=FinancialCorrectionsTest
→ 8 passed (40 assertions)

php artisan test --filter=PaymentTest
→ 10 passed

php artisan test --filter=GeneralHouse
→ 120 passed (485 assertions)  # includes FinancialCorrections + GH-1…GH-9.4
```

Covered scenarios (service + HTTP):

- Partial credit note; excess credit rejected
- Full credit after payment → customer credit → refund SoD → PAID_OUT
- Approved unpaid refund excluded from CashNet
- Refund above available rejected
- Driver forbidden on CN/refund/FC routes
- Payment recording correction request/approve; EXECUTE blocked; payment stays CONFIRMED
- Manager list pages 200
- Idempotent credit-note create

## UI/UX

Blade screens: credit-note list/create/show, refund list/create/show, financial-correction list/show; invoice payment panel balance context + actions. AR+EN keys added; logical Bootstrap spacing.

### Browser UAT (TEST, manager session)

| Screen | Result |
|---|---|
| `/service/gh/credit-notes` | OK — EN list + finance nav |
| `/service/gh/invoices/{id}` payment panel | OK — Create credit note / Request refund / Flag recording error |
| `/service/gh/invoices/{id}/credit-notes/create` | OK — balance context + lines |
| Same create after `/locale/ar` | OK — RTL Arabic strings |
| `/service/gh/refunds` | OK |
| `/service/gh/financial-corrections` | OK — execute-blocked banner present |

Responsive 375/768/1280: layout uses Bootstrap grid/table-responsive (spot-check via existing GH finance pattern; no dedicated device lab run).

## Regression notes

- Payment balance keys remain backward-compatible (`remaining` alias of `remaining_due`).
- Attendance / payroll suites not altered by GH-10 migrations (GH tables only).

## Open / not automated in this pass

- Explicit mixed VAT multi-line fixture beyond STANDARD share math
- Heavy concurrency stress (locks covered in service paths; no parallel PHPUnit)
- Full Attendance + Payroll + Gross + Valuation battery (recommend CI job; GH filter green)
