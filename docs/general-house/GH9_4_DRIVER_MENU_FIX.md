# GH-9.4 — Driver Service Sidebar Correction

**Date:** 2026-10-31  
**STATUS:** Done on TEST

---

## Problem

When DRIVER users opened GH routes (`AppModule::SERVICE`), the sidebar rendered the full `config/module_menu.service` list — including invoices, payments, GH dashboard, and reports — even though policies returned 403.

HR menu (`config/menu.DRIVER`) was already correct (workspace + clock + report + media).

---

## Fix

| Piece | Change |
|---|---|
| `app/Support/GeneralHouse/GhServiceMenu.php` | Filter service menu items via existing Gates / `GhCatalogAccess` / `GhOrderAccess` / `AppModule` (same pattern as `GrossMenu`) |
| `resources/views/layouts/partials/sidebar.blade.php` | Apply `GhServiceMenu::filterForUser` for SERVICE module |
| Empty finance/catalog headings | Dropped when no child links remain |
| Driver workspace | Injected into SERVICE sidebar when `GhOrderAccess::mayUseDriverWorkspace` (so drivers retain a GH entry point without finance) |

**Not used:** hardcoded `if ($user->isDriver())` to hide finance. Visibility follows the same authorization helpers the controllers already use.

Server-side 403 on finance controllers/policies is unchanged.

---

## Tests

`tests/Feature/GeneralHouse/Gh94DriverServiceMenuTest.php`

- Driver filtered menu: no invoices/payments/dashboard/reports/orders; has workspace  
- Manager filtered menu: finance retained; no driver workspace inject  
- Driver sidebar HTML AR+EN: finance route URLs absent  
- Manager sidebar HTML: finance present  
- Driver direct finance GET: Forbidden  
- HR `menu.DRIVER` routes unchanged  

---

## Changed files

- `app/Support/GeneralHouse/GhServiceMenu.php` (new)  
- `resources/views/layouts/partials/sidebar.blade.php`  
- `tests/Feature/GeneralHouse/Gh94DriverServiceMenuTest.php` (new)  
- this document  
