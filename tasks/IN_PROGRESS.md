# IN PROGRESS

- TASK-A043 — политика жизненного цикла персональных данных.
  ADR-014, threat model и проверяемая retention/deletion matrix подготовлены. До завершения остаются
  документированные approvals владельца процесса, ответственного за ПДн и юриста; до них
  production-анонимизация/удаление и перевод ADR в `accepted` запрещены.

- TASK-A044 — исполняемые controls жизненного цикла ПДн.
  Repository-side реализация, SQLite/PostgreSQL tests и synthetic restore replay готовы. Apply
  закрыт безопасным production-gate; завершение задачи ожидает approvals A043 и external evidence
  по logs, email provider, backup/KMS и records schedule.
