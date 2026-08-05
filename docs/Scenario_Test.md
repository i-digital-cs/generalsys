Here’s a **manual UAT checklist** to run yourself before handing the system to the client. Use three browsers/incognito windows (or log out between roles).

**Seed logins** (password: `password`):
- Manager: `admin@hrpro.test`
- Team leader: `leader@hrpro.test`
- Employees: `radwa@hrpro.test`, `mona@hrpro.test`

Also test **AR ↔ EN** and **phone (~375)** on every major screen.

---

## 0. Prep (5 min)

1. Open the app URL, confirm login page loads (AR RTL by default if locale is Arabic).
2. Switch language EN → AR → EN; layout must flip LTR/RTL, no missing keys.
3. Wrong password → translated error; 5+ failures → rate limit.

---

## 1. Manager (Admin) — full tour

### 1.1 Dashboard
- [ ] Stats cards show (total / active / inactive / contracts expiring).
- [ ] Expiring contracts table: search works; columns aligned.
- [ ] Team leaders’ today’s shifts (if any): search/filter; times/hours aligned.
- [ ] Team today / month tables + calendar: pick “Me” then an employee; day list filters work.
- [ ] No Clock / Daily report / My requests in **Admin** sidebar.

### 1.2 Employees
- [ ] List: server search + status filter; page search toolbar; Edit opens.
- [ ] Create employee: all required fields; save; appears in list with auto code.
- [ ] Edit: change salary/name; rest days add/remove (outside main form); documents upload (valid PDF/image), reject bad mime; photo if used.
- [ ] Inactive employee: still listed when filtered; cannot be used as active where expected.

### 1.3 Shifts (same screen as TL)
- [ ] Pick date; roster shows employees **and** team leaders.
- [ ] Assign FULL_TIME / Online ½ / Online full; EXTRA/OT only as allowed.
- [ ] Edit / cancel scheduled; tables searchable; times aligned.
- [ ] Assign a TL a shift for **today** → shows on Admin dashboard.

### 1.4 Rest days
- [ ] Search employee cards; add date; remove date; table aligned.

### 1.5 Approvals
- [ ] Pending / decided / all pills.
- [ ] Accept permission → ACCEPTED; Reject → REJECTED (no money shown).
- [ ] Vacation approve → rest day consumed; reject → rest day kept.
- [ ] Force on REJECTED (if shown) with confirm.

### 1.6 Reports review
- [ ] Filter employee + status; open report; reply; employee report locks.
- [ ] XSS: if you paste `<script>alert(1)</script>`, it must show as text, not run.

### 1.7 Evaluations
- [ ] Month/year; team list search; open employee; save monthly scores + text.
- [ ] Daily scores on report (if used): 4 scores + attendance auto.

### 1.8 Penalties
- [ ] Grid loads; edit amount; create missing rule; search/filter active/inactive.
- [ ] TL must get **403** if URL opened directly.

### 1.9 Payroll (privacy #1)
- [ ] TL must **not** see Payroll in menu; direct URL → **403**.
- [ ] Generate month → drafts appear (even if attendance incomplete, with warning if configured).
- [ ] List: search + status; Review: earnings/deductions, 5 scores, attendance, hours gaps, adjustments.
- [ ] Edit draft counts / recalc; base salary updates after employee salary change.
- [ ] Approve → locked; export Excel / PDF; download works.
- [ ] Money uses `<x-money>` style formatting; columns aligned.

### 1.10 Advances
- [ ] Create advance; list filters; employee sees own only.
- [ ] Generate payroll → monthly deduction; approve payroll → remaining decreases → FULLY_DEDUCTED when cleared.

### 1.11 Annual reports
- [ ] Pick employee + year; grid months; evaluation average; search/filter months.

### 1.12 Settings
- [ ] Language/currency defaults save; flash success.

---

## 2. Team leader

Log in as `leader@hrpro.test`.

### 2.1 Dashboard
- [ ] Quick actions + holidays (if any).
- [ ] Team today / month scoped to **own team only** (no other team names).
- [ ] Focus “Me” vs team member; calendar + day table.

### 2.2 Clock (TL self)
- [ ] With **no** plan: only default shift type (e.g. Full time).
- [ ] With plan: must match assigned type; cannot free-switch FT ↔ Online.
- [ ] EXTRA/OT only if assigned that day.
- [ ] Clock in → timer / open session; clock out → hours recorded.
- [ ] Same shift type twice same day → rejected.

### 2.3 Daily report
- [ ] After clock-out: submit report; before clock-out: blocked.
- [ ] After Manager/TL reply: locked.

### 2.4 My requests
- [ ] Permission: submit; see status after Manager decision.
- [ ] Vacation: needs rest day; swap date; overlap rejected.
- [ ] Force rejected (if allowed) with confirm.
- [ ] Tables: search + status filter.

### 2.5 Shifts / Rest days / Approvals / Reports / Evaluations
- [ ] Same as Admin but **only own team**.
- [ ] Cannot open other team’s employee (403).
- [ ] No payroll / advances / penalties / annual money screens in menu.

### 2.6 My evaluation
- [ ] Read-only monthly scores; history searchable.

---

## 3. Employee (`radwa` / `mona`)

### 3.1 Dashboard
- [ ] Status + hours today + month planned/actual.
- [ ] Big CTA → Clock.
- [ ] Today sessions table searchable; day list filters (work/shortfall/rest/vacation).

### 3.2 Clock
- Same rules as TL clock (plan match, EXTRA/OT only if assigned).
- [ ] Mobile: CTA large, usable at ~375px.

### 3.3 Profile
- [ ] Own data only; another employee URL → 403.
- [ ] Rest days read-only; documents download own only.

### 3.4 Daily report / Requests
- Same as TL self-service; team-scoped notifications if bell exists.

### 3.5 My salary
- [ ] Sees APPROVED/PAID only; DRAFT → 403.
- [ ] Breakdown: money, 5 scores, deductions; PDF download.
- [ ] TL opening `/salary` → 403.

### 3.6 Advances
- [ ] Own advances list only; search/status if rows exist.

### 3.7 Evaluation
- [ ] Read-only month; history table/search.

---

## 4. Cross-cutting (must pass)

| Check | Pass criteria |
|--------|----------------|
| RTL/LTR | Every screen you touched looks correct in AR and EN |
| Tables | Headers and cells share alignment; numbers not flipped left/right oddly |
| Search/filters | List pages filter without breaking empty state |
| Privacy | TL never sees money/payroll; employee never sees others’ payroll/docs |
| Notifications | Submit permission/report → bell/update for TL/Manager; decision → employee |
| Mobile | Clock, requests tabs, salary cards usable at phone width |
| Security basics | CSRF on forms; no raw HTML from reports; login rate limit |

---

## 5. Suggested run order (≈ 60–90 min)

1. Manager employees + assign shifts + rest days for today/tomorrow  
2. Employee clock in/out + daily report  
3. TL reply report + score + approvals  
4. Manager payroll generate → review → approve → employee My Salary  
5. Advances + one vacation swap  
6. AR/EN + phone pass on those same screens  
7. Negative checks: TL → payroll URL 403; employee → other profile 403  

---

## 6. Sign-off

Only give to client when:
- [ ] All role paths above checked  
- [ ] No blocker bugs written down (or fixed)  
- [ ] Production `.env`: `APP_DEBUG=false`, real DB, strong Manager password (not `password`)  

If you want, after you run this I can turn any bugs you find into a short fix list in priority order.