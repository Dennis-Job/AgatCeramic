# TODO

Здесь находится только актуальный roadmap. Текущая задача — в [`IN_PROGRESS.md`](IN_PROGRESS.md),
завершённые результаты — в [`DONE.md`](DONE.md); подробная история воспроизводима из Git, ADR,
canonical documentation и audit reports.

## Исправления и рефакторинг по аудиту проекта — 2026-10-03

TASK-A066–TASK-A081 внесены 2026-10-04 по проверке версии `39d5cb2`. При составлении списка на
`7b0a020` повторно сверены места двух дефектов P1; они остаются открытыми. Результаты аудита
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

- [ ] TASK-A066 **P1** Проверять инварианты группы по актуальному заблокированному товару
  - Цель: исключить изменение категории/бренда товара, включённого в группу параллельным запросом.
    [ProductManagementService](../backend/app/Services/ProductManagementService.php) читает
    membership до блокировки Product; управляемое чередование операций воспроизводит группу с
    товарами разных категорий на SQLite. Реальная PostgreSQL-гонка пока не проверена.
  - План: согласовать порядок блокировок с ProductGroupManagementService; перечитать membership и
    проверить инвариант по актуальной модели внутри транзакции. API-контракт сохранить.
  - Готовность: regression tests конкурирующего создания/изменения группы и смены category/brand на
    PostgreSQL; проверены отсутствие нарушения состава и обработка конфликтов/блокировок.

- [ ] TASK-A067 **P1** Исключить устаревшие ответы в списках и деталях заказов/обращений
  - Цель: после быстрого выбора B запоздавшие ответы A не заменяют список, запись, историю,
    комментарии или draft формы. В
    [useOrdersWorkspace](../frontend/admin/src/features/orders/composables/useOrdersWorkspace.ts)
    воспроизведены откат страницы и история/комментарии другого заказа; аналогичный код найден в
    [useContactsWorkspace](../frontend/admin/src/features/contacts/composables/useContactsWorkspace.ts).
  - План: переиспользовать защиту usePaginatedCollection для списков; детали применять атомарно
    только для актуального selection/request ID; отделить server value от редактируемого draft.
  - Готовность: поведенческие unit/E2E проверяют ответы в обратном порядке, быструю смену выбора,
    отказ одного запроса деталей, смену записи во время сохранения и корректные loading/error
    states. API и permissions сохраняются.

- [ ] TASK-A068 **P2** Включить Client E2E и форматирование в обязательный CI
  - Цель: [Client job](../.github/workflows/ci.yml) проверяет поведение SSR, preview и
    accessibility, а не только зависимости, TypeScript и build; существующие тесты не остаются
    локальной проверкой.
  - План: добавить format check и production Playwright после SSR build, установить необходимые
    Chromium dependencies; использовать текущие изолированные mock API и конфигурацию runner.
  - Готовность: чистый CI run проходит SSR/content/preview/axe suite и format check; failures
    сохраняют диагностические артефакты. Не обращаться к рабочей БД и не отключать проверки доступа.

- [ ] TASK-A069 **P2** Разделить состояние и композицию рабочей области товаров
  - Цель:
    [useProductEditor](../frontend/admin/src/features/products/composables/useProductEditor.ts) не
    объединяет список, форму, характеристики, изображения, группы и связи в одном интерфейсе.
  - План: выделить самостоятельные composables по этим сценариям, начать со списка; разделить список
    и редактор в ProductEditor.vue, сузить контракты секций вместо полного ReturnType общего
    composable. Локальные формы не переносить в глобальный Pinia store.
  - Готовность: поведенческие тесты покрывают create/edit/clone/publish, изображения, группы, связи,
    фильтры и экспорт; маршруты, API, permissions и видимое поведение сохранены. Изменение способа
    загрузки групп вынесено в TASK-A081.

- [ ] TASK-A070 **P2** Выделить общий HTTP transport и единый контракт API-ошибок Admin
  - Цель: [services/auth.ts](../frontend/admin/src/services/auth.ts) содержит auth-сценарии, feature
    services переиспользуют общий transport и error decoder без копий разбора envelope.
  - План: выделить apiFetch/CSRF, ApiError со status/code/details и обработку отмены; проверить
    response.ok при получении CSRF cookie. Endpoint и доменные payload оставить в features.
    Дедупликация CSRF initialization сохраняет восстановление после истечения сессии.
  - Готовность: тесты 401/403/419/422/429, network failure, abort и CSRF failure; field validation
    details доступны формам, auth/permissions и существующие API-контракты сохраняются.

- [ ] TASK-A071 **P2** Разделить ZIP image import и сократить файловый I/O под DB-блокировкой
  - Цель: [ProductImageImportService](../backend/app/Services/ProductImageImportService.php)
    разделяет archive inspection, validation/staging и применение галереи; большой SKU не требует
    хранения всех старых изображений в памяти. Timeout/OOM пока не воспроизводились.
  - План: подготовить новые файлы до транзакции, под блокировкой перечитать данные и применить
    ссылки/checkpoint; сохранить durable cleanup, audit и retry semantics. Проверить необходимость
    backup содержимого старых файлов при использовании уникальных новых путей.
  - Готовность: fault tests file write/commit/cleanup, retry/resume и PostgreSQL checks проходят;
    измерены память и время обработки большой галереи, существующие файлы не теряются.

- [ ] TASK-A072 **P2** Ввести lazy loading route views Admin
  - Цель: [router/index.ts](../frontend/admin/src/router/index.ts) не включает все экраны в
    начальный JS asset; при аудите один chunk составлял около 600 kB, gzip 158 kB.
  - План: заменить статические импорты route components динамическими, включая UiKitView; измерить
    начальную сборку и загрузку маршрутов. Permissions и navigation guards сохранить.
  - Готовность: build и navigation E2E проходят; cold/direct navigation работает; import workspaces
    сохраняют состояние при SPA-переходах, ошибки загрузки chunk обработаны.

- [ ] TASK-A073 **P2** Унифицировать денежные вычисления корзины, checkout и платежей
  - Цель: единый decimal/minor-unit контракт вместо дублирования арифметики в OrderAmountCalculator,
    [CartItemResource](../backend/app/Http/Resources/CartItemResource.php) и payment service;
    подтверждённой потери копеек в текущем checkout нет.
  - План: выделить небольшой чистый Money/Decimal primitive, убрать вычисления из Resource,
    согласовать snapshot PHPDoc и decimal casts, исключить промежуточный float в точных расчётах.
    API сохраняет decimal strings; представление цены во frontend не меняется.
  - Готовность: cart/order totals совпадают для дробных, нулевых и предельных сумм; partial/full
    payment и refund проверены; feature tests на PostgreSQL и OpenAPI contract checks проходят.

- [ ] TASK-A074 **P2** Согласовать stock_quantity validation с диапазоном PostgreSQL
  - Цель: значения, принятые
    [StoreProductRequest](../backend/app/Http/Requests/Api/V1/Admin/StoreProductRequest.php) и
    UpdateProductRequest, сохраняются в целевой БД. Сейчас max 4294967295 не соответствует
    PostgreSQL integer, в который установленная Laravel grammar преобразует unsignedInteger.
  - План: определить допустимый бизнес-диапазон; согласовать request/import/OpenAPI и DB schema. При
    необходимости bigint подготовить безопасную миграцию и проверку существующих данных.
  - Готовность: PostgreSQL API/import tests проверяют границы и отклонение превышения через
    validation; миграция/rollback при изменении schema проверены, DATABASE/API документация
    актуальна.

- [ ] TASK-A075 **P2** Измерить и оптимизировать поиск и счётчики товаров на PostgreSQL
  - Цель: [ProductQuery](../backend/app/Queries/ProductQuery.php) имеет измеримую стоимость
    поиска/фильтров; один endpoint счётчиков сейчас выполняет четыре COUNT.
  - План: зафиксировать query count, планы и время на представительном объёме; проверить условные
    агрегаты и подходящие индексы по результатам измерений. Сохранить исключение собственного
    activity/sale-фильтра из соответствующего счётчика.
  - Готовность: до/после evidence, PostgreSQL correctness/performance checks и API tests; комбинации
    фильтров, пустые результаты и сортировка работают без изменения контракта.

- [ ] TASK-A076 **P3** Исключить временные отчёты из Admin format check
  - Цель: `npm run format:check` проверяет сопровождаемые исходники/config, не зависит от generated
    JSON/Markdown в `.tmp/`. При аудите исходники проходили, 65 временных файлов — нет.
  - План: уточнить [.prettierignore](../frontend/admin/.prettierignore) и область проверки;
    generated outputs не форматировать и не добавлять в Git.
  - Готовность: format check проходит в чистом checkout и с временными отчётами, но по-прежнему
    обнаруживает нарушение форматирования исходников и проверяемых конфигурационных файлов.

- [ ] TASK-A077 **P3** Синхронизировать каноническую архитектурную документацию
  - Цель: [ARCHITECTURE.md](../docs/ARCHITECTURE.md) отражает текущую feature-структуру и Seller UI
    вместо прежнего TailAdmin style; правила между AGENTS/docs не противоречат друг другу.
  - План: актуализировать описание слоёв и источников истины; сверить CURRENT_STATE/CI с кодом,
    оставить завершённые audit reports историческим evidence без копирования roadmap/метрик.
  - Готовность: repository hygiene/Markdown links и diff check проходят; источники по темам
    соответствуют [DOCUMENTATION.md](../docs/DOCUMENTATION.md).

- [ ] TASK-A078 **P2** Выделить общие Client mapper и media/link utilities из homePage service
  - Цель: content pages, site appearance и preview не зависят от модуля конкретной главной ради
    [общих преобразований](../frontend/client/app/services/homePage.ts).
  - План: выделить mapping блоков/chrome и безопасные URL-преобразования по ответственности;
    сохранить типизированные DTO и reuse публичного/preview renderer без новых универсальных слоёв.
  - Готовность: SSR/typecheck/build и content/preview E2E проходят; SEO, безопасные ссылки, media
    URL, no-store/noindex и отсутствие draft в SSR payload сохраняются.

- [ ] TASK-A079 **P2** Упростить применение строки category-template import
  - Цель: [CategoryProductRowImporter](../backend/app/Services/CategoryProductRowImporter.php) имеет
    ясные шаги нормализации, определения сущностей и применения данных вместо сложного apply.
  - План: использовать существующие mapper/parser/executor boundaries, выделить только
    самостоятельные операции; сохранить транзакцию и checkpoint у вызывающего сценария.
  - Готовность: category import regression tests покрывают create/update, validation errors,
    повторный запуск, разрешённые характеристики и SKU; PostgreSQL suite подтверждает инварианты.

- [ ] TASK-A080 **P2** Разделить операции SpreadsheetML в генераторе import template
  - Цель:
    [ProductImportTemplateValidationWriter](../backend/app/Services/ProductImportTemplateValidationWriter.php)
    разделяет нормализацию styles, текстовые идентификаторы и dropdown validation.
  - План: выделить осмысленные операции из apply, сохранить структуру XLSX, скрытый lookup sheet,
    порядок worksheet elements и формат article_number/barcode без потери ведущих нулей.
  - Готовность: существующие template tests и проверки SpreadsheetML/XLSX проходят; generated
    workbook открывается без восстановления файла, значения справочников работают.

- [ ] TASK-A081 **P2** Загружать группы и данные секций товара по необходимости
  - Цель: [loadDetails](../frontend/admin/src/features/products/composables/useProductEditor.ts) не
    загружает все группы с полными товарами/характеристиками при каждом открытии редактора.
  - План: измерить запросы и payload, ввести компактный permission-protected поиск/lookup групп и
    lazy section loading; выбранная группа доступна независимо от результатов поиска. Выполнять
    после выделения state boundaries в TASK-A069; API change обновляет OpenAPI.
  - Готовность: поведенческие/E2E tests открытия, смены шагов, повторного входа и load failure;
    права и выбор группы сохраняются, объём данных не растёт со всем каталогом при открытии товара.

- [ ] TASK-A082 **P2** Разделить панели и результаты массового импорта товаров
  - Цель:
    [ProductImportWorkspace.vue](../frontend/admin/src/features/products/components/ProductImportWorkspace.vue)
    композирует самостоятельные XLSX- и ZIP-сценарии вместо объединения подготовки файла, загрузки,
    прогресса и подробных результатов обоих сценариев в одном template.
  - План: выделить панели импорта товаров и изображений с узкими props/events; разделить подготовку,
    загрузку и результаты там, где есть самостоятельный контракт. Сохранить существующие
    useProductImport/useFileImport; владелец polling и state остаётся в workspace, чтобы
    переключение вкладки не сбрасывало job. Не дублировать транспорт или validation.
  - Готовность: unit/import E2E покрывают XLSX create/edit, ZIP, частичный успех, ошибки, скачивание
    отчётов и retry polling; состояние сохраняется при смене вкладок и SPA-маршрутов. Проверены
    keyboard tabs, возврат фокуса, live announcements и responsive; UI Design Guard accepted.

- [ ] TASK-A083 **P2** Разделить формы реквизитов, документов и согласований в настройках
  - Цель:
    [SettingsWorkspace.vue](../frontend/admin/src/features/settings/components/SettingsWorkspace.vue)
    и
    [useSettingsWorkspace](../frontend/admin/src/features/settings/composables/useSettingsWorkspace.ts)
    имеют явные границы трёх сценариев: реквизиты продавца, версии документов и внутренние
    согласования.
  - План: выделить соответствующие form/section components и локальное состояние каждого сценария;
    workspace координирует загрузку, общую блокировку действий и связь согласования с опубликованной
    политикой. Передавать узкие контракты; labels/options и подготовку payload хранить вне разметки.
    Сохранить текущую последовательность операций и API без добавления новых функций.
  - Готовность: поведенческие unit/E2E проверяют save, создание черновика, preview/publish,
    согласование выбранной версии, validation и ошибки; settings.approve, публичность банковских
    реквизитов и сброс только отправленной формы сохраняются; UI Design Guard accepted.

- [ ] TASK-A084 **P2** Убрать дублирование обхода дерева категорий в Admin
  - Цель: рекурсивный обход не повторяется в
    [useCategoriesWorkspace](../frontend/admin/src/features/categories/composables/useCategoriesWorkspace.ts),
    [useProductEditor](../frontend/admin/src/features/products/composables/useProductEditor.ts) и
    [useProductImport](../frontend/admin/src/features/products/composables/useProductImport.ts).
  - План: выделить чистые типизированные операции обхода и получения потомков в categories feature;
    правила сортировки, доступности и подписи options оставить явными у потребителей. Editor
    по-прежнему скрывает неактивные категории, import показывает их с отметкой, parent selector
    исключает саму категорию и её потомков. Не вводить универсальный tree framework.
  - Готовность: unit tests проверяют пустое/вложенное дерево, отсутствие мутаций, порядок siblings,
    разные правила hidden categories и исключение потомков; catalog/import E2E сохраняют выбор.
    Согласовать изменение общего участка useProductEditor с TASK-A069.

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
  - Для управляемых контентных страниц реализовано 2026-10-20: опубликованные
    snapshots, фильтр индексации, все страницы коллекции, canonical URL и lastmod.
    Sitemap товаров/категорий/брендов остаётся в этой задаче.
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
  - 2026-10-29: ссылки товаров и фильтрация category/brand/collection с сохранением
    параметров при пагинации готовы; полный интерфейс фильтров остаётся открытым.
  - Реализовать клиентские фильтры; параметры их представления разместить в «Контент» → «Общее
    оформление» → «Вид фильтров», отдельно от каталожных данных.
- [ ] TASK-121 Category pages
- [x] TASK-122 Product pages — 2026-10-29, реальные SSR-карточки; отчёт в DONE.
- [x] TASK-123 Cart — 2026-10-29, серверная гостевая корзина с целыми количествами.
  - Количество всегда целое; поддержка дробных количеств исключена из дальнейшей
    разработки по решению владельца от 2026-10-30.
- [ ] TASK-124 Checkout
- [ ] TASK-125 Order confirmation
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
