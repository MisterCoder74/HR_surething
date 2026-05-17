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
- [x] Phase 8 — Dashboards HR + Employee + Security Hardening (rate limiting, HTTP headers)
- [x] Phase 9 — Reports + CSV Export: presenze mensili, ferie/permessi, malattie, smartworking; dashboard field-name bug fixed
- [ ] Phase 10 — UI/UX Polish

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
│   ├── auth.php            → POST login/logout + rate limiting (5 attempts / 15 min)
│   ├── users.php           → GET profile · POST change_password / reset_password (HR) / list (HR)
│   ├── employees.php       → GET list/single · POST create / update / deactivate / reactivate
│   ├── attendance.php      → GET by employee+month · POST save / delete / bulk_summary
│   ├── leave-requests.php  → GET list/balance · POST submit / cancel / approve / reject
│   ├── smartworking.php    → GET list · POST submit / cancel / approve / reject
│   ├── sick-leave.php      → GET list/download_cert · POST submit / cancel / upload_cert / mark_received / close
│   ├── dashboard.php       → GET hr / employee dashboard KPIs + recent activity
│   └── reports.php         → GET presenze / ferie_permessi / malattie / smartworking [+CSV]
├── hr/
│   ├── dashboard.php       → HR home: KPI cards + recent activity table
│   ├── employees.php       → Employee registry CRUD
│   ├── attendance.php      → Attendance view + edit + CSV export
│   ├── requests.php        → Leave/permit requests — approve/reject
│   ├── smartworking.php    → Smartworking requests — approve/reject
│   ├── sick-leave.php      → Sick leave management — mark received/close
│   └── reports.php         → Reports + CSV export (all 4 modules)
├── employee/
│   ├── dashboard.php       → Employee home: balance + pending requests
│   ├── attendance.php      → Self-service monthly calendar
│   ├── request-leave.php   → Submit ferie/permesso
│   ├── request-smartworking.php → Submit smartworking request
│   └── sick-leave.php      → Declare sick leave + upload certificate
├── partials/
│   ├── sidebar_hr.php
│   └── sidebar_emp.php
├── data/
│   ├── users/credentials.json
│   ├── employees/employees.json  → [{employee_id, first_name, last_name, email, role, department, contract_type, hire_date, status}]
│   ├── attendance/{eid}.json     → [{date, type, check_in, check_out, notes}]
│   ├── leave_requests/requests.json → [{id, employee_id, tipo, data_inizio, data_fine, giorni, ore, motivo, stato, note_hr, creato_il, aggiornato_il}]
│   ├── leave_balance/{anno}.json → {eid: {ferie_totali, ferie_usate, ferie_residue, permessi_totali_ore, permessi_usati_ore, permessi_residui_ore}}
│   ├── smartworking/requests.json → [{id, employee_id, data_inizio, data_fine, giorni, motivo, stato, note_hr, creato_il, aggiornato_il}]
│   ├── sick_leave/records.json  → [{id, employee_id, data_inizio, data_fine, medico, stato, doc_status, cert_filename, creato_il, aggiornato_il}]
│   ├── sick_certs/              → uploaded certificates (protected by .htaccess)
│   └── config/
│       ├── rules.json           → business rules (preavviso, max days, etc.)
│       ├── holidays.json        → public holidays by year
│       └── login_attempts.json  → rate limiting store (auto-managed)
```

## Data field conventions
- Status/type values: English (`pending`, `approved`, `rejected`, `active`, `closed`, `attivo`, `cessato`)
- Field names: Italian (`stato`, `tipo`, `data_inizio`, `data_fine`, `creato_il`, `aggiornato_il`)
- Attendance records use `type` (English) and `date` (ISO 8601)
