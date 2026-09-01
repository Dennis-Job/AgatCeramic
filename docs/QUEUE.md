# Очереди и планировщик

Laravel использует Redis в качестве backend по умолчанию для очередей. Данные
очередей изолированы в базе Redis `2`; базы `0` и `1` остаются зарезервированными
соответственно для подключения Redis по умолчанию и кеша.

Сервис `queue` запускает `php artisan queue:work redis --sleep=3 --tries=3
--max-time=3600`. Задача, завершившаяся ошибкой, будет повторно обработана
воркером не более трёх раз; окончательно неуспешные задачи Laravel сохраняет в
таблице PostgreSQL `failed_jobs`. Воркер завершает работу через час, после чего
Docker перезапускает его. Это позволяет подхватывать новый код приложения во
время разработки. Задачи отправляются в очередь только после фиксации
охватывающей их транзакции БД (`after_commit=true`).

Сервис `scheduler` запускает `php artisan schedule:work`. В TASK-016 создаётся
только процесс планировщика: расписания следует регистрировать через API
планирования Laravel при реализации соответствующей бизнес-задачи. Не добавляйте
команды-заглушки в расписание.

TASK-029B registers the daily `audit:prune` command. It permanently removes audit records older
than the configured five-year retention period.

## Durable storage cleanup

Product and product-image deletion writes one `storage_cleanup_tasks` record per
file in the same database transaction that removes the catalog row. A
`DeleteStoredFile` job is dispatched only after commit, so a storage or Redis
failure cannot lose the cleanup intent or roll back an already accepted catalog
deletion. Jobs are idempotent, record attempt count, last error and completion
time, and use Laravel's normal retry/`failed_jobs` handling.

The scheduler runs `storage-cleanup:retry` every five minutes. It redispatches
eligible pending/failed records and recovers tasks left behind by queue outages.
Operators can inspect and trigger a bounded batch manually:

```powershell
docker compose exec backend php artisan storage-cleanup:retry --limit=100
```

The command prints the dispatched count and a status summary. Persistent
`pending` or `failed` rows, increasing `attempts`, and populated `last_error`
values are the operational signal to investigate the configured disk before
retrying. Completed rows are retained as cleanup history.

## Product imports

`POST /api/v1/admin/products/import` stores the uploaded XLSX on the private `local` disk and
dispatches `ProcessProductImport` after the import-status row commits. The job is retry-safe because
the catalogue write is one database transaction; a failed attempt leaves no partial product rows.
It retries up to three times with backoff, updates `pending`/`processing`/`completed`/`failed`
status for Admin polling, and deletes the private workbook after success or final failure.

TASK-051 limits one workbook to 5000 non-empty rows and the job timeout to 80 seconds, below the
Redis `retry_after` interval. Larger/chunked generic bulk processing belongs to TASK-055.

## Локальный запуск

Запустите окружение:

```powershell
docker compose up --build
```

Для просмотра журналов долгоживущих процессов используйте
`docker compose logs queue scheduler`. Чтобы выполнить разовую локальную
команду, запустите из `backend/` `php artisan queue:work redis` или
`php artisan schedule:run`. Redis должен быть доступен, а значения в
`backend/.env` — настроены.

Не храните учётные данные production Redis в `.env.example`, файлах Compose или
документации.
