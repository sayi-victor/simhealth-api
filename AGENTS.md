# AGENTS.md — SimHealth API

> Read this before changing code. The Vue SPA is a **separate repository**
> ([simhealth](https://github.com/sayi-victor/simhealth)); the legacy PHP app is a **separate
> read-only reference project**. Nothing here imports from either at runtime.
>
> Update **§6 Progress Log** when you finish a task.

---

## 1. What this repo is

A Laravel JSON API for a health management system (EMR). It owns the database schema, business
rules, permissions and audit trail. It renders no HTML.

- Stack: PHP 8.3, Laravel 13, Sanctum (bearer tokens), MySQL 8, PHPUnit 12, Pint.
- Database: `simhealth` (local), configured through `.env`. Tests use SQLite `:memory:`.
- No HR, payroll or accounting modules — out of scope permanently.

## 2. Layout

```
app/Http/Controllers/Api   one controller per module, thin
app/Http/Requests          validation only, grouped by module
app/Http/Resources         the only thing that shapes responses
app/Http/Middleware        RequirePermission (permission:<slug>)
app/Models                 Eloquent models, relations, casts, scopes
app/Services               all business logic and transactions
database/migrations        schema; every table has PK, FKs, indexes
database/seeders           RBAC, reference data, one admin user
routes/api.php             the entire API surface
tests/Feature/Api          one file per module
```

## 3. Rules

**R1 — No logic in controllers.** Controllers validate (FormRequest), delegate to a service,
return a Resource. No queries, no `DB::` calls, no business branching in controllers.

**R2 — One transaction per write.** Multi-table writes go through a service method wrapped in
`DB::transaction()`. Stock, payments and admissions are locked with `lockForUpdate()` before
mutation — never read-then-write without a lock.

**R3 — Explicit output only.** Every response goes through an API Resource or a fixed array
shape. Never return a model directly. Money is `decimal(12,2)` (string in JSON); dates are
`Y-m-d`; datetimes ISO-8601; ids are integers.

**R4 — Errors are uniform.** Throw, don't return error payloads. `bootstrap/app.php` maps
validation/auth/authorization/404/429 into `{error:{code,message,details}}`. Add a new mapping
there rather than inventing a shape in a controller.

**R5 — Permissions are explicit.** Every authenticated route carries
`permission:<module>.<action>` and the slug must exist in `RbacSeeder`. Prescribing and
dispensing, creating and resulting orders, and billing and claiming are deliberately separate
permissions.

**R6 — Audit the things that matter.** Clinical/administrative writes (records, stock, money,
auth, permissions) call `AuditLogger`. Log identifiers and actions, never PHI values.

**R7 — Schema discipline.** New tables: `id()` primary key, `foreignId(...)->constrained()`,
indexes on every FK and on filter/sort columns, `timestamps()`, `softDeletes()` where records
must survive deletion, `down()` implemented. Never model a legacy 100+ column table as-is —
split it.

**R8 — Migrations are additive in production.** New behaviour = new migration. Never edit a
migration that has shipped. No `DROP`/`TRUNCATE` outside a local database.

**R9 — Tests are part of the endpoint.** Every endpoint needs a feature test covering the happy
path, validation failure, unauthenticated access, and unauthorised access. Run
`php artisan test` and `vendor/bin/pint --test` before committing.

**R10 — PHI never leaves the box.** No real patient data in dev or tests. Never log names, MRNs,
DOB, or free-text notes. Never commit `.env`.

**R11 — Ask instead of guessing.** Dosage, units, flags, ranges and "signed means X" are
patient-safety decisions. If the reference (legacy PHP app or its `database.sql`) is ambiguous
or contradictory, stop and ask.

**R12 — Small conventional commits.** `feat(patients): add document upload`,
`fix(billing): reject overpayment`, one module or concern per commit.

## 4. Adding a module

1. Migration(s) + model(s) with relations, casts, and any enums.
2. FormRequest(s) for input; Resource(s) for output.
3. Service with the rules and one method per use case, transactional where needed.
4. Permission slugs in `RbacSeeder` (and a migration or re-seed for existing databases).
5. Routes in `routes/api.php` behind `auth:sanctum` + `permission:`.
6. Feature tests: happy path, validation, 401, 403.
7. `README.md` endpoint table updated.

## 5. Commands

```sh
php artisan serve
php artisan migrate --seed
php artisan test
vendor/bin/pint            # fix
vendor/bin/pint --test     # check
```

## 6. Progress Log

| Date | Area | Status | Notes |
|---|---|---|---|
| 2026-11-21 | Repository split | DONE | Extracted from the legacy monorepo into its own repo; Laravel's frontend scaffold removed; CORS made configurable; project docs written |
