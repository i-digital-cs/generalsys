# General House — Excel import (master data only)

TEST app only. Managers import via **Download example → fill → Upload & preview → Confirm**.
All routes live under `service/gh/import/{entity}/…` (`gh.import.*`), handled by one thin
`GhImportController`; authorization is the existing `create` policy of each model (Manager-only).

## Entities and duplicate keys

| Entity | Index page | Duplicate key (existing rows are **skipped**, never overwritten) |
|---|---|---|
| `categories` | Service categories | `name_en` (case-insensitive) |
| `treatments` | Treatments | `name_en` (case-insensitive) |
| `products` | Products (+ optional images ZIP) | `code` (upper-cased) |
| `offerings` | Offerings & prices | product + treatment |
| `zones` | Zones | `name_en` (case-insensitive) |
| `customers` (+ one optional address) | Customers | `phone`; if empty `email + type`; rows with neither are rejected |
| `contracts` (B2B, multi-row) | Contracts | `reference` when given; otherwise customer + dates |

No schema change was needed: categories/treatments are matched by `name_en`, products by `code`.

## Flow

1. `POST …/preview` — validates the workbook (`xlsx/xls`, max 4 MB, max **500 rows**, required columns), builds a
   `GhImportPreviewResult` (total / valid / invalid / duplicate / create / update, row errors, rows to apply) and stores it
   in the cache for **30 minutes** (`GhImportSession`, bound to user + entity; file/database cache is enough).
2. `GET …/preview/{token}` — summary, error table, `GET …/errors/{token}` downloads the errors as xlsx.
3. `POST …/confirm/{token}` — single use. Re-checks duplicates, creates valid rows through the existing domain
   services (`GhCategoryService`, `GhProductService`, `GhContractService`, …), skips the rest, flashes a summary.

Templates are generated on the fly (`GhSpreadsheetSupport::writeTemplate`): sheet 1 *Import Data* (5–8 realistic rows),
sheet 2 *Instructions*, sheet 3 *Reference Values* (live categories / zones / products / treatments). No xlsx blobs are committed.

## Template examples use live data

* **Products** — if ACTIVE categories exist, the example rows use their `name_en` (cycled, 6 rows, fresh codes); otherwise static sample rows are shown and the Instructions sheet says to **import categories first** (this note is always present on the products sheet). Round-trip: download → upload → preview yields no `category_not_found` when categories exist.
* **Offerings** — live ACTIVE product codes × ACTIVE treatment names (pairs without an offering first); static fallback otherwise.
* **Contracts** — live B2B customer phone/email and ACTIVE offerings (product code + treatment) when available; static fallback otherwise. `reference` is left blank and `import_group` is `G1`, `G2`, `G3`.

## Contracts

`reference` is **optional**. Grouping (one contract per group): same `reference` → else same `import_group` → else consecutive rows
with the same customer + dates. Reference handling: provided and already exists → group skipped; provided and new → used as-is;
blank → auto-allocated `GH-CTR-######` through `GhContractService::create` (locked `gh_sequences.contracts`). Re-uploading a
blank-reference group whose customer + dates already match an existing contract is skipped as a duplicate.
Header values come from the first non-empty cell of the group; status defaults to `DRAFT`. A contract is atomic: one invalid
row rejects the whole contract. Overlap rules of `GhContractService` are applied at confirm time and reported as commit errors.

## Product images

* `image_url` — fetched after the product is created by `GhSafeImageFetcher`: HTTPS only, port 443, no credentials,
  public IPs only (127/8, 10/8, 172.16/12, 192.168/16, 169.254/16, `::1`, ULA, link-local blocked; DNS results and
  every redirect hop re-checked), 10 s timeout, 5 MB cap, content validated as JPG/PNG/WebP.
* `image_filename` — matched (case-insensitive) against an optional ZIP uploaded together with the products file.
  `GhZipImageArchive` rejects path traversal / absolute paths / executables, allows max 50 images and 20 MB, validates each
  image by content, and extracts into a private temp folder that is deleted after confirm (stale folders purged after 1 h).
* A product is never blocked by an image problem: the failure is listed in the confirm warning (`image_errors`).
* Stored through `GhProductService::attachStoredImage()` under `gh-product-images/{id}` with a random name (single `image_path`).

## Deliberately NOT importable

Orders, invoices, payments, refunds and credit notes have **no Import button and no import route**
(`GET /service/gh/import/orders/template` is a 404). They carry financial history, numbering, state machines,
VAT/balance invariants and audit trails; importing them would bypass `GhOrderStateService`, issuance and payment
services and could silently alter historical money records. They are created only through their workflows.

## Out of scope / notes

* Update-on-duplicate mode is not offered (skip only).
* Customers import creates at most one address per customer row; extra addresses are managed in the UI.
* Preview does not simulate contract overlap with existing ACTIVE contracts (reported when confirming).
