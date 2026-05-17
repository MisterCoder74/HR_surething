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
- [x] Phase 6 — Smartworking requests: employee submit (1 day notice, overlap check), HR approve/reject with notes
- [x] Phase 7 — Sick leave: employee declare + upload cert, HR mark received/close, doc_status tracking
- [x] Phase 8 — Dashboards (HR + Employee) + Security hardening (rate limiting, X-XSS-Protection, Permissions-Policy)
- [ ] Phase 9 — Reports + CSV/PDF Export
- [ ] Phase 10 — UI/UX Polish

## Security (Phase 8)
- **Rate limiting**: 5 failed login attempts per username → 15-minute lockout. Attempts stored in `data/config/login_attempts.json`.
- **Security headers**: X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy (all via `.htaccess`).
- **CSRF**: decorative token layer — meta tag + `X-CSRF-Token` header on all AJAX calls. Server-side validation intentionally omitted (internal tool, 10–20 users).

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
│   ├── auth.php            → POST login/logout + rate limiting (Phase 8)
│   ├── dashboard.php       → GET hr / employee dashboard data (Phase 8)
│   ├── users.php           → GET profile · POST change_password / reset_password (HR) / list (HR)
│   ├── employees.php       → GET list/single · POST create / update / deactivate / reactivate
│   ├── attendance.php      → GET by employee+month · POST save / delete / bulk_summary
│   ├── leave-requests.php  → GET list/balance · POST submit / cancel / approve / reject
│   ├── smartworking.php    → GET list · POST submit / cancel / approve / reject
│   └── sick-leave.php      → GET list/download_cert · POST submit / cancel / upload_cert / mark_received / close
├── hr/
│   ├── dashboard.php       → HR home — KPI cards + recent activity (Phase 8)
│   ├── employees.php       → Employee registry CRUD
│   ├── attendance.php      → Attendance view + edit + CSV export
│   ├── requests.php        → Leave/permit requests — approve/reject
│   ├── smartworking.php    → Smartworking requests — approve/reject (Phase 6)
│   ├── sick-leave.php      → Sick leave management — mark received/close (Phase 7)
│   └── reports.php         → Reports (Phase 9)
├── employee/
│   ├── dashboard.php       → Employee home — leave balance + quick actions (Phase 8)
│   ├── attendance.php      → Monthly calendar, add/edit last 7 days
│   ├── request-leave.php   → Submit ferie / permesso
│   ├── request-smartworking.php → Submit smartworking
│   └── sick-leave.php      → Declare sick leave + upload cert
├── partials/
│   ├── sidebar_hr.php
│   └── sidebar_emp.php
└── data/
    ├── config/
    │   ├── rules.json              → leave rules (notice days, max days, etc.)
    │   ├── holidays.json           → public holidays by year
    │   └── login_attempts.json     → rate limiting store (Phase 8)
    ├── users/credentials.json
    ├── employees/employees.json
    ├── leave_balance/2026.json
    ├── leave_requests/requests.json
    ├── smartworking/requests.json
    ├── sick_leave/records.json
    ├── sick_certs/                 → uploaded certificates (.htaccess protected)
    └── attendance/{YYYY-MM}.json
```

## Data schemas

### employees.json (array)
```json
{"employee_id":"e001","user_id":"e001","first_name":"Mario","last_name":"Rossi",
 "email":"...","role":"Sviluppatore","department":"IT","contract_type":"indeterminato",
 "hire_date":"2022-01-15","status":"attivo","phone":"","notes":""}
```

### leave_balance/YYYY.json (object keyed by employee_id)
```json
{"e001":{"anno":2026,"ferie_totali":26,"ferie_usate":0,"ferie_residue":26,
         "permessi_totali_ore":32,"permessi_usati_ore":0,"permessi_residui_ore":32,
         "ultimo_aggiornamento":"2026-01-01T00:00:00Z"}}
```

### leave_requests/requests.json (array)
```json
{"id":"lr_20260516_001","employee_id":"e001","type":"ferie",
 "date_from":"2026-06-01","date_to":"2026-06-05","days":5,
 "reason":"Vacanza estiva","status":"pending","hr_notes":"",
 "created_at":"2026-05-16T10:00:00Z","updated_at":"2026-05-16T10:00:00Z"}
```

### smartworking/requests.json (array)
```json
{"id":"sw_20260516_001","employee_id":"e001","date":"2026-05-20",
 "reason":"Riunione remota","status":"pending","hr_notes":"",
 "created_at":"2026-05-16T10:00:00Z","updated_at":"2026-05-16T10:00:00Z"}
```

### sick_leave/records.json (array)
```json
{"id":"sl_20260516_001","employee_id":"e001","start_date":"2026-05-16",
 "end_date":null,"estimated_days":3,"notes":"Influenza",
 "status":"active","doc_status":"missing","cert_filename":null,
 "created_at":"2026-05-16T10:00:00Z","updated_at":"2026-05-16T10:00:00Z"}
```

### attendance/{YYYY-MM}.json (array)
```json
{"employee_id":"e001","date":"2026-05-16","type":"presenza",
 "check_in":"09:00","check_out":"18:00","notes":""}
```
