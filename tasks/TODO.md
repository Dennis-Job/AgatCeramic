# TODO

Здесь находится только актуальный roadmap. Текущая задача — в [`IN_PROGRESS.md`](IN_PROGRESS.md),
завершённые результаты — в [`DONE.md`](DONE.md); подробная история воспроизводима из Git, ADR,
canonical documentation и audit reports.

## Исправления и рефакторинг по аудиту проекта — 2026-10-03

TASK-A066–TASK-A081 внесены 2026-10-04 по проверке версии `39d5cb2`. При составлении списка на
`7b0a020` повторно сверены места двух дефектов P1. Результаты аудита
описывают проверенную версию, а время запросов, нагрузка и реальные конкурентные PostgreSQL-сценарии
требуют отдельного измерения. Все открытые задачи ниже ожидают реализации. TASK-A082–TASK-A086
добавлены 2026-10-04 после дополнительного просмотра кода на `9140b92`; для TASK-A086 воспроизведено
чередование ответов на действующем composable с mock API.

Приоритеты: **P1** — корректность данных и рабочих сценариев, исправить в первую очередь; **P2** —
сопровождаемость, производительность и автоматические проверки; **P3** — воспроизводимость
инструментов и актуальность документации. Рекомендуемый порядок: P1 → Client CI → редактор товаров и
HTTP-клиент → остальные P2 → P3.

Общие критерии готовности: одна задача меняет один логический сценарий; сохраняет permissions,
PII-защиту и действующее поведение; проходит релевантные tests, lint/static analysis/build.
DB-зависимые изменения проверяются на изолированной PostgreSQL; API/schema changes отражаются в
OpenAPI и канонической документации. Изменения интерфейса проходят независимый UI Design Guard по
[UI_DESIGN_REVIEW.md](../docs/UI_DESIGN_REVIEW.md). После реализации обновляются статус задачи и
индекс результатов; проверки аудита не заменяют приёмку исправления.

- [ ] TASK-A084 **P2** Убрать дублирование обхода дерева категорий в Admin
  - Цель: рекурсивный обход не повторяется в
    [useCategoriesWorkspace](../frontend/admin/src/features/categories/composables/useCategoriesWorkspace.ts),
    [useProductCatalogOptions](../frontend/admin/src/features/products/composables/useProductCatalogOptions.ts) и
    [useProductImport](../frontend/admin/src/features/products/composables/useProductImport.ts).
  - План: выделить чистые типизированные операции обхода и получения потомков в categories feature;
    правила сортировки, доступности и подписи options оставить явными у потребителей. Editor
    по-прежнему скрывает неактивные категории, import показывает их с отметкой, parent selector
    исключает саму категорию и её потомков. Не вводить универсальный tree framework.
  - Готовность: unit tests проверяют пустое/вложенное дерево, отсутствие мутаций, порядок siblings,
    разные правила hidden categories и исключение потомков; catalog/import E2E сохраняют выбор.

- [ ] TASK-A085 **P2** Отделить retention-выборки и унифицировать проверку legal hold
  - Цель: [OrderRetentionService](../backend/app/Services/Retention/OrderRetentionService.php)
    отделяет eligibility queries от batch orchestration; одинаковый predicate active legal hold не
    дублируется в нём, ContactRequestRetentionService и TombstoneReplayService.
  - План: перенести сложные eligibility queries в типизированные Query-компоненты; выделить общую
    проверку hold для orders/contacts. Сохранить специализированные destruction services и
    транзакцию у use case; не создавать универсальный retention engine или новые правила сроков.
    Устранить дублирование unheld/heldCount/hasActiveHold без изменения смысла batch counters.
  - Готовность: PostgreSQL retention/replay tests проверяют cutoff и terminal anchors, holds,
    bounded batches/SKIP LOCKED, повторную проверку внутри транзакции, rollback и идемпотентность.
    Dry-run counts, tombstones, evidence и запрет apply без утверждённой policy сохраняются;
    действующая [retention matrix](../docs/PERSONAL_DATA_LIFECYCLE.md) не меняется.

## Phase 8 — SEO

- [ ] TASK-100 SEO metadata
  - Создать отдельный управляемый SEO-слой для товаров, категорий и брендов; не дублировать значения
    в таблицах Catalog.
- [ ] TASK-101 Canonical для индексируемых сущностей
- [ ] TASK-102 Генерация sitemap
  - Sitemap для страниц отдельных категорий остаётся в этой задаче.
    Реализованный охват перечислен в [CURRENT_STATE.md](../docs/CURRENT_STATE.md#реализовано).
- [ ] TASK-103 Robots metadata/directives и robots.txt
  - Для контентных страниц реализованы опубликованный `is_indexable`, SSR
    `noindex,follow`, динамический robots.txt и исключение из sitemap. Остальные
    entity-level директивы каталога остаются в этой задаче.
- [ ] TASK-104 Redirects, включая изменения Catalog slug
- [ ] TASK-105 Структурированные данные
- [ ] TASK-106 Черновики SEO-контента с AI

## Phase 9 — Analytics

- [ ] TASK-110 Dashboard заказов
- [ ] TASK-111 Dashboard оплаченных продаж
- [ ] TASK-112 Ежемесячные отчёты
- [ ] TASK-113 Продажи по категориям, брендам и товарам
- [ ] TASK-114 Средний чек

## Phase 10 — Client

- [ ] TASK-120 Catalog pages
  - [x] 2026-11-17: алфавитное меню выбранных брендов под категориями;
    `0–9`, латинские буквы и `РУС` только при наличии брендов, широкая панель
    на четыре колонки, переключатель в Admin. UI Design Guard PASS;
    по уточнению владельца переключатель доступен также прямо в строке бренда;
    [результаты и проверки](../docs/CATALOG_BRAND_MENU_2026-11-17.md).
  - [x] 2026-11-12: самостоятельный `/catalog`, независимый от CMS-блока;
    категории, бренд/коллекция, цена, наличие, сортировка и все типы характеристик.
    Характеристики только с `is_filterable` и явным назначением выбранной категории.
    UI Design Guard PASS; [результаты и проверки](../docs/CATALOG_2026-11-12.md).
  - [ ] Отдельный будущий подпункт: редактируемые параметры представления фильтров
    в «Контент → Общее оформление → Вид фильтров», без хранения товарных данных.
    В согласованную реализацию каталога эти новые настройки не входили;
    текущая панель объясняет источники данных и порядок категорий.
- [ ] TASK-121 Category pages
- [x] TASK-122 Product pages — 2026-10-29, реальные SSR-карточки; отчёт в DONE.
- [x] TASK-123 Cart — 2026-10-29, серверная гостевая корзина с целыми количествами.
  - Количество всегда целое; поддержка дробных количеств исключена из дальнейшей
    разработки по решению владельца от 2026-10-30.
- [x] TASK-124 Checkout — 2026-11-03, гостевое оформление из корзины и безопасный повтор отправки.
- [x] TASK-125 Order confirmation — 2026-11-03, номер и состав заказа из ответа API.
- [ ] TASK-126 SEO implementation
- [ ] TASK-127 Структурированные данные
- [ ] TASK-128 Оптимизация производительности

## Phase 11 — Production

- [ ] TASK-140 Production Docker
- [ ] TASK-141 CI/CD
- [ ] TASK-142 Резервное копирование
- [ ] TASK-143 Мониторинг
- [ ] TASK-144 Security hardening
- [ ] TASK-145 Production deployment
  - До включения checkout/обращений заполнить реквизиты оператора и operational approval block
    ADR-014, опубликовать актуальные юридические документы и подтвердить processors/provider
    retention, backup/KMS, primary storage location и records schedule.
  - Только после pre-production проверки задавать `PII_RETENTION_POLICY_STATUS=accepted`; apply
    дополнительно требует `PII_RETENTION_APPLY_ENABLED=true`, выбранный disposition и versioned key.
