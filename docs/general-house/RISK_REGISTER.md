# General House — Risk Register (Phase 0, revised)

| ID | Risk | Likelihood | Impact | Mitigation | Owner |
|---|---|---|---|---|---|
| R-GH-01 | Accidental coupling to Mohra Gross data | M | H | Separate `gh_*` schema/routes/docs; no Gross financial writes; code review gate | Eng |
| R-GH-02 | Min-order surcharge tax class undefined → wrong VAT | H | H | **C-GH-TAX-MIN** before Phase 5; never assume exempt | Owner |
| R-GH-03 | Float / JS pricing authority | M | H | Single `InvoiceCalculationEngine` SSOT + unit tests | Eng |
| R-GH-04 | Duplicate ISSUE / payment | M | H | Unique constraints, txn finalize, payment `idempotency_key` | Eng |
| R-GH-05 | Scheduler miss on Hostinger (24h eligibility) | M | M | `schedule:run`; catch-up query; monitoring | Eng |
| R-GH-06 | DRIVER IDOR (pickup vs delivery scope) | M | H | Policies scoped by assignment responsibility + feature tests | Eng |
| R-GH-07 | Scope explosion vs Gross features | H | M | Phase gates; non-goals; corrections deferred until **C-GH-CORR** | Owner/Eng |
| R-GH-08 | Attendance/payroll regression | L | H | No shared table writes; no HR schema edits in GH phases | Eng |
| R-GH-09 | LIVE deploy without approval | L | H | AGENTS deploy rules; no auto CI deploy | Owner |
| R-GH-10 | Historical invoice mutation via catalog edits | M | H | Snapshots on ISSUE; catalog deactivate not rewrite | Eng |
| R-GH-11 | Timezone edge on 24h rule | M | M | Explicit timezone tests on eligibility | Eng |
| R-GH-12 | Drag-and-drop breaks status | M | M | Sort table separate from logistics status | Eng |
| R-GH-13 | Single-driver design regresses to shared assignment | M | H | Separate pickup/delivery assignment rows + history tests | Eng |
| R-GH-14 | Expired B2B contract silently prices new invoices | M | H | Validity check in `PricingResolver`; fail closed | Eng |
| R-GH-15 | Cancel after ISSUE without correction path | M | H | **C-GH-CANCEL** + no silent delete of ISSUED | Owner/Eng |
| R-GH-16 | Online init recorded as PAID | M | H | Distinct initiation vs confirmation statuses | Eng |
