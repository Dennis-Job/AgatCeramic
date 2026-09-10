# AgatCeramic Backend

Laravel 13 API-only application and the common business API for the public storefront and admin SPA.

## Local commands

```powershell
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Run the checks:

```powershell
composer test
vendor/bin/pint --test
composer analyse
```

PostgreSQL is the default application database. Set the `DB_*` values in `backend/.env`; when Laravel runs through Docker Compose, those values are supplied by the root `.env` and the database host is `postgres`.

The automated test suite continues to use an in-memory SQLite database for fast, isolated tests.
CI additionally runs all migrations and dedicated integration tests against PostgreSQL 17. The
PostgreSQL-only Catalog suite uses independent processes/connections to exercise real competing
transactions for image, relation, product-deletion, and category-tree invariants.

To run that destructive integration suite locally, use only the isolated `agatceramic_test`
database and explicitly acknowledge the reset:

```powershell
.\scripts\run-postgres-integration.ps1 -IUnderstandThisDestroysCiTestDatabase -Password '<test-db-password>'
```

The command refuses another database name, requires `pdo_pgsql`, clears cached configuration and
runs `migrate:fresh` only before the PostgreSQL-only tests. Never point it at the local application
database or production.

Redis is configured as the default cache store. The application API is versioned under
`/api/v1`; public and administrative route declarations live in
`routes/api/v1/public.php` and `routes/api/v1/admin.php` respectively. The version
entry point is available at `GET /api/v1`.

Queues and admin authentication are configured with Laravel Sanctum cookie sessions. The
Admin SPA first requests `GET /sanctum/csrf-cookie` with credentials, then uses the
`/api/v1/admin/auth/*` routes with the XSRF token and credentials included.
