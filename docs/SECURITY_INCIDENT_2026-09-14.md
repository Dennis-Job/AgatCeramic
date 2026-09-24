# Security incident: PostgreSQL dumps в публичной Git-истории

Incident ID: `AGAT-2026-09-14-DB-DUMPS`
Initial severity: Critical (precautionary classification)
Final residual severity: Low
Owner: владелец repository `Dennis-Job`
Status: **closed — residual test-data exposure accepted by owner**

Документ намеренно не содержит строк БД, password hashes, session payloads, audit snapshots,
credentials или иных чувствительных значений.

## Timeline

| Время (Europe/Moscow) | Событие |
| --- | --- |
| 2026-09-11 16:23 | Commit `d56b11c61de11954adcc4b0aadce57eea8d23805` впервые добавил четыре PostgreSQL dump blob в публичный repository. |
| 2026-09-14 | Повторный аудит классифицировал публикацию как Critical и открыл `TASK-A042`; публикация новых изменений остановлена. |
| 2026-09-14 | Подтверждены публичный GitHub repository, две публичные ветки и отсутствие видимых forks по GitHub API на момент проверки. Наличие внешних clones и caches технически исключить нельзя. |
| 2026-09-14 | Dump-файлы удалены из текущего рабочего дерева; добавлены обязательные ignore, history artifact gate, secret scan и безопасный restore runbook. |
| 2026-09-14 | В локальном затронутом окружении заменён password hash одного администратора, удалены четыре sessions, reset tokens отсутствовали, remember token сброшен и `APP_KEY` ротирован без вывода значений. |
| 2026-09-14 12:15 | Encrypted backup/restore exercise на синтетической БД подтвердил checksum и одну контрольную строку; обе временные БД, файлы и key material удалены. |
| 2026-09-14 | `git-filter-repo` 2.47.0 удалил шесть recovery paths из истории; переписанные `main` (`d9dd3a8901e8ce725712061e6d5aeaaeb3869660`) и `admin-refactor-finalization` (`fd7aae953ea7c310cbd9d075c3fc50a97fac588f`) принудительно опубликованы. First Changed Commit: `79f3d53d3ed24140f363eb52d6723c4544cd2b21`. |
| 2026-09-14 | GitHub Actions run `34847508182` для remediation commit завершился со статусом Success, включая repository-security gates. |
| 2026-09-14 | Проверка свежего remote mirror подтвердила чистые public branches/tags, но GitHub продолжил публиковать старые objects через `refs/pull/1/head`–`refs/pull/4/head`; требуется server-side purge. |
| 2026-09-14 16:30 | В GitHub Support отправлен запрос `#4756780` на очистку cached views и четырёх скрытых pull-request refs; статус запроса — Open. |
| 2026-09-24 | GitHub Support `#4756780` запросил согласие на удаление PR №1–4. Запрос закрыт без удаления; remote verification подтвердил, что четыре `refs/pull/*` остаются достижимыми. |
| 2026-09-24 | Владелец подтвердил, что опубликованные дампы содержали только тестовые данные для разработки, отказался от удаления PR и явно принял остаточный риск. `TASK-A042` закрыта; prevention gates остаются обязательными. |

## Inventory без содержимого

Все четыре blob впервые появились в указанном выше commit и достижимы из `main` и
`admin-refactor-finalization`. Размеры, hashes и object IDs нужны только для проверки purge.

| Исторический путь | Bytes | Git blob | SHA-256 |
| --- | ---: | --- | --- |
| `backups/agatceramic_20260902_174608_utf8.dump` | 110597 | `428478f841930113928d0d373ace5bdf81589a8c` | `97f683ef2fbaf369238147530d958f47ff0410bc81a5ae1f4ed91b6d9c1f2916` |
| `backups/agatceramic_before_restore_20260911.dump` | 97228 | `743bf5b472f13bcc73f7100f47ed8fca573c00a1` | `7d8542ec72a6b978b3e9c680059d9061107d5c509d8a6fe40ade8ba5d362496a` |
| `backups/agatceramic_empty_before_recovery_20260903_1540.dump` | 104807 | `0f3934bfce993bc71510cd55088d4b660f6dfb64` | `94fa886e72ed1401433b227f2bdfca9e91f962e2919112692f27b675466969af` |
| `backups/agatceramic_pre_restore_20260903.dump` | 104713 | `1b09755ce8c2a90d3977d6a0df6fa251bb056071` | `26050a795f01cd6b9a6f1a3a9f116d36828010b57c8b44b443b890292cbb7d41` |

Подтверждённый audit scope: один архив содержит одну административную запись, четыре database
sessions и 157 audit snapshots; остальные архивы содержат session rows. Первоначально эти записи
рассматривались как потенциально скомпрометированные. 2026-09-24 владелец подтвердил, что всё
содержимое относится только к тестовой базе и использовалось исключительно для разработки; риск
утечки реальных business/production данных не подтверждён.

Gitleaks 8.30.1 до rewrite просканировал 189 commits, а после rewrite — 190 commits с `--all` и
полной redaction: отдельных textual secrets в Git не найдено. Результат поддерживает итоговую
оценку вместе с подтверждением владельца о тестовом характере данных, но не отменяет запрет на
публикацию любых database exports в будущем.

## Containment и recovery checklist

- [x] Остановлена публикация новых изменений до закрытия incident.
- [x] Dump-файлы и рядом лежавший checksum удалены из текущего дерева.
- [x] Добавлены обязательные ignore и CI gates для database artifacts и secrets.
- [x] После отдельного подтверждения переписать все локальные и публичные branches/refs через
  `git-filter-repo >= 2.47 --sensitive-data-removal` и force-push rewritten refs.
- [x] Проверить, что четыре blob ID и все `backups/` paths недостижимы из текущих локальных refs,
  публичных GitHub branches и tags; fresh mirror и history artifact gate подтверждают результат.
- [x] Принять решение по GitHub-managed refs: Support `#4756780` запросил удаление PR №1–4;
  владелец отказался от удаления и 2026-09-24 принял остаточную доступность тестовых объектов в
  `refs/pull/1/head`–`refs/pull/4/head`.
- [x] В локальном затронутом окружении инвалидированы database sessions/reset tokens и remember
  tokens; password hash единственного администратора заменён случайным неизвестным значением.
- [x] Опубликованная административная запись подтверждена как development-only; локальный test
  password hash уже инвалидирован, production credential exposure не подтверждён.
- [x] Локальный `APP_KEY` ротирован без вывода значения; предыдущий ключ больше не используется.
- [x] Владелец подтвердил, что опубликованные данные происходили исключительно из development/test
  базы; влияние на production data не подтверждено.
- [x] После rewrite отправить GitHub Support First Changed Commit и affected object IDs для purge
  cached views и PR references: запрос `#4756780`, отправлен 2026-09-14 16:30 MSK.
- [x] Видимые forks отсутствуют; отдельное уведомление неизвестных clones не требуется в рамках
  принятого остаточного риска тестовых данных.
- [x] На момент inventory, после force-push и при закрытии incident видимые forks отсутствуют;
  повторная purge-проверка неприменима, поскольку PR refs сохранены по решению владельца.
- [x] Пройти encrypted backup/restore exercise на синтетических fixtures по
  [`DATABASE_BACKUP_RESTORE.md`](DATABASE_BACKUP_RESTORE.md); production storage/KMS остаётся
  отдельным Phase 11 решением.

## History rewrite procedure

Каноническая инструкция GitHub:
<https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository>.

Rewrite выполняется из свежего mirror clone после остановки pushes. Удаляются все шесть путей:
четыре dumps, исторический checksum и `backups/RESTORE.md`. Последние два сами не содержат database
rows, но зависят от скомпрометированного recovery channel и не должны оставаться в старой структуре.
Перед force-push сохраняется только шифрованная аварийная копия mirror вне repository и без общего
доступа; после подтверждения clean remote она уничтожается согласно incident policy.

## Risk acceptance и closure

Изначальный closure criterion требовал удаления объектов из всех GitHub-managed refs. GitHub
Support подтвердил, что для этого необходимо удалить PR №1–4. 2026-09-24 владелец repository:

- подтвердил, что дампы содержали исключительно тестовые development-данные;
- отказался удалять четыре исторических PR;
- принял остаточную доступность старых объектов через `refs/pull/1/head`–`refs/pull/4/head`;
- подтвердил, что это исключение не разрешает публикацию новых database exports.

Текущее дерево, public branches и tags очищены; локальные test credentials/sessions
инвалидированы; encrypted restore exercise и fully-redacted Gitleaks scan пройдены. `/backups/`,
database-export extensions, history artifact gate и Gitleaks остаются обязательными CI checks.
На дату закрытия GitHub branch protection/rulesets не настроены: CI обнаруживает нарушение после
push и не является server-side pre-receive запретом для произвольной ветки.
С учётом owner attestation и явно принятого остаточного риска incident и `TASK-A042` закрыты
2026-09-24 без удаления PR.
