# Backend

Laravel является API-only business core для Admin и будущего Client. Канонические правила
архитектуры находятся в [`ARCHITECTURE.md`](ARCHITECTURE.md), HTTP contract — в
[`openapi.json`](openapi.json) и [`API.md`](API.md), data model — в [`DATABASE.md`](DATABASE.md),
а setup/operations — в [`OPERATIONS.md`](OPERATIONS.md).

Обязательный локальный стандарт разработки закреплён в
[`backend/AGENTS.md`](../backend/AGENTS.md). Он применяется вместе с корневым
`AGENTS.md` и определяет границы слоёв, dependency injection, читаемость, тестирование
и Definition of Done.

Этот файл сохраняется только как короткая точка входа для backend-разработки и не дублирует
перечисленные источники.
