| Account | Role | What you should see |
|---------|------|---------------------|
| `leader@hrpro.test` | Team Leader | Dashboard, **Clock**, **Daily report**, **My Requests**, Shifts, **Rest Days**, Approvals, Reports. No money. |
| `admin@hrpro.test` | Manager | Admin dashboard, **Clock**, **Daily report**, **My Requests**, Employees (rest dates on edit), Approvals, **Rest Days**, Reports, settings. |
| `radwa@hrpro.test` | Employee (EGP) | Dashboard, Clock, Daily report, **My Requests**. Under Team Leader. |
| `mona@hrpro.test` | Employee (AED) | Same; also under Team Leader. |

Password for all: `password`.

### Quick smoke — date-based rest days

1. Login **admin** → Employees → edit Radwa → Rest days → add a specific date → Save list.
2. Or login **leader** → **Rest Days** → add dates for Radwa/Mona.
3. Dashboard calendar shows those dates as Rest day; planned hours 0.

### Quick smoke — rest days + vacation swap

1. Login **leader** → **Rest Days** → add dates for Radwa (e.g. 2026-08-07).
2. Login **radwa** → My Requests → Vacations → see rest dates → pick **day off** + **rest day to replace** → Submit.
3. Login **leader** → Approvals → Vacations → see replace date → Accept.
4. Calendar: vacation day shows Vacation; the replaced rest date is gone (work day).

### Quick smoke — permission approve/reject + force (T-2.8 / T-2.9)

1. Login **radwa** → My Requests → submit Leave permission.
2. Login **leader** → **Approvals** → Accept or Reject (no amounts shown).
3. Login **radwa** → status shows Accepted / Rejected.
4. If Rejected: **leader** (or radwa / admin) → **Force** on Approvals / My Requests → status **Forced**, penalty ×2 (money hidden from TL).

### Where to see permission requests

| Role | Where | What you see |
|------|--------|--------------|
| **Employee / TL / Admin** | Sidebar → **My Requests** (`/requests`) | Own requests only |
| **Team Leader** | Sidebar → **Approvals** | Team employees only |
| **Admin** | Sidebar → **Approvals** | All (employees + TL + managers) |

Admin also gets a **bell notification** on every permission/report action (submit, decide, report, reply).

### Quick smoke — planned vs spent (T-2.13)

1. Login **leader** → Dashboard → **Team today** shows Radwa/Mona status.
2. Pick Radwa → day list + calendar show should-spend vs spent; month totals update when you change a shift on `/team-leader/shifts`.
3. Login **radwa** → Dashboard → month planned/spent cards + daily list + calendar.

### Quick smoke — daily report (T-2.5 + T-2.6)

1. Login **radwa** → Clock In → Clock Out.
2. Sidebar → **Daily report** → write text → Save.
3. Logout → login **leader** → **Reports review** → open pending → send reply.
4. Login **radwa** again → report shows TL reply and is locked (cannot edit).
5. (Optional) second shift same day still uses the **first** shift’s report row.
