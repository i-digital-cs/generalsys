Login with the seeded accounts (password for all: `password`):

| Account | Role | What you should see |
|---------|------|---------------------|
| `admin@hrpro.test` | Manager | Admin dashboard, Employees CRUD, rates, documents, settings. Can also open `/team-leader/shifts`. |
| `leader@hrpro.test` | Team Leader | Generic `/dashboard`; **Shift Assignment** → **My team** (Radwa + Mona) + assign/edit/cancel shifts. No money; `/admin/...` → 403. |
| `radwa@hrpro.test` | Employee (EGP) | Login → **My dashboard** + Clock. Under Team Leader. |
| `mona@hrpro.test` | Employee (AED) | Same; also under Team Leader. |

### Quick smoke — TL team + shifts + employee calendar

1. Login **leader** → Shift Assignment → see **My team** (Radwa + Mona).
2. Assign Radwa Full time (today) and optionally another shift tomorrow.
3. Login **radwa** → dashboard shows **My assigned shifts** (all of them) + **Calendar** with those days marked.
4. Leader / Admin dashboards also show a month calendar (team shifts / holidays).
