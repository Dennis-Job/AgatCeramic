# Восстановление AgatCeramic из backup

Архив `agatceramic_20260901_123619_utf8.dump` создан PostgreSQL 17.9 в custom-формате с кодировкой UTF-8, без владельцев и ACL. Custom-формат не проходит через текстовую кодировку PowerShell и восстанавливается через `pg_restore`.

## Проверка архива

Из корня проекта:

```powershell
(Get-FileHash -Algorithm SHA256 -LiteralPath .\backups\agatceramic_20260901_123619_utf8.dump).Hash
```

Ожидаемый SHA-256:

```text
0F589CE9EEB699220F337006EA1D28B7A9317C97FECB3B274D08B3FC4F3ABCE1
```

## Быстрое восстановление

Внимание: команды ниже заменяют содержимое текущей локальной базы `agatceramic` данными из архива.

```powershell
docker cp .\backups\agatceramic_20260901_123619_utf8.dump agatceramic-postgres-1:/tmp/agatceramic_restore.dump
docker compose stop backend queue scheduler
docker compose exec -T postgres pg_restore -U agatceramic -d agatceramic --clean --if-exists --single-transaction --exit-on-error --no-owner --no-privileges /tmp/agatceramic_restore.dump
docker compose run --rm backend php artisan migrate --force
docker compose start backend queue scheduler
```

Параметры `--single-transaction` и `--exit-on-error` не позволяют оставить базу частично восстановленной при ошибке. После восстановления Laravel применит только миграции, которых ещё нет в архиве.
