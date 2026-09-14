# Security incident: PostgreSQL dumps в публичной Git-истории

Incident ID: `AGAT-2026-09-14-DB-DUMPS`  
Severity: Critical  
Owner: владелец repository `Dennis-Job`  
Status: **open — containment and history rewrite in progress**

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
sessions и 157 audit snapshots; остальные архивы содержат session rows. Административные записи,
password hashes, session payloads и audit snapshots считаются скомпрометированными независимо от
того, был ли доказан download третьей стороной.

Gitleaks 8.30.1 просканировал 189 commits с `--all` и полной redaction: отдельных textual secrets
в Git не найдено. Этот результат не уменьшает severity binary database disclosure.

## Containment и recovery checklist

- [x] Остановлена публикация новых изменений до закрытия incident.
- [x] Dump-файлы и рядом лежавший checksum удалены из текущего дерева.
- [x] Добавлены обязательные ignore и CI gates для database artifacts и secrets.
- [ ] После отдельного подтверждения переписать все локальные и публичные branches/refs через
  `git-filter-repo >= 2.47 --sensitive-data-removal` и force-push rewritten refs.
- [ ] Проверить, что четыре blob ID и все `backups/` paths недостижимы из локальных и GitHub refs.
- [x] В локальном затронутом окружении инвалидированы database sessions/reset tokens и remember
  tokens; password hash единственного администратора заменён случайным неизвестным значением.
- [ ] Администратор должен задать уникальный новый пароль через штатный защищённый reset flow;
  старый или повторно используемый password считается скомпрометированным и в других системах.
- [x] Локальный `APP_KEY` ротирован без вывода значения; предыдущий ключ больше не используется.
- [ ] Подтвердить inventory иных deployed environments. Если они восстанавливались из этих dumps,
  повторить auth invalidation и ротировать `APP_KEY`/related secrets через их secret storage.
- [ ] После rewrite отправить GitHub Support First Changed Commit и affected object IDs для purge
  cached views и PR references: <https://support.github.com/>.
- [ ] Уведомить владельцев иных clones; старые clones удалить, а не merge/pull в clean history.
- [x] По GitHub API на момент inventory forks отсутствуют; повторить проверку после purge.
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

## Closure criteria

Incident закрывается только когда все пункты checklist подтверждены датой, оператором и
нечувствительным evidence. Проверка должна показать ноль forbidden paths/objects во всех refs,
успешный fully-redacted Gitleaks scan, завершённую credential/session rotation, обработанные
GitHub caches/clones и успешный restore из нового backup channel. До этого `TASK-A042` остаётся
в `IN_PROGRESS` и блокирует публикацию остальных изменений.
