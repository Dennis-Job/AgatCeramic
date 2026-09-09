# TASK-A013 — надёжность очередей, импортов и файлового хранилища

Проверено: 2026-09-09. Scope: Phase 0–6.

## Подтверждённые controls

- Redis queue настроена с `after_commit=true`; импорт и подтверждение заказа публикуются только после commit.
- Jobs импорта используют ограниченные попытки, backoff и 80-секундный timeout, который ниже Redis `retry_after` (90 секунд).
- Загруженные XLSX и ZIP остаются на private disk; status и error reports доступны только владельцу с соответствующим permission.
- XLSX import фиксирует checkpoint, row counters и результат одной транзакцией. ZIP image import блокирует галерею отдельного SKU, поэтому повторный job не дублирует применённые изображения.
- Удаление source workbook/ZIP и удалённых public files оформлено durable `storage_cleanup_tasks`: intent создаётся в транзакции, а физическое удаление выполняет idempotent job после commit.
- Ошибки строк сохраняются в БД и доступны как owner-only reports; terminal job failures также остаются в Laravel `failed_jobs`.

## Исправления аудита

1. `storage-cleanup:retry` теперь восстанавливает задачу в состоянии `processing`, если её последняя попытка старше 10 минут. Свежая задача не redispatch-ится, поэтому живой worker не получает дубликат.
2. Terminal completed/failed state product-image import и durable cleanup ZIP теперь фиксируются в одной транзакции под lock import record. Аналогичная атомарность применена к final failure XLSX import.

## Проверка

- `php artisan test --compact tests/Feature/StorageCleanupTest.php tests/Feature/Api/ProductImageImportTest.php tests/Feature/Api/ProductImportTest.php` — 23 passed, 153 assertions.
- `vendor/bin/pint --test app/Console/Commands/RetryStorageCleanupCommand.php app/Jobs/ProcessProductImport.php app/Jobs/ProcessProductImageImport.php app/Models/ProductImageImport.php tests/Feature/StorageCleanupTest.php` — passed.

## Оставшаяся граница

Submission import controllers по-прежнему дублируют часть lifecycle orchestration; их консолидация относится к `TASK-A009`. Она не оставляет подтверждённой потери cleanup intent или нарушения ownership в проверенном async lifecycle.
