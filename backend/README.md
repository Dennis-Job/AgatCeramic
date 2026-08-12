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
```

PostgreSQL is the default application database. Set the `DB_*` values in `backend/.env`; when Laravel runs through Docker Compose, those values are supplied by the root `.env` and the database host is `postgres`.

The automated test suite continues to use an in-memory SQLite database for fast, isolated tests. CI additionally runs all migrations against PostgreSQL 17 to catch database-specific incompatibilities.

Redis, queues, authentication, and API versioning are introduced in their respective tasks.
