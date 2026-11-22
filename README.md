# SimHealth API

Laravel JSON API for the SimHealth health management system. The Vue SPA lives in a
separate repository: [sayi-victor/simhealth](https://github.com/sayi-victor/simhealth).

The legacy PHP/OpenEMR application this replaces is kept as a read-only reference in a
separate project; nothing here reads from it at runtime.

## Stack

- PHP 8.3, Laravel 13, Sanctum 4 (bearer tokens)
- MySQL 8 (database `simhealth`), PHPUnit 12, Pint

## Local setup

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Then seed creates one administrator:

| Username | Password |
|---|---|
| `admin` | `ChangeMe123!` |

Change that password before any non-local deployment.

The SPA is a separate origin, so set `CORS_ALLOWED_ORIGINS` (comma separated) and point the
SPA's `VITE_API_BASE_URL` at this API, e.g. `http://localhost:8000/api`.

## API conventions

- Routes live in `routes/api.php`, all prefixed with `/api`.
- Auth: `POST /api/auth/login` returns a Sanctum token; send it as
  `Authorization: Bearer <token>`. `GET /api/me` returns the current user.
- Every authenticated route also requires a permission (`permission:patients.view` etc.),
  mapped to roles in `RbacSeeder`.
- Success responses are `{ "data": ... }` (Laravel API Resources).
- Errors are always
  `{ "error": { "code": "VALIDATION_ERROR", "message": "...", "details": {...} } }`.
- Lists are paginated (`?page=&per_page=`); filters and sorting are documented per endpoint
  in the controller.
- Money is `decimal(12,2)` and travels as a string; dates are `Y-m-d`, datetimes ISO-8601.

## Endpoint groups

| Group | Base path | Module |
|---|---|---|
| Auth | `/api/auth/*`, `/api/me` | login, logout, profile |
| Facilities | `/api/facilities` | locations |
| Patients | `/api/patients` | registration, contacts, consents, employers, insurances, issues, history |
| Encounters | `/api/encounters`, `/api/form-definitions` | visits, vitals, SOAP notes, dynamic LBF forms, signing |
| Calendar | `/api/appointments`, `/api/appointment-categories` | scheduling, status flow, check-in |
| Pharmacy | `/api/drugs`, `/api/prescriptions` | formulary, lots, prescribing, FEFO dispensing |
| Orders | `/api/orders`, `/api/order-catalog` | lab, imaging, procedures, results, review |
| Documents | `/api/patients/{id}/documents`, `/api/encounters/{id}/documents`, `/api/orders/{id}/documents`, `/api/documents/{id}`, `/api/document-categories` | attachments, upload, authorized download, delete |
| Clinical | `/api/wards`, `/api/admissions`, `/api/surgeries`, `/api/immunizations` | inpatient, theatre, vaccination |
| Billing | `/api/invoices`, `/api/claims` | charges, payments, claims, adjudication |
| Inventory | `/api/warehouses`, `/api/inventory-items` | stock ledger |
| Reports | `/api/reports/*` | operational reporting |
| Admin | `/api/admin/*` | users, roles, audit log |

## Quality gates

```sh
php artisan test          # 44 feature tests
vendor/bin/pint --test
```

## Project rules

See `AGENTS.md`.
