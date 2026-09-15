# TODO

Здесь находится только актуальный roadmap. Текущая задача — в `IN_PROGRESS.md`,
завершённые результаты — в `DONE.md`; подробная история воспроизводима из Git, ADR,
canonical documentation и audit reports.

## Interim Audit Phases 0–6

Блок завершён; по результатам `TASK-A020`–`TASK-A022` переход к следующему prerequisite был разрешён. Refactoring не меняет подтверждённый
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

- [x] TASK-A020 Провести финальную регрессию Interim Audit и разрешить переход к Phase 7
  - Повторить clean-environment backend/Admin/Compose, PostgreSQL concurrency, Redis/queue,
    import/export, OpenAPI и key end-to-end scenarios.
  - Подтвердить отсутствие undocumented gaps, high/critical findings, stale links и temporary files.
  - Заблокирована результатами `TASK-A021` и `TASK-A022`; evidence и условия повторной приёмки —
    в [`docs/INTERIM_AUDIT_FINAL.md`](../docs/INTERIM_AUDIT_FINAL.md).

- [x] TASK-A021 Стабилизировать проверку каталога ответственных обращений
  - Устранить недетерминированное ожидание порядка в `ContactAssignmentTest`: проверять состав,
    eligibility и контракт сортировки отдельно, не меняя поведение API без обновления OpenAPI.
  - Повторить полный backend suite дважды.

- [x] TASK-A022 Обеспечить запуск Admin Playwright E2E в локальном Compose
  - Добавить в образ/команду необходимые зависимости Chromium либо выделить поддерживаемый
    тестовый runner; production/dev Admin workflow не должен терять работоспособность.
  - Подтвердить clean-install, полный E2E и axe scan в Compose без environment failures.

## Admin frontend refactoring — prerequisite for Phase 7

Блок повторно открыт после независимого аудита 2026-09-12 и завершён повторной приёмкой
2026-09-13. `TASK-A031`–`TASK-A035` и `TASK-A030` закрыли выявленные findings; переход к Phase 7
разрешён. Admin приведён к архитектуре из `frontend/admin/AGENTS.md` без изменения подтверждённого
поведения API, permissions и пользовательских сценариев.

- [x] TASK-A023 Зафиксировать baseline Admin и правила миграции
  - Зафиксировать скриншоты и smoke/axe-проверки `/login`, `/forgot-password`, `/reset-password`,
    `/`, `/profile`, `/products`, `/categories`, `/brands`, `/attribute-groups`, `/attributes`,
    `/employees`, `/roles`, `/permissions`, `/audit-log`, `/orders`, `/contacts`, `/content` и
    `/settings`; включить loading, empty, error, forbidden и destructive-dialog состояния там,
    где они доступны.
  - Создать карту каждого текущего `view`, компонента, composable, store и service: назначение,
    владельца feature и целевой слой. Отдельно зафиксировать сохранённые публичные props/events
    Base-компонентов для безопасной постепенной миграции.
  - Не перемещать код в этой задаче; результат — воспроизводимый baseline, который не позволит
    принять визуальный или accessibility-regression за «чистый рефакторинг».

- [x] TASK-A024 Сформировать стабильный UI-kit и design tokens
  - Ввести явные слои `styles/`, `components/ui/` и `components/shared/`, сохранив переходные
    adapters для существующих `Base*` компонентов до перевода всех потребителей.
  - Централизовать tokens цветов, типографики, spacing, radius, control heights, borders, shadows,
    transitions и focus ring; удалить зависимость глобального поведения кнопок от селекторов
    `class*` в `style.css`.
  - Реализовать и задокументировать варианты/состояния `UiButton`, `UiInput`, `UiSelect`,
    `UiTextarea`, `UiCheckbox`, `UiRadio`, `UiDialog`, `UiAlert`, `UiBadge`, `UiCard`, `UiTable`,
    `UiField`, `UiLoadingState`, `UiEmptyState`, `UiPagination` и `ConfirmDialog`.

- [x] TASK-A025 Выделить application shell и auth shell
  - Разделить текущий `AppLayout` на `AdminLayout` и компоненты header/sidebar; создать
    `AuthLayout` для login/forgot-password/reset-password без копирования каркаса и стилей.
  - Вынести общие page header, уведомления, user menu и responsive-navigation в shared/layout
    слой; сохранить route guards, focus management и keyboard-навигацию.
  - Проверить sidebar, header, диалоги и таблицы на 320/640/768/1024/1280 px, включая отсутствие
    непреднамеренного горизонтального overflow.

- [x] TASK-A026 Нормализовать общие UI-паттерны страниц
  - Заменить локальные копии заголовков, action bars, filters, alerts, status badges, table shells,
    loading/empty/error blocks, пагинации и подтверждений удаления на shared/UI-компоненты.
  - Единообразно реализовать label/help/error у полей, `role=status`/`role=alert`, visible focus,
    disabled/loading/success/empty состояния и длинный русский контент.
  - Не переносить доменные правила в UI-kit и не создавать одноразовые wrapper-компоненты.

- [x] TASK-A027 Рефакторинг feature `products`
  - Превратить `ProductsView.vue` в тонкую route-level композицию; выделить
    `features/products/{components,composables,services,types,validation}`.
  - Разделить редактор на `ProductEditor`, `ProductMainSection`, `ProductAttributesSection`,
    `ProductImagesSection`, `ProductVariantsSection`, `ProductRelationsSection` и
    `ProductReviewSection`; состояние, submit и validation перенести в composables/schema.
  - Устранить горизонтальную прокрутку/обрезание stepper в редакторе на 320 px, сохранив
    доступность шагов, текущий порядок операций, импорт/экспорт и API-contract.

- [x] TASK-A028 Рефакторинг feature `catalog`
  - Последовательно выделить feature-модули categories, brands, attribute-groups и attributes;
    разложить крупные формы и dialogs на логические доменные секции.
  - Вынести DTO/types и validation из Vue-файлов; сохранить services как единственный путь к API,
    не создавая raw HTTP в компонентах.
  - Привести list/detail/form состояния и responsive-представление таблиц к общему паттерну.

- [x] TASK-A029 Рефакторинг feature `access`, `sales` и `contacts`
  - Перевести profile, employees, roles, permissions, audit-log, orders и contacts на тонкие pages
    и feature-level компоненты/composables; Pinia оставить только для действительно глобального
    состояния.
  - Убрать дублирование фильтров, форм, статусов и destructive flows, не изменяя permissions,
    данные заказов, ПДн и audit semantics.
  - Для узких list/detail экранов определить явную responsive-стратегию вместо случайного
    горизонтального скролла.

- [x] TASK-A031 Исправить mobile sidebar и контроль ошибок рендера
  - Исключить закрытый mobile sidebar из accessibility tree и Tab-порядка вне desktop; сохранить
    открытие, focus trap, Escape, backdrop, возврат фокуса и desktop-навигацию.
  - Восстановить видимую и доступную иконку удаления; добавить проверки unresolved Vue components,
    browser console errors и `pageerror`, чтобы такие дефекты не проходили E2E незамеченными.
  - Проверить sidebar на 320/640/768/1024/1280 px, выполнить build, unit, релевантные E2E/axe,
    responsive visual QA и независимый UI Design Guard review.

- [x] TASK-A032 Довести design tokens и цветовой контраст до принятого стандарта
  - Исправить все выявленные WCAG AA contrast failures, включая secondary/muted text, links,
    controls и состояния disabled/hover/focus, без ухудшения визуальной иерархии.
  - Сделать `tokens.css` фактическим source of truth для цветов, spacing, radius, control heights,
    borders, shadows, transitions и focus ring; `@theme` должен ссылаться на tokens, а не дублировать
    необоснованные raw values.
  - Удалить необоснованные исключения `color-contrast` из full-page Axe scan; выполнить visual QA
    обязательных маршрутов на всех контрольных ширинах и независимый UI Design Guard review.

- [x] TASK-A033 Завершить products migration на UI-kit и feature-owned types
  - Заменить повторяющиеся raw buttons, page headers, alerts, badges, table/empty/loading states,
    fields, radios и destructive dialogs внутри `products` на UI/shared primitives; native elements
    оставить только там, где это оправдано платформой, например file input и progress.
  - Перенести `AttributeDraftValue` и остальные domain/DTO-типы из Vue/service файлов в
    `features/products/types`; composables не должны зависеть от Vue-компонентов.
  - Сохранить API-contract, permissions, импорт/экспорт и порядок операций; расширить unit/E2E/axe,
    проверить 320/640/768/1024/1280 px и пройти независимый UI Design Guard review.

- [x] TASK-A034 Устранить дублирование auth shell
  - Вынести общий auth card, branding, title, description и layout из login, forgot-password и
    reset-password в `AuthLayout` либо один shared auth-компонент без параллельных реализаций.
  - Оставить page-specific формы и workflows во views; сохранить guards, autofocus, autocomplete,
    keyboard navigation, API-contract и состояния loading/success/error.
  - Выполнить build, unit, auth E2E/axe и visual QA трёх маршрутов на всех контрольных ширинах,
    затем получить независимый UI Design Guard review.

- [x] TASK-A035 Стабилизировать acceptance suite и актуализировать evidence
  - Заменить timer-based loading fixtures на управляемые deferred fixtures и подтвердить
    стабильность повторными параллельными запусками без flaky failures.
  - Добавить воспроизводимое forbidden-state coverage; зафиксировать историческое отклонение
    `TASK-A023`, не переписывая Git-историю.
  - Исправить устаревшие пути/команды в `docs/UI_DESIGN_REVIEW.md`, включить `/contacts` в
    обязательный маршрутный набор и синхронизировать связанную документацию.
  - Дважды подряд выполнить полный локальный Admin suite и один раз Linux Compose suite.

- [x] TASK-A030 Повторная финальная приёмка Admin frontend refactoring
  - Предварительные исправления `TASK-A031`–`TASK-A035` завершены.
  - Удалить временные compatibility adapters только после перевода всех потребителей; проверить,
    что `views` не содержат raw HTTP, доменных DTO, крупных форм или копий UI primitives.
  - Выполнить полный build, unit, production E2E и axe suite, visual QA обязательных маршрутов и
    independent UI Design Guard review; устранить все blocking findings.
  - Обновить `frontend/admin/AGENTS.md`, `docs/UI_DESIGN_REVIEW.md` и актуальную документацию
    только при изменении фактических правил/контрактов; после этого разрешить Phase 7 — Content.

## Admin frontend post-acceptance follow-up

- [x] TASK-A036 Удалить hardcoded-роли и оставшийся compatibility adapter
  - Использовать полученные от backend названия ролей без frontend-словаря системных slug и удалить
    неиспользуемый service re-export `getRoles`; синхронизировать итоговую документацию TASK-A030.
  - Сохранить текущие permissions, маршруты и API-contract; добавить адресную unit/E2E-проверку.

- [x] TASK-A037 Завершить disabled-контракт UI-kit
  - Заблокировать вспомогательные действия `UiInput` и `UiSelect` в disabled-состоянии и добавить
    полноценный disabled-контракт для `UiDatePicker` без потери keyboard/focus semantics.
  - Обновить UI-kit tests, axe и visual QA затронутых состояний на контрольных ширинах; пройти
    независимый UI Design Guard review.

- [x] TASK-A038 Вынести orchestration импортов товаров из Vue-компонентов
  - Перенести state, загрузку, polling, download/upload flow и validation XLSX/ZIP из трёх import
    dialogs в feature composables/validation, оставив компонентам представление и события.
  - Не менять API-contract, permissions, лимиты файлов и поведение фоновой обработки; выполнить
    unit, import E2E/axe, responsive QA и независимый UI Design Guard review.

- [x] TASK-A039 Усилить Admin lint и flaky CI gates
  - Добавить воспроизводимые lint и format-check команды в локальный workflow и CI.
  - Не позволять Playwright retry скрывать flaky tests: отключить retries либо включить падение CI
    при flaky-результате; подтвердить два последовательных локальных прогона и Linux Compose suite.

- [x] TASK-A040 Дополнить живую витрину Admin UI-kit
  - Показать на `/ui-kit` все 16 `Ui*`-компонентов, shared-компоненты, значимые варианты,
    состояния и полный набор design tokens без изменения самих UI primitives.
  - Закрепить полноту inventory и tokens unit-тестами; проверить responsive, visual, axe и
    интерактивные состояния локально и в Linux Compose; пройти независимый UI Design Guard review.

- [x] TASK-A041 Восстановить desktop-прокрутку Admin sidebar
  - Сохранить `overflow-y-auto` на desktop и изолировать wheel/trackpad-прокрутку sidebar от
    основной страницы через `overscroll-contain`.
  - Закрепить на viewport 1280×480 переполнение, hover + wheel, отсутствие scroll chaining,
    видимость нижней навигации и переход по ссылке; обновить просмотренные visual baselines.

## Follow-up повторного аудита Phases 0–6 — 2026-09-14

Функциональная приёмка Phases 0–6 сохраняется, однако повторная проверка выявила один
критический security incident и несколько пробелов в lifecycle данных и production-like
проверках. `TASK-A042` блокирует публикацию новых изменений до закрытия incident. Остальные
задачи выполняются в указанном порядке зависимостей; подробное evidence находится в
[`docs/INTERIM_AUDIT_FINAL.md`](../docs/INTERIM_AUDIT_FINAL.md).

- [ ] TASK-A042 Удалить опубликованные дампы БД и закрыть incident утечки
  - Без вывода содержимого инвентаризировать четыре отслеживаемых PostgreSQL dump-файла и все их
    Git-объекты. Считать административные записи, password hashes, session payloads и audit
    snapshots скомпрометированными, пока не доказано обратное.
  - После отдельного подтверждения destructive history rewrite удалить dump blobs из текущего
    дерева, всех refs и публичной Git-истории; добавить обязательные ignore/secret-scan gates и
    хранить recovery backups только вне репозитория в шифрованном хранилище с контролем доступа.
  - Инвалидировать затронутые sessions/reset tokens, сменить пароли административных аккаунтов и
    ротировать `APP_KEY`/связанные секреты по подтверждённому содержимому дампов. Зафиксировать
    incident timeline, внешние копии/forks/caches, ответственного и результат проверки очистки.
  - Обновить безопасный restore runbook и доказать восстановление из нового backup-канала без
    помещения архива или чувствительных значений в Git, CI logs либо task evidence.

- [ ] TASK-A043 Утвердить политику жизненного цикла персональных данных
  - Совместно с ответственным за ПДн/юристом определить сроки и правовые основания хранения для
    заказов, обращений, комментариев, workflow history, administrative snapshots, sessions,
    failed jobs, логов и backups; отделить обязательное хранение от операционного удобства.
  - Зафиксировать threat model и решение по encryption at rest, key management, поиску по
    зашифрованным полям, backup-копиям, доступу, выгрузке и уничтожению данных. Не считать
    application encryption самостоятельным подтверждением соответствия 152-ФЗ.
  - Принять ADR и проверяемую retention/deletion matrix до необратимой анонимизации либо удаления.
  - Инженерная редакция, threat model и матрица подготовлены в
    [`docs/PERSONAL_DATA_LIFECYCLE.md`](../docs/PERSONAL_DATA_LIFECYCLE.md); остаются три явно
    зафиксированных согласования, после которых ADR-014 переводится из `proposed` в `accepted`.

- [ ] TASK-A044 Реализовать утверждённые retention, anonymization и deletion controls для ПДн
  - На основе `TASK-A043` реализовать отдельные application services/commands для заказов,
    обращений и связанных history/comments без обхода permissions, audit и юридически обязательных
    сроков; операции должны быть идемпотентными, bounded и безопасными для повторного запуска.
  - Добавить scheduler, dry-run/metrics, failure visibility и PostgreSQL tests; исключить PII из
    command output, logs, queue payloads и аналитических агрегатов.
  - Обновить `DATABASE.md`, `LOGGING.md`, `OPERATIONS.md` и recovery/backup правила; подтвердить
    результат на обезличенных fixtures и в restore exercise.
  - Repository-side controls и synthetic tests реализованы 2026-09-14. Production activation и
    закрытие задачи заблокированы pending approvals `TASK-A043` и внешним provider evidence.

- [x] TASK-A045 Защитить bearer-токены и ограничить срок жизни гостевых корзин
  - Хранить необратимый HMAC/hash `X-Cart-Token` вместо raw bearer-токена, сохранив текущий wire
    contract; предусмотреть безопасную migration/expiration strategy для существующих корзин.
  - Утвердить configurable TTL для пустых, брошенных и оформленных корзин и добавить bounded
    scheduled cleanup без удаления активной корзины во время конкурентного add/update/checkout.
  - Закрепить ownership, expiry, replay/concurrency, индекс и cleanup tests на SQLite и PostgreSQL;
    обновить OpenAPI, `API.md`, `DATABASE.md` и operations visibility.
  - Реализовано 2026-09-15: raw tokens мигрируются в HMAC-SHA-256, lifecycle/TTL и bounded hourly
    cleanup синхронизированы row locks; SQLite contract tests и отдельный PostgreSQL concurrency/index
    suite добавлены в CI.

- [x] TASK-A046 Усилить семантическую и compatibility-проверку OpenAPI
  - Подключить воспроизводимый OpenAPI 3.1 validator/linter, проверяющий `$ref`, schemas, formats,
    parameters, request/response media types и уникальность `operationId`, а не только JSON parse и
    совпадение route registry.
  - Заменить поверхностный compatibility check на рекурсивное сравнение request/response schemas,
    типов, required/nullable, enum, bounds, parameters, headers и status codes с корректной
    классификацией breaking/non-breaking изменений.
  - Обычный patch/minor version bump не должен автоматически разрешать удаление operation или
    несовместимый wire contract. Закрепить сам checker mutation/fixture-тестами и требовать явный
    migration plan для разрешённого breaking change.
  - Реализовано 2026-09-15: закреплённый Redocly OpenAPI 3.1 gate и project conventions проверяют
    semantics/formats/media types; направленно-рекурсивный checker и mutation fixtures покрывают
    request/response schemas, parameters, headers/status/security. Breaking разрешён только major
    bump с явным versioned migration plan.

- [x] TASK-A047 Сделать Compose bootstrap зависимостей lock-aware
  - Заменить проверки только наличия `vendor/autoload.php`, `vite` или `nuxt` на детерминированную
    сверку `composer.lock`/`package-lock.json` с содержимым named volumes для backend, queue,
    scheduler, Admin и Client.
  - Гарантировать, что `docker compose up --build` после изменения lock-файла устанавливает точный
    набор зависимостей либо завершается с понятной ошибкой, не оставляя частично рабочие сервисы.
  - Добавить clean-volume и stale-volume smoke tests, описать recovery без ручного удаления рабочих
    данных и синхронизировать `ENVIRONMENT.md`/`CI.md`.
  - Реализовано 2026-09-15: единый fail-closed bootstrap хранит атомарный fingerprint manifest,
    lock-файла и runtime в dependency volume, сериализует общий Composer install и запускает
    application process только после успешного `composer install`/`npm ci`. Lock-файлы являются
    image rebuild inputs; отдельный CI smoke проверяет clean/stale volumes и безопасный recovery.

- [ ] TASK-A048 Прогонять Laravel feature suite на PostgreSQL
  - Добавить безопасный CI job для полного либо обоснованно разделённого backend feature suite на
    отдельной PostgreSQL database; существующий SQLite suite оставить быстрым feedback, а не
    единственным доказательством большинства HTTP/business сценариев.
  - Исключить destructive доступ к development/production DB теми же fail-closed guards, что и у
    текущих integration tests; обеспечить deterministic reset и отсутствие зависимости от порядка.
  - Зафиксировать PostgreSQL-specific различия constraints, JSON, decimal, locking и transactions;
    не скрывать несовместимые тесты условными skip без адресной follow-up задачи.

- [ ] TASK-A049 Проверить реальную доставку queue jobs через Redis worker
  - В изолированном CI/Compose profile отправить representative import, storage cleanup и order
    confirmation jobs через реальную Redis queue и дождаться обработки отдельным worker process,
    не вызывая `handle()` напрямую.
  - Проверить after-commit dispatch, serialization только разрешённых identifiers, retry/backoff,
    terminal failure, `failed_jobs`, stale-dispatch recovery и отсутствие дублей/потери cleanup.
  - Использовать bounded timeouts и диагностический output без PII; сохранить быстрые fake/sync
    tests для unit/feature уровня.

- [ ] TASK-A050 Добавить минимальный full-stack smoke Admin SPA → Laravel API
  - В отдельном test profile поднять production build Admin, Laravel, PostgreSQL и Redis; создать
    изолированные fixtures и пройти реальный Sanctum CSRF/login/logout flow без `page.route()` mocks.
  - Проверить по одному representative read/mutation сценарию для Catalog, orders и contacts,
    включая permissions и стандартный error envelope. Не дублировать полный visual suite.
  - Не помещать credentials/PII fixtures в repository или logs; обеспечить cleanup и блокирующий CI
    result. UI Design Guard нужен только если исправление smoke findings изменит интерфейс.

- [ ] TASK-A051 Нормализовать task ledger и исторические audit reports
  - Выполнить обещание `TASK-A005`: оставить в `TODO.md` только незавершённые задачи, а завершённые
    `TASK-A001`–`TASK-A041` держать в компактном `DONE.md`/Git без второго полного roadmap.
  - Пометить audit evidence как историческое либо обновить изменяемые счётчики маршрутов, файлов и
    тестов; убрать противоречия между `CURRENT_STATE.md`, `INTERIM_AUDIT_FINAL.md`, специализированными
    audit reports и фактическими CI gates.
  - Сохранить русский основным языком канонической документации и добавить автоматическую проверку
    внутренних Markdown links и запрещённых tracked artifacts.

- [ ] TASK-A052 Декомпозировать импортный контур backend по ответственностям
  - Разделить чтение workbook, parsing, validation, планирование изменений, применение,
    формирование template/error report и cleanup на небольшие компоненты с явными контрактами.
  - Сохранить текущий API, checkpoint/resume, transaction и locking contract; не вводить
    repository/interface без реальной границы persistence или вариативности.
  - Удалить неиспользуемые зависимости и покрыть каждый извлечённый workflow тестами; основные
    orchestration-классы должны пройти review по эвристикам из `backend/AGENTS.md`.

- [ ] TASK-A053 Устранить скрытые зависимости и test-driven production API
  - Заменить `app()`/`resolve()` в обычном production flow на явный constructor/method injection.
  - Убрать nullable service-аргументы и container fallback, добавленные ради прямых legacy-вызовов
    Jobs; тесты перевести на реальный production entry point/container invocation.
  - Заменить глобальные `request()`/`auth()` в Controllers на типизированные зависимости. Для
    неизбежных Laravel lifecycle callbacks оставить минимальный adapter, комментарий и тест.

- [ ] TASK-A054 Вынести нетривиальные admin read queries из Controllers
  - Вынести многоусловные filters/search/sort и metadata enrichment для audit logs, orders,
    contact requests, admin users и других list endpoints в Query objects.
  - Оставить простой локальный CRUD query в Controller там, где новый слой не улучшает код.
  - Исключить N+1, проверить PostgreSQL indexes и сохранить текущие response/OpenAPI contracts;
    каждый фильтр покрыть feature-тестом.

- [ ] TASK-A055 Автоматизировать backend architecture guardrails
  - Добавить проверку направлений зависимостей между HTTP, application, data/integration и
    presentation слоями.
  - Запретить в Controllers DB transactions/mutations, service locator и глобальные request/auth
    helpers; временный allowlist допускается только с номером задачи на удаление долга.
  - Добавить отчёт по чрезмерному размеру/complexity классов и методов с review-порогами из
    `backend/AGENTS.md`, подключить guard к обязательному CI и документировать локальную команду.

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
