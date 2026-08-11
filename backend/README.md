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

The local SQLite configuration is scaffold-only. PostgreSQL is configured in TASK-010, and Redis, queues, authentication, and API versioning are introduced in their respective tasks.
