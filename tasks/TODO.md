# TODO

Здесь находится только актуальный roadmap. Текущая задача — в `IN_PROGRESS.md`,
завершённые результаты — в `DONE.md`; подробная история воспроизводима из Git, ADR,
canonical documentation и audit reports.

## Interim Audit Phases 0–6

Переход к Phase 7 запрещён до завершения этого блока. Refactoring не меняет подтверждённый
API behaviour без migration plan и синхронного обновления OpenAPI.

- [x] TASK-A001 Зафиксировать исходное состояние и карту реализации Phases 0–6
  - Сопоставить каждую завершённую задачу с кодом, миграциями, маршрутами, permissions, тестами,
    OpenAPI и экраном Admin/будущего Client; не считать отметку `[x]` доказательством приёмки.
  - Зафиксировать версии зависимостей, состояние БД/очередей/storage и результаты полного набора
    проверок, не изменяя рабочие данные.
  - Составить единый реестр подтверждённых функций, дефектов, технического долга и намеренно
    отложенных возможностей с владельцем в Phase 7–11.

- [x] TASK-A002 Провести повторную приёмку Phases 0–6
  - Проверить foundation, auth/RBAC/audit, Catalog, import/export, cart/orders и contacts по
    требованиям, реальному коду и сквозным сценариям.
  - Для каждого расхождения создать адресный follow-up в этом блоке или вернуть исходную задачу в
    работу; не исправлять несвязанные дефекты внутри аудита.
  - Результат аудита хранить как один компактный актуальный отчёт вместо набора повторяющихся
    отчётов по датам.

- [x] TASK-A003 Утвердить информационную архитектуру и единый стиль документации
  - Сделать русский основным языком проектной документации; имена API, кода, технологий и
    общепринятые технические термины оставлять без искусственного перевода.
  - Назначить один канонический документ для каждой темы: обзор, требования, архитектура, данные,
    API/OpenAPI, окружение/эксплуатация, решения и roadmap; остальные документы только ссылаются на
    него и не копируют содержание.
  - Зафиксировать шаблоны для задач, ADR, эксплуатационных инструкций и результатов аудита.

- [x] TASK-A004 Переписать документацию текущего состояния и roadmap
  - Кратко и однозначно описать фактически реализованное в Phases 0–6, принятые архитектурные
    решения, ограничения и эксплуатационные требования.
  - Отдельно перечислить ещё не реализованные возможности Phases 7–11 и все ранее заложенные
    зависимости: Media, SEO, Analytics, Client и Production.
  - Удалить устаревшие формулировки, смешение времён и языков, дублирование и журнальные подробности,
    не нужные для следующей задачи; проверить все внутренние ссылки.

- [x] TASK-A005 Сжать и нормализовать реестр задач
  - Оставить в `tasks/TODO.md` только актуальный roadmap и критерии будущих работ, в
    `tasks/IN_PROGRESS.md` — только текущую задачу, а в `tasks/DONE.md` — компактный индекс
    завершённого с проверяемыми итогами.
  - Перенести полезную подробную историю в один архивный формат либо удалить её, если она полностью
    воспроизводится из Git и не содержит уникальных решений/инструкций восстановления.
  - Устранить повторение одной и той же информации между `tasks/`, `docs/` и README-файлами.

- [x] TASK-A006 Очистить audit-артефакты и служебный мусор репозитория
  - Инвентаризировать `docs/audits/`, `.tmp/`, временные XLSX/ZIP/CSV, скриншоты, отчёты тестов,
    логи, дампы и локальные артефакты; до удаления разделить их на обязательные доказательства,
    recovery-материалы и воспроизводимый мусор.
  - Удалить только устаревшие и неиспользуемые файлы, перенести сохраняемое в понятные постоянные
    места и дополнить `.gitignore`, чтобы мусор не накапливался снова.
  - Проверить, что документация, тесты, restore-процедуры и CI не ссылаются на удалённые файлы.

- [x] TASK-A007 Ввести измеримый baseline качества Laravel backend
  - Проверить структуру `backend/app`, namespaces, PSR-4, типизацию, зависимости и соответствие
    Laravel conventions; зафиксировать нарушения по модулям и приоритету.
  - Добавить или настроить статический анализ (предпочтительно Larastan/PHPStan) на максимально
    строгом реально поддерживаемом уровне, сохраняя Pint, Composer validation/audit и PHPUnit.
  - Зафиксировать воспроизводимые команды локальной и CI-проверки без baseline, скрывающего новые
    ошибки.

- [x] TASK-A008 Нормализовать HTTP/API-слой Laravel
  - Сделать контроллеры тонкими, вынести validation/authorization в Form Requests и Policies,
    унифицировать API Resources, пагинацию, фильтры, сортировку, статусы и формат ошибок.
  - Проверить route model binding, именование маршрутов, вложенность ресурсов, HTTP verbs и status
    codes на соответствие Laravel, REST и CRUD.
  - Удалить мёртвые endpoints/classes и прямые Eloquent/SQL-операции из контроллеров, если они
    принадлежат query/application layer.

- [x] TASK-A009 Перестроить application/domain services по DRY и SOLID
  - Найти дублирование в Catalog/import/export, orders, contacts, auth/audit и storage cleanup;
    выделять общие компоненты только при совпадающих правилах, не создавая слои ради слоёв.
  - Разделить слишком крупные сервисы на понятные use cases, validators, queries и infrastructure
    adapters; определить явные транзакционные границы и идемпотентность.
  - Сохранить одно бизнес-ядро Laravel для Admin и Client: интерфейсы доставки не должны дублировать
    бизнес-правила или обращаться к БД в обход application layer.

- [x] TASK-A010 Проверить модели, БД и целостность данных
  - Пересмотреть Eloquent relationships/casts/scopes, mass assignment, factories/seeders, индексы,
    foreign keys, unique/check constraints, блокировки и конкурентные инварианты.
  - Проверить жизненный цикл legacy product-variant tables и placeholder media fields; удалить их
    только отдельной безопасной миграцией после подтверждённой сверки данных и требований Phase 7.
  - Для каждой корректирующей миграции предусмотреть PostgreSQL-проверку, rollback/forward policy и
    отсутствие разрушения существующих данных.

- [x] TASK-A011 Подтвердить API-only архитектуру и границы Admin/Client
  - Убедиться, что Laravel не содержит пользовательских Blade/HTML flows, а `web` middleware,
    session/cookie и Sanctum применяются только там, где требуются first-party Admin SPA.
  - Разделить публичные и административные contracts, permissions и rate limits, сохранив общие
    use cases и доменную модель.
  - Проверить, что Phase 10 сможет использовать существующие Catalog/cart/order/contact capabilities
    без копирования бизнес-логики в Nuxt.

- [x] TASK-A012 Усилить безопасность, аудит и обработку персональных данных
  - Повторно проверить auth/session/password reset, RBAC/least privilege, mass-assignment protection,
    rate limits, upload/import boundaries, queue payloads, secrets и ownership checks.
  - Проверить маскирование PII в логах, audit trail, exceptions и jobs, а также retention/deletion,
    backup и доступ к данным в пределах ранее принятых требований 152-ФЗ.
  - Закрыть найденные high/critical проблемы до функционального рефакторинга зависимых модулей.

- [x] TASK-A013 Проверить надёжность очередей, импортов и файлового хранилища
  - Свести общий lifecycle async operations: prepare, dispatch, progress, retry, terminal state,
    ownership, error report и cleanup; убрать расхождения между типами импорта там, где правила общие.
  - Проверить retry/backoff/timeout, транзакции, after-commit dispatch, идемпотентность, безопасную
    работу с ZIP/XLSX и восстановление после остановки worker.
  - Подтвердить отсутствие потерянных/осиротевших файлов и наличие операционной видимости для
    неуспешных cleanup/jobs.

- [x] TASK-A014 Привести OpenAPI и human-readable API guide к фактическому контракту
  - Автоматически сопоставить все Laravel routes Phases 0–6 с `docs/openapi.json`, включая security,
    parameters, request/response schemas, ошибки, пагинацию и примеры.
  - Сделать OpenAPI каноническим машинным контрактом, а `docs/API.md` — коротким руководством по
    сценариям и нетривиальным правилам без повторения каждой schema.
  - Добавить CI-проверки валидности, route coverage и обнаружения несовместимого contract drift.

- [x] TASK-A015 Перестроить тестовую пирамиду и CI quality gates
  - Удалить placeholder/дублирующие тесты, разделить unit/feature/integration/contract/e2e и закрыть
    негативные, permission, transaction, concurrency и PostgreSQL-specific сценарии.
  - Проверить детерминированность factories/seeders, изоляцию test DB от локальных данных и
    повторный полный прогон без зависимости от порядка тестов.
  - Сделать обязательными Pint, static analysis, Composer audit, OpenAPI checks, backend suite и
    релевантные PostgreSQL/Redis/queue проверки; устранить flaky-тесты, а не скрывать их retry.

- [x] TASK-A016 Составить и закрыть матрицу покрытия Backend API → Admin UI
  - Для каждого административного endpoint/permission указать экран, доступные действия и состояния
    loading/empty/error/forbidden; отдельно пометить backend-only функции, намеренно ожидающие Phase 7–10.
  - Проверить навигацию и route guards по фактическим permissions; менеджер не должен видеть пустую
    ссылку либо терять доступ к разрешённой операции из-за чужого permission.
  - Создать адресные follow-up задачи для каждого подтверждённого разрыва до финальной приёмки.

- [x] TASK-A017 Нормализовать Admin UI-kit перед расширением панели
  - Инвентаризировать tokens и shared-компоненты для кнопок, полей, selects, dialogs, таблиц,
    пагинации, badges, alerts, skeleton/loading/empty/error и destructive confirmations.
  - Заменить локальные копии паттернов общими доступными компонентами, документировать варианты и
    состояния, сохранив визуальную основу TailAdmin Vue и требования `docs/UI_DESIGN_REVIEW.md`.
  - Проверить keyboard/focus, screen readers, контраст и responsive 320/640/768/1024/1280 px;
    подтвердить компонентными тестами и независимым UI Design Guard review.

- [x] TASK-A018 Реализовать рабочее место менеджера по заказам
  - Добавить permission-safe order list/show API и заменить `/orders` placeholder на list/detail,
    snapshots, contacts/delivery, transitions/history, payments и comments.

- [x] TASK-A019 Реализовать рабочее место менеджера по обращениям
  - Добавить contacts navigation и list/detail для callback, email и partner requests с filters,
    assignment, statuses, history и comments under `contacts.view/manage`.

- [ ] TASK-A020 Провести финальную регрессию Interim Audit и разрешить переход к Phase 7
  - Повторить clean-environment backend/Admin/Compose, PostgreSQL concurrency, Redis/queue,
    import/export, OpenAPI и key end-to-end scenarios.
  - Подтвердить отсутствие undocumented gaps, high/critical findings, stale links и temporary files.

## Phase 7 — Content

- [ ] TASK-090 Site settings
- [ ] TASK-091 Pages
- [ ] TASK-092 Banners
- [ ] TASK-093 Sliders
- [ ] TASK-094 Stores
- [ ] TASK-095 Working hours
- [ ] TASK-096 Media library
  - Deliver category images, brand logos and documents through managed media references; reconcile
    every pre-existing non-null placeholder before adding foreign keys.

## Phase 8 — SEO

- [ ] TASK-100 SEO metadata
  - Create a separate managed SEO layer for products, categories and brands; do not duplicate values
    in Catalog tables.
- [ ] TASK-101 Canonical for indexable entities
- [ ] TASK-102 Sitemap generation
- [ ] TASK-103 Robots metadata/directives and robots.txt
- [ ] TASK-104 Redirects, including Catalog slug changes
- [ ] TASK-105 Structured data
- [ ] TASK-106 SEO AI draft generation

## Phase 9 — Analytics

- [ ] TASK-110 Orders dashboard
- [ ] TASK-111 Paid sales dashboard
- [ ] TASK-112 Monthly reports
- [ ] TASK-113 Category/brand/product sales
- [ ] TASK-114 Average order value

## Phase 10 — Client

- [ ] TASK-120 Catalog pages
- [ ] TASK-121 Category pages
- [ ] TASK-122 Product pages
- [ ] TASK-123 Cart
- [ ] TASK-124 Checkout
- [ ] TASK-125 Order confirmation
- [ ] TASK-126 SEO implementation
- [ ] TASK-127 Structured data
- [ ] TASK-128 Performance optimization

## Phase 11 — Production

- [ ] TASK-140 Production Docker
- [ ] TASK-141 CI/CD
- [ ] TASK-142 Backups
- [ ] TASK-143 Monitoring
- [ ] TASK-144 Security hardening
- [ ] TASK-145 Production deployment
