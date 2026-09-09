# Документация AgatCeramic

Этот файл определяет информационную архитектуру документации. Он не заменяет
предметные документы и не содержит копии их правил.

## Язык и стиль

Основной язык проектной документации — русский. Имена файлов, классов, API endpoints,
permissions, команд, технологий и устоявшиеся термины (`OpenAPI`, `queue`, `rollback`,
`CI`) не переводятся искусственно. Новый текст пишется в настоящем времени, короткими
утверждениями; исторический контекст остаётся только в ADR, audit report и recovery runbook.

Ссылки на требования, решения и код должны быть относительными Markdown-ссылками. Не
дублируйте полный контракт из OpenAPI в guide, схему данных из `DATABASE.md` в API guide
или roadmap в README.

## Канонические источники

| Тема | Канонический источник | Что не должно его дублировать |
| --- | --- | --- |
| Обзор и границы проекта | [`../README.md`](../README.md) | бизнес-правила, data schema, endpoint schemas |
| Требования | [`REQUIREMENTS.md`](REQUIREMENTS.md) | техническая реализация и текущий status |
| Архитектура | [`ARCHITECTURE.md`](ARCHITECTURE.md) | подробные таблицы БД и HTTP schemas |
| Данные и lifecycle schema | [`DATABASE.md`](DATABASE.md) | миграционный журнал и API examples |
| Машинный API contract | [`openapi.json`](openapi.json) | human-readable explanation каждого поля |
| API guide и нетривиальные правила | [`API.md`](API.md) | полные повторные schemas OpenAPI |
| Environment и эксплуатационная навигация | [`OPERATIONS.md`](OPERATIONS.md) | секреты, runtime commands и CI details в README |
| Архитектурные решения | [`DECISIONS.md`](DECISIONS.md) | повтор решений в task history |
| Будущий roadmap | [`../tasks/TODO.md`](../tasks/TODO.md) | completed-task history |
| Текущая работа | [`../tasks/IN_PROGRESS.md`](../tasks/IN_PROGRESS.md) | roadmap и завершённые результаты |
| Завершённые работы | [`../tasks/DONE.md`](../tasks/DONE.md) | requirements и canonical design rules |
| UI implementation/review standard | [`UI_DESIGN_REVIEW.md`](UI_DESIGN_REVIEW.md) | screen-specific business requirements |

`docs/audits/` содержит только evidence и verdict конкретной приёмки. До `TASK-A006`
исторические audit assets не удаляются. `DATABASE_RECOVERY_2026-09-03.md` — recovery
runbook инцидента; его актуальные постоянные правила должны ссылаться на канонические
operations documents, а не копироваться в новые документы.

## Правила изменений

1. При изменении публичного API одновременно обновляются `openapi.json` и, если правило
   нетривиально для интегратора, `API.md`.
2. При изменении схемы или data lifecycle обновляется `DATABASE.md`; migration file сам
   остаётся техническим доказательством, а не документацией решения.
3. Решение с долгосрочным компромиссом фиксируется в `DECISIONS.md` по шаблону ADR.
4. Новый operational procedure создаётся по шаблону runbook и добавляется в `OPERATIONS.md`.
5. Новая задача, audit или ADR начинается с соответствующего шаблона из `docs/templates/`.

## Шаблоны

- [`templates/TASK.md`](templates/TASK.md)
- [`templates/ADR.md`](templates/ADR.md)
- [`templates/RUNBOOK.md`](templates/RUNBOOK.md)
- [`templates/AUDIT.md`](templates/AUDIT.md)
