# Queues and scheduler

Laravel uses Redis as its default queue backend. Queue data is isolated in Redis
database `2`; databases `0` and `1` remain reserved for the default Redis
connection and cache respectively.

The `queue` service runs `php artisan queue:work redis --sleep=3 --tries=3
--max-time=3600`. A failed job is retried at most three times by the worker;
Laravel records permanently failed jobs in PostgreSQL's `failed_jobs` table.
The worker exits after one hour and Docker restarts it, allowing it to pick up
new application code in development. Jobs are dispatched only after their
surrounding database transaction commits (`after_commit=true`).

The `scheduler` service runs `php artisan schedule:work`. TASK-016 establishes
the scheduler process only: schedules must be registered with Laravel's
scheduling API when their owning business task is implemented. Do not add
placeholder scheduled commands.

## Local operation

Start the environment with:

```powershell
docker compose up --build
```

Inspect the long-running processes with `docker compose logs queue scheduler`.
For a one-off local command, run `php artisan queue:work redis` or `php artisan
schedule:run` from `backend/`, with Redis available and the values from
`backend/.env` configured.

Do not store production Redis credentials in `.env.example`, Compose files, or
documentation.
