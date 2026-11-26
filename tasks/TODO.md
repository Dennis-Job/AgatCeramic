# TODO

Здесь находится только актуальный roadmap. Текущая задача — в [`IN_PROGRESS.md`](IN_PROGRESS.md),
завершённые результаты — в [`DONE.md`](DONE.md); подробная история воспроизводима из Git, ADR,
canonical documentation и audit reports.

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
  - [ ] Отдельный будущий подпункт: редактируемые параметры представления фильтров
    в «Контент → Общее оформление → Вид фильтров», без хранения товарных данных.
    В согласованную реализацию каталога эти новые настройки не входили;
    текущая панель объясняет источники данных и порядок категорий.
- [ ] TASK-121 Category pages
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
