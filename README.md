# Mini HR Vanilla

HR management system for small companies (10–20 employees).

## Stack
- PHP 8+ | Vanilla JS | CSS custom | JSON flat files

## Default credentials
| Role | Username | Password |
|------|----------|----------|
| HR Consultant | `admin` | `Admin1234!` |
| Employee | `mario.rossi` | `Pass1234!` |
| Employee | `lucia.bianchi` | `Pass1234!` |
| Employee | `andrea.verdi` | `Pass1234!` |

## Phases
- [x] Phase 0 — Setup, data architecture, CSS/JS, auth.php, json_helper.php
- [x] Phase 1 — Authentication: login backend, profile modal, change-password, HR reset-password
- [x] Phase 2 — Employee CRUD: list, add, edit, deactivate/reactivate (HR only)
- [x] Phase 3 — Attendance self-service (employee): monthly calendar, add/edit last 7 days
- [x] Phase 4 — HR attendance view + CSV export: edit/add any record, bulk summary, per-employee detail, CSV download
- [x] Phase 5 — Leave requests (ferie / permessi): employee submit + balance, HR approve/reject with notes
- [ ] Phase 6 — Smartworking requests
- [ ] Phase 7 — Sick leave + certificates upload
- [ ] Phase 8 — Dashboards (HR + Employee)
- [ ] Phase 9 — Reports
- [ ] Phase 10 — UI/UX + Security hardening
- [ ] Phase 11 — Testing & Bug Fixing

## File structure
```
/
├── index.php               → redirect to hr/ or employee/ based on role
├── login.php               → public login page
├── logout.php
├── auth.php                → session helpers (require_login, require_hr, require_employee, csrf)
├── style.css               → global CSS (custom properties, layout, components)
├── app.js                  → global JS (apiFetch, modals, toasts, calendar helpers)
├── api/
│   ├── auth.php            → POST login/logout
│   ├── users.php           → GET profile · POST change_password / reset_password (HR) / list (HR)
│   ├── employees.php       → GET list/single · POST create / update / deactivate / reactivate
│   ├── attendance.php      → GET by employee+month · POST save / delete / bulk_summary
│   └── leave-requests.php  → GET list/balance · POST submit / cancel / approve / reject
├── hr/
│   ├── dashboard.php       → HR home
│   ├── employees.php       → Employee registry CRUD
│   ├── attendance.php      → Attendance view + edit + CSV export
│   ├── requests.php        → Leave/permit requests — approve/reject (Phase 5) ✓
│   ├── sick-leave.php      → Sick leave management (Phase 7)
│   └── reports.php         → Reports (Phase 9)
├── employee/
│   ├── dashboard.php       → Employee home
│   ├── attendance.php      → Monthly attendance calendar (self-service)
│   ├── request-leave.php   → Ferie & permessi: balance + submit + history (Phase 5) ✓
│   ├── request-smartworking.php → Smartworking request (Phase 6)
│   └── sick-leave.php      → Sick leave submission (Phase 7)
├── partials/
│   ├── sidebar_hr.php
│   └── sidebar_emp.php
└── data/                   → JSON storage (protected by .htaccess)
    ├── users/credentials.json
    ├── employees/employees.json
    ├── attendance/{employee_id}.json
    ├── leave_balance/2026.json     → ferie/permessi balances per employee per year
    └── leave_requests/requests.json → all leave requests (pending/approved/rejected/cancelled)
```

## Setup
1. Copy to any PHP 8+ server (works in any subfolder — all paths are relative)
2. `chmod -R 770 data/`
3. Open `login.php` in browser

## API quick reference

### `api/attendance.php`
| Method | Params | Auth | Description |
|--------|--------|------|-------------|
| GET | `?month=YYYY-MM` | employee | Own records |
| GET | `?employee_id=e001&month=YYYY-MM` | HR | Any employee's records |
| POST `save` | `{employee_id?,date,type,check_in?,check_out?,notes?}` | any | Create/update record |
| POST `delete` | `{employee_id?,date}` | any | Delete record |
| POST `bulk_summary` | `{month}` | HR | Counts per employee for month |

### `api/leave-requests.php`
| Method | Params | Auth | Description |
|--------|--------|------|-------------|
| GET | `?action=list` | employee | Own requests |
| GET | `?action=list&stato=pending&tipo=ferie&employee_id=e001` | HR | All requests with optional filters |
| GET | `?action=balance` | employee | Own balance |
| GET | `?action=balance&employee_id=e001` | HR | Specific employee balance |
| GET | `?action=balance` | HR | All balances |
| POST `submit` | `{tipo,data_inizio,data_fine?,ore?,motivo?}` | employee | Submit new request |
| POST `cancel` | `{id}` | employee | Cancel own pending request |
| POST `approve` | `{id,note_hr?}` | HR | Approve pending request (deducts balance) |
| POST `reject` | `{id,note_hr}` | HR | Reject pending request (note required) |
