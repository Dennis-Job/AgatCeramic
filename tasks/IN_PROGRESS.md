# IN PROGRESS

- TASK-A042 — удаление опубликованных PostgreSQL dumps и закрытие incident утечки.
  Текущее дерево и публичная история очищены, rewritten branches опубликованы, локальные
  auth/session credentials инвалидированы, prevention gates и runbooks добавлены, encrypted
  restore exercise пройден. GitHub Support request `#4756780` открыт: до закрытия остаются purge
  cached views и `refs/pull/1/head`–`refs/pull/4/head`, fresh-mirror recheck, новый пароль
  администратора и подтверждение inventory иных deployed environments/clones.

- TASK-A043 — политика жизненного цикла персональных данных.
  ADR-014, threat model и проверяемая retention/deletion matrix подготовлены. До завершения остаются
  документированные approvals владельца процесса, ответственного за ПДн и юриста; до них
  production-анонимизация/удаление и перевод ADR в `accepted` запрещены.
