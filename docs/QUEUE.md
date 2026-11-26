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

## Подтверждения заказов

Checkout атомарно записывает заказ и `order_mail_deliveries`: `admin` всегда,
`customer` для нового заказа с обязательным email. Исторические заказы без
email по-прежнему поддерживаются. После commit отправляются независимые
`SendOrderMail(deliveryId)`; payload содержит только ID. Магазин получает письмо
на актуальный `site_settings.notification_email`, клиент — на email заказа.
Подтверждение содержит все введённые данные, snapshots позиций и публичные
контакты сайта; Reply-To использует публичную почту, без служебного адреса.

Сбой Redis не отменяет заказ: durable pending-записи восстанавливает scheduler
через `orders:retry-mail` каждые пять минут (до 100 записей за запуск, минимум
пять минут между dispatch). У каждой доставки свои статус и лимит 9 попыток;
jobs используют 3 попытки и backoff 30/120 секунд. `orders:retry-mail
--retry-exhausted` явно сбрасывает исчерпанные pending-доставки после исправления
SMTP. Пустая/некорректная почта магазина оставляет admin pending без расхода
попыток, не мешая клиенту. Sent-записи пропускаются; обезличенные заказы — skipped.

Shared `MailTransportSafety` блокирует log и вложенные цепочки с log, как для
обращений. Исключения SMTP заменяются общим текстом без previous exception,
dispatch-ошибки журналируются только с delivery ID. Старый `SendOrderConfirmation`
с orderId совместим с уже поставленными задачами и обрабатывает только customer.
Миграция не рассылает старые заказы. Row locks предотвращают обычные дубли;
окно между приёмом SMTP и фиксацией sent_at не даёт гарантии exactly-once при
аварии процесса. Резервные копии восстанавливаются по существующему порядку.

`failed_jobs` не является долговременным журналом. Lifecycle policy ограничивает хранение
payload/exception 30 днями от `failed_at`; jobs обязаны сериализовать только технические ID, а
exception перед сохранением не должна содержать PII. `retention:technical` dry-run показывает
только count/cutoff, а gated `--apply` удаляет один bounded batch вместе с expired database sessions
и password reset tokens. Пока не завершён operational approval gate ADR-014, scheduler
регистрирует только dry-run.

Сервис `scheduler` запускает `php artisan schedule:work`. В TASK-016 создаётся
только процесс планировщика: расписания следует регистрировать через API
планирования Laravel при реализации соответствующей бизнес-задачи. Не добавляйте
команды-заглушки в расписание.

TASK-029B registers the daily `audit:prune` command. It permanently removes audit records older
than the configured five-year retention period. TASK-A044 schedules retention dry-runs at
01:10/01:20/01:30 and, only after the dual production gate is active, apply batches at
02:10/02:20/02:30. Every command uses `withoutOverlapping` and records append-only metrics/failures.

Guest carts have an independent technical lifecycle. The scheduler runs `cart:prune` hourly with
`withoutOverlapping`; each run locks and deletes at most the configured batch of expired rows and
reports aggregate deleted/remaining counts only. Cart writes and checkout lock the same cart row;
PostgreSQL cleanup uses `SKIP LOCKED`, leaves in-flight carts for a later run and rechecks
`expires_at` before deletion.

## Письма по заявкам сайта

`POST /api/v1/callback-requests` в одной транзакции создаёт обращение и
`callback_mail_deliveries` только для администратора. Email клиента не собирается,
письмо клиенту не отправляется; прежние callback customer-задачи безопасно пропускаются.
`POST /api/v1/partner-requests` и `POST /api/v1/email-requests` в той же схеме
создают две доставки `admin` и `customer`. Каждая имеет собственный статус/лимит
попыток: сбой одной не повторяет уже отправленную другую. Название таблицы/job
сохранено для совместимости; новых миграций для расширения типов нет.
После commit dispatch выполняется безопасно: недоступность Redis не превращает
сохранённую заявку в ошибку формы. `SendCallbackMail` несёт только ID доставки,
получает актуальные контакты из БД, отправляет через настроенный Laravel mailer.
Получатель новых заявок берётся из актуального `site_settings.notification_email`
во время выполнения job. `ADMIN_EMAIL`, клиентский и партнёрский адреса не
используются как fallback; изменение настройки применяется и к ещё не отправленным
задачам без перезапуска worker. `ADMIN_APP_URL` задаёт ссылку на обращение в письме.

SMTP использует `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`,
`MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME`, `MAIL_SCHEME` при необходимости.
При `MAIL_MAILER=log` или цепочке failover/roundrobin с log отправка не выполняется: delivery остаётся pending без
расхода попыток и без записи персональных данных письма в application log.
Для порта 465 Laravel автоматически выбирает implicit TLS; для 587 используется
SMTP с STARTTLS согласно поддержке сервера. `MAIL_TIMEOUT=30` ограничивает операцию,
job timeout=60 ниже Redis retry_after=90. После изменения `.env` очистить config cache
и перезапустить долгоживущие `queue`/`scheduler`; Laravel API читает конфигурацию заново
на запрос, если cache отсутствует. Драйвер `log` не использовать для реальных контактов.

Отправка администратору выполняется с тремя попытками и backoff 30/120 секунд.
Row lock и `status=sent` исключают повтор уже отмеченного успеха, в том числе при
повторном dispatch. SMTP не предоставляет exactly-once: разрыв после приёма письма
сервером до сохранения `sent_at` может привести к повтору. Transport exception
заменяется безопасным сообщением без предыдущего exception; queue/failed_jobs не
сохраняют адрес, пароль или тело письма. Удалённые/обезличенные обращения пропускаются.

Scheduler каждые пять минут выполняет `callbacks:retry-mail`: до 100 pending записей,
последний dispatch не позже пяти минут назад; после девяти попыток отправки
автоматический recovery останавливается. Ручной запуск той же команды:

```powershell
docker compose exec backend php artisan callbacks:retry-mail
```

При pending сначала проверить очередь и SMTP; счётчики/статусы доступны в
`callback_mail_deliveries`. После исправления SMTP командой
`callbacks:retry-mail --retry-exhausted` можно явно сбросить лимит до 100 исчерпанных
pending-доставок. Проверка лимита есть и под row lock job; успешные доставки не
сбрасываются и повторно не отправляются.
Пустой/некорректный `notification_email` оставляет admin-доставку pending до настройки
без расходования SMTP-попыток; после настройки recovery отправляет письмо.
Исторические обращения автоматически не рассылаются.

## Durable storage cleanup

Product and product-image deletion writes one `storage_cleanup_tasks` record per
file in the same database transaction that removes the catalog row. A
`DeleteStoredFile` job is dispatched only after commit, so a storage or Redis
failure cannot lose the cleanup intent or roll back an already accepted catalog
deletion. Jobs are idempotent, record attempt count, last error and completion
time, and use Laravel's normal retry/`failed_jobs` handling.

The scheduler runs `storage-cleanup:retry` every five minutes. It redispatches
eligible pending/failed records and recovers tasks left behind by queue outages.
It also recovers a `processing` task whose last attempt is older than ten minutes;
newer processing work is left untouched to avoid duplicate live dispatch.
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
dispatches `ProcessProductImport` after the import-status row commits. Legacy/export imports
without `category_id` use one catalogue transaction; a failed attempt leaves no partial rows.
For each category-template row, the importer locks the category and resolves edit rows by SKU
within that category. It normalizes workbook fields and option labels against the category's
current attributes, then uses the shared row mapper and executor to validate and write the
product. A create also rechecks the name after writing. A failed validation rolls back product,
attribute, SKU allocation and audit changes before the import records the error.
The import service commits each successful row or recorded error together with its counters
and `last_processed_row` under a lock on the import record.
Retries skip that checkpoint, so a worker interruption cannot create duplicates or turn an
already successful row into a duplicate-name error. At most 100 rows or 35 seconds of processing
are handled per job, then the next chunk is queued. The 80-second job timeout remains below
Redis `retry_after`. Each chunk retries transient failures up to three times with backoff.

Admin polls `pending`/`processing`/`completed`/`failed`, total, processed, created and failed rows.
`completed` includes partial row failures; `failed` is a file/infrastructure error. Earlier
category-template successes stay committed even after terminal infrastructure failure. Private
source workbooks are deleted after completion/final failure. Failed row values and messages remain
in `product_import_errors` for owner-only XLSX report generation; reports themselves are temporary
downloads and do not require a retained private source file.

TASK-051 limits one workbook to 5000 non-empty rows (category templates: rows 2–5001).
Generic/export imports use the same durable queue pattern. The first job validates the whole
workbook without changing the catalogue and stores a private, normalized write plan in
`product_import_items`. If any validation error is found, all named errors are persisted and no
catalogue row is changed. Otherwise workers commit up to 100 prepared rows or 35 seconds per job,
then enqueue the next chunk. Every item and its import counters/checkpoint commit together, so an
interrupted worker resumes only pending rows and does not repeat successful creates or updates.
Concurrent catalogue changes after preflight are reported as a row error; managers should refresh
the export and retry that row. Temporary source XLSX files are removed only after the final chunk.

## Bulk product-image imports

`POST /api/v1/admin/product-image-imports` persists a manager-uploaded ZIP privately and dispatches
its own queue job after commit. The worker inspects the archive, validates each SKU folder, and
writes the validated images to new public paths before acquiring any gallery locks. Each attempt
uses its own directory and retains the filenames used for ordinal matching and audit. Files are
read individually with a size limit. The import then locks its status row and the product in that order,
rereads the checkpoint and current gallery, and commits the image references, counters, audit,
and cleanup records for replaced files together. A retry resumes at the first unfinished SKU and
cannot publish a second generation for a committed checkpoint. An invalid folder records an error
while valid folders continue.

Every new path has a durable cleanup reservation before the write. Publication cancels these
reservations in the gallery transaction. A failed attempt releases its unpublished files to the
cleanup queue. A terminated worker leaves reservations that become eligible for scheduled cleanup
ten minutes after staging begins. Job timeout is 80 seconds. The previous gallery stays readable
through a failed write or rollback. Replaced files and terminal source ZIPs use the same cleanup
queue. The cleanup job removes files individually, so a local public disk can retain empty
generation directories.
The status record and owner-only CSV error report remain available after cleanup of the source ZIP.

ZIP inspection skips Finder metadata (`__MACOSX/`, `.DS_Store`, `._*`) after checking
entry sizes, total limits, paths and symbolic links. Metadata does not create SKU groups
or affect gallery counters; an archive with only metadata is rejected as empty.

### TASK-A071 PostgreSQL measurements

The benchmark replaced one gallery of 96 PNGs, 1,229,693 bytes per image and 112.58 MiB per
generation. Values are medians of three local runs per version on PostgreSQL 17.9 and PHP 8.3.35
using the local filesystem. Processing time excludes fixture creation and execution of cleanup
jobs. The lock interval was measured from the return of the product's `SELECT … FOR UPDATE` to
the first post-commit callback. Each run verified the gallery and compared SHA-256 hashes before
cleanup. After cleanup, all new files remained readable and the replaced files were gone.

| Metric | Before | After |
| --- | ---: | ---: |
| Gallery processing | 1,101 ms | 726 ms |
| Product lock interval | 554 ms | 151 ms |
| Peak PHP memory | 149.02 MiB | 35.19 MiB |
| Storage calls during the product lock | 384 | 0 |

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
