# General House — Financial Rules (Phase 0, revised)

**Status:** Design defaults for implementation planning.  
**Not legal advice.** Tax classifications and surcharge tax treatment require owner / tax-advisor confirmation before production finance goes live.

**Alignment:** Owner CONDITIONAL APPROVAL 2026-10-25.

## 1. Configuration defaults (APPROVED as product defaults)

| Setting | Default |
|---|---|
| Currency | **AED** |
| Price mode | **Tax exclusive** |
| Default VAT rate | **5%** (STANDARD_RATED) |
| Rounding | **HALF_UP** to 2 decimal places |
| Money storage | **DECIMAL** — no floating-point financial math |

All invoice math runs in **one authoritative server-side engine** (service layer). Controllers/UI are consumers only.

## 2. Tax classification (APPROVED enum; application per valid rules)

| Class | Meaning (product design) |
|---|---|
| `STANDARD_RATED` | Taxable at configured rate (default 5%) |
| `ZERO_RATED` | Taxable base may exist; tax = 0 by rule |
| `EXEMPT` | Exempt per rule — **not** a casual UI toggle |
| `OUT_OF_SCOPE` | Outside VAT scope |

**APPROVED constraint:** Discounts and unrestricted user toggles must **not** arbitrarily force exemption. Classification comes from offering / adjustment tax rules, not “discount = no tax.”

## 3. Discount & totals pipeline (APPROVED calculation order)

For each invoice finalize:

1. **Line gross** = unit price × quantity (per line).  
2. Apply **item-level discount** (fixed and/or percent — define precedence in engine: percent then fixed **PROPOSED** unless owner prefers otherwise).  
3. **Sum** discounted item amounts.  
4. Apply **invoice-level discount** (fixed and/or percent).  
5. **Allocate** invoice discount across **eligible** lines (deterministic algorithm; remainder handling documented in engine).  
6. Determine **minimum-order adjustment** (see §4).  
7. Compute **VAT** on the correct taxable base per line / adjustment tax class.  
8. Compute **final payable** = taxable/non-taxable components + VAT − payments (payments tracked separately).

**Guards:**

- No negative payable totals (clamp / reject finalize — engine policy: **reject** if discounts exceed eligible base).  
- Rounding allocation deterministic and repeatable for the same inputs.  
- Historical issued amounts frozen (snapshots on lines).

## 4. Minimum-order policy (APPROVED behaviors; tax treatment pending)

### 4.1 Policies per zone

| Policy | Behavior |
|---|---|
| `VALIDATION_ONLY` | Block finalize/order if eligible service value &lt; zone minimum |
| `SURCHARGE` | Add adjustment so eligible value reaches minimum |

**Proposed product default:** `SURCHARGE`.

### 4.2 Evaluation base (APPROVED)

Minimum threshold is evaluated against **eligible service value after discounts and before VAT**.

### 4.3 Example (APPROVED arithmetic shape)

- Service amount after discounts: AED 20  
- Zone minimum: AED 30  
- Minimum adjustment: AED **10**

### 4.4 Overrides (authorized only)

| Override | Effect |
|---|---|
| `APPLY_DEFAULT` | Use zone policy/amount |
| `OVERRIDE_AMOUNT` | Use alternate minimum amount |
| `WAIVE` | No surcharge / skip validation failure path |

### 4.5 Audit fields (APPROVED)

Record on override / application:

- Original minimum  
- Effective minimum  
- Adjustment amount  
- Override reason  
- Authorized actor  
- Timestamp(s)

### 4.6 Tax treatment of surcharge — REQUIRES OWNER CONFIRMATION (`C-GH-TAX-MIN`)

**Do not assume the adjustment is tax-exempt.**

Before financial implementation, owner must choose tax class for the minimum-order adjustment (e.g. inherit dominant line class, always STANDARD_RATED, OUT_OF_SCOPE, etc.). Document choice in DECISION_LOG when confirmed.

## 5. B2B pricing (APPROVED)

- Contract prices **override** standard offering prices when contract is **valid** for the invoice date.  
- Restricted catalogs: only authorized contract offerings appear for that customer.  
- Issued line prices are **frozen** snapshots.  
- Expired contracts must **not** silently price new invoices (engine fails closed → standard price only if policy allows, or block — **PROPOSED:** block B2B order without valid contract price for selected offering).

## 6. Invoice issuance (APPROVED v1)

- One primary invoice per order.  
- Transaction-safe finalize; uniqueness prevents duplicate ISSUE.  
- Issued invoices are not silently edited.

## 7. Corrections — designed, not implemented (`C-GH-CORR`)

| Mechanism | Intent | Tax/finance implication (to document when approved) |
|---|---|---|
| **Void** | Cancel erroneous ISSUED invoice before/without replacement | Reverses liability; numbering/audit retained |
| **Credit note** | Partial/full credit against ISSUED | Negative tax/document linkage |
| **Refund** | Cash/online return of collected funds | Payment ledger outbound; may follow credit note |

**Do not implement** correction workflows until detailed rules are owner-approved.

## 8. Payments (APPROVED)

- Dedicated **General House payment ledger** (`gh_payments`).  
- **Do not** write GH collections into Mohra Gross financial tables.  
- Methods: Cash, Online; support partial payments and outstanding balances.  
- Online **initiation ≠** success.  
- Cash: traceable to authorized **collector**.  
- Duplicate recording prevented via `idempotency_key` / unique external refs.

## 9. Multi-currency

**PROPOSED default:** AED only in v1.  
**REQUIRES OWNER CONFIRMATION (`C-GH-MULTI-CUR`)** if other currencies are required at launch.
