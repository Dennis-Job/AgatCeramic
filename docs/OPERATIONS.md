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
| Recovery incident and test DB isolation | [`DATABASE_RECOVERY_2026-09-03.md`](DATABASE_RECOVERY_2026-09-03.md) |

Развёртывание production, резервное копирование и monitoring намеренно не входят в текущую
эксплуатационную область и принадлежат Phase 11 (`TASK-140`–`145`).
