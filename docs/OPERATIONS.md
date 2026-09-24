# Окружение и эксплуатация

Это каноническая точка входа для запуска и эксплуатации. Подробные правила находятся
только в специализированных документах:

| Область | Источник |
| --- | --- |
| Local environment, secrets и first super admin | [`ENVIRONMENT.md`](ENVIRONMENT.md) |
| CI quality gates | [`CI.md`](CI.md) |
| Redis configuration | [`REDIS.md`](REDIS.md) |
| Queues, scheduler, import jobs и storage cleanup | [`QUEUE.md`](QUEUE.md) |
| PII-safe logging и audit retention | [`LOGGING.md`](LOGGING.md) |
| Retention/deletion matrix, legal hold и encryption boundary | [`PERSONAL_DATA_LIFECYCLE.md`](PERSONAL_DATA_LIFECYCLE.md) |
| Recovery incident and test DB isolation | [`DATABASE_RECOVERY_2026-09-03.md`](DATABASE_RECOVERY_2026-09-03.md) |
| Безопасный backup/restore channel | [`DATABASE_BACKUP_RESTORE.md`](DATABASE_BACKUP_RESTORE.md) |
| Incident опубликованных DB dumps | [`SECURITY_INCIDENT_2026-09-14.md`](SECURITY_INCIDENT_2026-09-14.md) |

Развёртывание production и monitoring намеренно не входят в текущую эксплуатационную область и
принадлежат Phase 11 (`TASK-140`–`145`). До production backup provider/KMS ещё не выбран, однако
обязательная граница хранения и безопасная процедура restore уже зафиксированы после incident
2026-09-14: database archives никогда не являются артефактами Git или CI.

## Retention operations

Scheduler ежедневно выполняет PII-safe dry-run по orders, contacts и technical storage. Apply jobs
регистрируются только если при старте процесса одновременно заданы
`PII_RETENTION_POLICY_STATUS=accepted` и `PII_RETENTION_APPLY_ENABLED=true`. Для orders дополнительно
обязателен явный `PII_RETENTION_ORDER_DISPOSITION=retain_commercial|delete_all`; `pending` блокирует
mutation. После изменения конфигурации scheduler перезапускается и его список проверяется.

Ручной preflight и один bounded batch:

```sh
php artisan retention:orders
php artisan retention:contacts
php artisan retention:technical
```

`--apply` разрешается только после заполнения operational approval block ADR-014, выбора
disposition, выдачи отдельного versioned `PII_RETENTION_TOMBSTONE_KEY_ID`/key через secret manager
и подтверждения backup/provider controls. Не копируйте key, command output или tombstone journal в
Git/CI artifacts.
Сигналы для monitoring: failed rows в `retention_executions`, рост `error_count`/`exception_count`,
stale active holds с наступившим `review_at`, oldest failed job/session и повторяющийся ненулевой
eligible count.

Техническая реализация `TASK-A044` готова и выключена безопасными defaults. `TASK-A043` принята для
реализации, но необратимая production-очистка остаётся заблокированной до pre-production approvals
и operational evidence по внешним logs, email provider, backups/KMS и records schedule.

## Guest cart lifecycle

Scheduler ежечасно запускает bounded `cart:prune`. Значения `CART_EMPTY_TTL_HOURS`,
`CART_ABANDONED_TTL_DAYS`, `CART_CHECKED_OUT_TTL_HOURS` и `CART_CLEANUP_BATCH_SIZE` должны быть
положительными; batch ограничен диапазоном 1–1000. Ручной запуск одного пакета:

```sh
php artisan cart:prune --limit=100
```

Команда выводит только количество удалённых и оставшихся eligible строк, без token/hash values.
Ненулевой остаток после нескольких hourly runs и ошибки scheduler являются monitoring signals.
`CART_TOKEN_HMAC_KEY` хранится в secret manager; при отсутствии отдельного значения используется
`APP_KEY`. Смена ключа инвалидирует все текущие корзины, поэтому выполняется как запланированная
security-операция вместе с удалением/истечением существующих строк.
