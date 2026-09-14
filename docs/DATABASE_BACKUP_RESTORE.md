# Безопасное резервное копирование и восстановление PostgreSQL

## Граница хранения

Резервные копии, SQL exports, checksums и ключи шифрования запрещено хранить в Git, в рабочем
дереве проекта, CI artifacts и обычных application logs. Backup target должен находиться вне
репозитория в утверждённом шифрованном хранилище с отдельным контролем доступа, аудитом чтения,
retention и удалением.

Минимальные роли разделяются:

- backup writer создаёт архивы, но не читает production application secrets;
- restore operator читает конкретный архив только на время восстановления;
- ключ шифрования управляется отдельно от backup storage;
- удаление и изменение retention доступны только ответственному за recovery.

Конкретный storage provider, KMS и ответственные утверждаются до production launch. После принятия
[`PERSONAL_DATA_LIFECYCLE.md`](PERSONAL_DATA_LIFECYCLE.md) полные backups с PII используют
скользящее окно не более 30 дней; они не заменяют архив обязательных бухгалтерских документов.
Локальная папка `backups/` не является backup channel и целиком игнорируется Git.

## Создание backup

1. Создать архив PostgreSQL custom format через `pg_dump --format=custom --no-owner
   --no-privileges`. Credentials передаются из secret storage через защищённый runtime-механизм,
   а не аргументом командной строки.
2. Записать архив сразу во временное место вне repository root с правами только для оператора.
3. Зашифровать архив утверждённым KMS/storage mechanism до долговременного хранения. Ключ нельзя
   хранить рядом с архивом.
4. Рассчитать SHA-256 зашифрованного объекта и сохранить checksum как metadata того же backup
   channel, не в Git.
5. Зафиксировать технический backup ID, время, PostgreSQL version, schema/migration version,
   размер, срок хранения и результат upload без имён клиентов, email, телефонов и иных данных из БД.
6. Удалить незашифрованный временный файл recoverable-способом, предусмотренным платформой, и
   проверить отсутствие файла в рабочем дереве, CI workspace и artifacts.

Перед завершением операции из корня проекта запускается:

```sh
bash scripts/assert-no-database-artifacts.sh
```

## Восстановление

Восстановление всегда начинается в изолированной базе, а не поверх рабочей или production БД.

1. Получить минимальный временный доступ к одному encrypted backup object и ключу через разные
   контролируемые каналы.
2. Проверить checksum зашифрованного объекта до расшифрования.
3. Расшифровать архив во временную директорию вне repository root; не печатать путь, credentials
   или содержимое архива в CI/task evidence.
4. Проверить table of contents командой `pg_restore --list` и совместимость PostgreSQL version.
5. Восстановить в новую пустую БД с `--single-transaction --exit-on-error --no-owner
   --no-privileges`.
6. Применить migrations и выполнить health/API smoke checks. Проверки данных используют только
   агрегаты и технические IDs; строки с PII не выводятся.
7. До открытия бизнес-доступа получить актуальный tombstone journal из отдельного защищённого
   recovery channel. Сначала выполнить `php artisan retention:tombstones-replay /secure/journal.jsonl`,
   затем ту же команду с `--apply`. После этого выполнить dry-run/apply просроченных orders,
   contacts и technical batches. Replay bounded, учитывает legal hold и сверяет versioned HMAC до
   mutation; fingerprint mismatch блокирует транзакцию без вывода исходных значений.
8. Переключать production на восстановленную БД можно только в согласованное окно, после отдельного
   approval и с проверенным rollback plan.
9. Удалить временный расшифрованный архив и отозвать выданный доступ.

## Restore exercise

До признания backup channel рабочим оператор документирует: backup ID, время начала/окончания,
версии инструментов, checksum match, успешность restore/migrations/smoke, RPO/RTO и cleanup.
Evidence не содержит archive, secret values, SQL output или PII. Exercise повторяется после
изменения storage/KMS/restore procedure и по production schedule.

Для локальной проверки процесса используются только синтетические fixtures. Исторические дампы,
затронутые incident 2026-09-14, запрещено использовать как новый recovery source.

## Последний exercise

2026-09-14 12:15 MSK выполнена локальная проверка только на синтетической таблице. PostgreSQL 17
custom-format archive был создан во временном channel вне repository, зашифрован AES-256-CBC с
PBKDF2, а plaintext удалён до restore. SHA-256 encrypted object совпал перед расшифрованием;
`pg_restore --single-transaction --exit-on-error --no-owner --no-privileges` восстановил одну
контрольную строку в отдельную БД. Обе временные БД, plaintext, encrypted object и key material
после проверки удалены. Ни archive contents, ни secret, ни PII в evidence не выводились.

Exercise подтверждает процедуру, но не выбирает production storage/KMS и не заменяет production
restore schedule из Phase 11.

В `TASK-A044` добавлен автоматический synthetic restore test: после anonymization экспортируется
PII-free JSONL tombstone, строка заказа имитируется восстановленной из старого backup, replay снова
анонимизирует aggregate и удаляет восстановленный comment. Отдельный negative exercise доказывает,
что совпавший record ID с другим keyed fingerprint не изменяется. Production journal экспортируется
bounded командой `php artisan retention:tombstones-export`; его availability, encryption, append-only
storage и срок хранения являются ответственностью утверждённого recovery provider.
