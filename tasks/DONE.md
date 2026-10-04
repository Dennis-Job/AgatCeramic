# DONE

- [x] Голубой вариант бейджа Admin, 2026-10-03: `UiBadge` получил tone
      `additional` на основе `blue-light-50`/`blue-light-700`, добавленный в
      витрину и UI-kit; бейдж категории в таблице товаров использует новый тон.
      UI Design Guard accepted; lint/build, 91 unit, scoped format и diff-check
      passed. [Файлы и проверки](../docs/ADMIN_BADGE_ADDITIONAL_REPORT.md).

- [x] Единый размер полей Admin, 2026-10-03: стандартные поля согласованы по
      минимальной высоте 36 px и ширине колонки; поле SKU приведено к стандарту,
      длинные значения в SKU/UiSelect обрезаются внутри поля, полный текст
      доступен; длинные подписи UiRadio не выходят за границы.
      Витрина и документация UI-kit учитывают 22 компонента. UI Design Guard
      accepted. Lint/build, 90 unit, форматирование и diff-check passed; полный
      E2E 267/294, замечания по baseline/исходным состояниям и ожиданиям API фото.
      [Отчёт и ограничения](../docs/ADMIN_CONTROL_SIZING_REPORT.md).

- [x] Единый информационный footer модальных окон Admin, 2026-10-03: общая
      `UiDialogFooter` показывает пояснения синим `UiAlert` в подвале, включая
      активные вкладки редактора товара. UI Design Guard accepted; lint/build и
      форматирование затронутых Vue-файлов passed. Полный `format:check` выявил
      прежние нарушения форматирования в `frontend/admin/.tmp`.
      [Отчёт](../docs/ADMIN_DIALOG_INFORMATION_REPORT.md).

- [x] Динамические счётчики фильтров товаров и устранение 429 от 2026-10-03:
      четыре счётчика загружаются одним запросом, справочники категорий и брендов
      кешируются на странице, фильтры синхронно debounce-ятся. UI Design Guard
      accepted; Admin lint/format/build, 90 unit, 2 E2E, Pint и 5 Backend API/OpenAPI
      tests passed.
      [Отчёт](../docs/ADMIN_PRODUCT_FILTER_DYNAMIC_COUNTS_REPORT.md).

- [x] Информационный footer фото-модалки, возврат от 2026-10-03: восстановлен
      блок `UiAlert` «Загрузка, удаление и порядок фото сохраняются сразу.» рядом
      с кнопкой «Готово». UI Design Guard accepted; lint, форматирование, build
      и 8 профильных E2E passed. [Файлы и проверки](../docs/ADMIN_PRODUCT_PHOTO_MODAL_REPORT.md).

- [x] Фото товаров, вид галереи от 2026-10-03: убран информационный footer,
      фото-карточки упрощены до изображения и бейджа обложки; удаление вынесено
      в верхний правый угол фото, порядок меняется перетаскиванием или клавишами
      со сфокусированной карточки, на touch — удержанием и перетаскиванием.
      UI Design Guard accepted. Lint/source format/build, 89 unit и 8 целевых
      E2E passed. [Файлы и проверки](../docs/ADMIN_PRODUCT_PHOTO_MODAL_REPORT.md).

- [x] Общие пустые состояния Admin и фото-модалка, 2026-10-03: `UiEmptyState`
      получил голубую поверхность для всех коллекций без border;
      фото-модалка фокусирует заголовок во время загрузки, tooltip крестика не
      всплывает при открытии. Кнопка внутри использует оттенок голубого темнее
      фона. Пунктирный вариант убран; UI Design Guard accepted. После последнего
      уточнения lint, точечный format, build, 89 unit и 15 фото/tooltip E2E passed;
      feature E2E проверяет отсутствие border. Предыдущий полный E2E: 280 passed,
      14 failures в старых screenshots/scrollHeader; scrollHeader отдельно passed
      2/2. Полный прогон после последнего уточнения не запускался. Детали — в
      [отчёте](../docs/ADMIN_PRODUCT_PHOTO_MODAL_REPORT.md).

- [x] Отображение загруженных фото, 2026-10-03: восстановлена отсутствующая
      ссылка Laravel public/storage, её создание добавлено в Compose startup.
      Оба реальных PNG доступны по HTTP 200 и отображаются в Admin; после
      пересоздания backend проверка повторена. Compose config/diff-check passed.
      [Причина, файлы и проверки](../docs/ADMIN_PRODUCT_PHOTO_MODAL_REPORT.md).

- [x] Фото товаров, уточнение от 2026-10-03: область выбора/перетаскивания
      в стиле Ozon Seller с иконкой файла и параметрами; галерея 4/2/1 по ширине
      экрана. UI Design Guard accepted; lint/source format/build, 89 unit,
      58 профильных E2E passed. [Файлы и проверки](../docs/ADMIN_PRODUCT_PHOTO_MODAL_REPORT.md).

- [x] Фото товаров из таблицы Admin, 2026-10-03: плюс для отсутствующего фото,
      карандаш при hover/focus существующей миниатюры и отдельное модальное окно
      загрузки, удаления и изменения порядка. Обложка обновляется в таблице;
      UI Design Guard accepted. Lint/source format/build, 89 unit и 57 профильных
      E2E passed. [Файлы, проверки и ограничения](../docs/ADMIN_PRODUCT_PHOTO_MODAL_REPORT.md).

Компактный индекс завершённых работ. Детальные implementation logs воспроизводятся из Git;
долгосрочные решения находятся в [`docs/DECISIONS.md`](../docs/DECISIONS.md), актуальное состояние —
в [`docs/CURRENT_STATE.md`](../docs/CURRENT_STATE.md), operational/recovery rules — в
[`docs/`](../docs/). Исторические audit reports сохраняют evidence на дату проверки и не являются
источником текущих количественных метрик.

| Этап                                                      | Завершённые задачи             | Проверяемый итог                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| --------------------------------------------------------- | ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Foundation                                                | TASK-001–017                   | Монорепозиторий, Laravel API-only, Vue Admin, Nuxt Client, Docker Compose, PostgreSQL, Redis, queue, scheduler, versioned API и OpenAPI.                                                                                                                                                                                                                                                                                                                |
| Access control                                            | TASK-020–029C                  | Admin authentication, password reset, RBAC, policies, audit trail и неизменяемость журнала PostgreSQL.                                                                                                                                                                                                                                                                                                                                                  |
| Catalog                                                   | TASK-030–042Z                  | CRUD каталога, типизированные характеристики, standalone/grouped products, SKU, изображения, связи, поиск и защита API.                                                                                                                                                                                                                                                                                                                                 |
| Import/export                                             | TASK-050–058                   | XLSX/ZIP import/export, preflight, error reports, resumable queue processing, цены, статусы и групповые файлы.                                                                                                                                                                                                                                                                                                                                          |
| Cart, orders, contacts                                    | TASK-060–084                   | Гостевая корзина, защищённый checkout, заказы, email-подтверждение и публичные/административные обращения.                                                                                                                                                                                                                                                                                                                                              |
| Документация и исходный аудит                             | TASK-A001–A006                 | Зафиксированы состояние и границы Phases 0–6, канонические документы и task ledger; удалены воспроизводимые audit-артефакты.                                                                                                                                                                                                                                                                                                                            |
| Архитектура и приёмка Phases 0–6                          | TASK-A007–A022                 | Усилены backend/API/data/security/queue/OpenAPI/CI границы, реализованы Admin orders/contacts и пройдена итоговая регрессия. Историческое evidence: [`INTERIM_AUDIT_FINAL.md`](../docs/INTERIM_AUDIT_FINAL.md).                                                                                                                                                                                                                                         |
| Admin refactoring                                         | TASK-A023–A041                 | Admin переведён на feature-слои и source-of-truth UI-kit, усилены accessibility/responsive/visual/lint gates и подтверждена повторная приёмка. Исторический baseline: [`ADMIN_REFACTORING_BASELINE.md`](../docs/ADMIN_REFACTORING_BASELINE.md).                                                                                                                                                                                                         |
| Repository security incident                              | TASK-A042                      | Database dumps удалены из текущего дерева, public branches и tags; credentials/sessions локальной test-базы инвалидированы, history/secret gates и encrypted restore runbook закреплены. Остаточная доступность исключительно тестовых объектов через четыре GitHub-managed PR refs принята владельцем без удаления PR. Evidence: [`SECURITY_INCIDENT_2026-09-14.md`](../docs/SECURITY_INCIDENT_2026-09-14.md).                                         |
| Personal-data lifecycle policy                            | TASK-A043                      | ADR-014, threat model и retention/deletion matrix приняты для реализации; персоналии, юридическая проверка и provider evidence сохранены как blocking pre-production gate `TASK-090`/`TASK-145`, а safe defaults не разрешают production apply.                                                                                                                                                                                                         |
| Personal-data lifecycle controls                          | TASK-A044                      | Реализованы bounded/idempotent retention commands для orders, contacts и technical storage, legal holds/exceptions, PII-safe evidence, versioned-HMAC tombstones, scheduler и synthetic restore replay; SQLite/PostgreSQL проверки подтверждают boundary, locking, rollback и immutability. Production apply остаётся закрыт gate `TASK-090`/`TASK-145`.                                                                                                |
| Production-like quality gates                             | TASK-A045–A050                 | Защищены cart tokens и TTL; усилены OpenAPI compatibility, lock-aware dependency bootstrap, PostgreSQL feature suite, реальная Redis delivery и Admin full-stack smoke. Актуальные gates: [`CI.md`](../docs/CI.md).                                                                                                                                                                                                                                     |
| Нормализация project ledger                               | TASK-A051                      | `TODO` содержит только будущие работы, исторические audits явно отделены от текущего состояния, а CI проверяет внутренние Markdown-ссылки и запрещённые tracked artifacts.                                                                                                                                                                                                                                                                              |
| Backend import architecture                               | TASK-A052                      | Workbook I/O, parsing/validation, planning, mutation, template/error reports и cleanup разделены по ответственностям без изменения API, checkpoint/resume, transaction и locking contracts.                                                                                                                                                                                                                                                             |
| Явные backend dependencies                                | TASK-A053                      | Jobs, bootstrap, validation, console и Controllers переведены на constructor/method injection; тесты Jobs используют container invocation, а неизбежный `failed()` adapter изолирован и протестирован.                                                                                                                                                                                                                                                  |
| Backend read layer                                        | TASK-A054                      | Многоусловные admin filters/search/sort и audit metadata enrichment вынесены в Query objects; eager loading, PostgreSQL indexes и feature-покрытие фильтров закреплены без изменения API-контрактов.                                                                                                                                                                                                                                                    |
| Backend architecture guard                                | TASK-A055                      | PHP AST gate блокирует обратные зависимости слоёв, запрещённые DB/Eloquent/service-locator/helper операции в Controllers и stale allowlist; size/complexity findings публикуются для review.                                                                                                                                                                                                                                                            |
| Client homepage                                           | TASK-119 + Phase 7 integration | SSR-главная использует управляемое содержимое Laravel API: опубликованный главный слайдер, секции, изображения, общие шапку/подвал и SEO. В Admin появился раздел «Главная сайта» с прямыми ссылками на редакторы баннеров и слайдеров; локальные изображения сохранены как начальные значения. Товары и цены ожидают публичного catalog API.                                                                                                           |
| Content: site settings                                    | TASK-090                       | Управляемые реквизиты продавца и публикация банковского блока по явному флагу; версии оферты, политики ПДн и согласий; закрытый журнал ADR-014 с отдельным permission, PostgreSQL immutability и audit trail. Admin `/settings`, публичный API и OpenAPI реализованы. Фактические согласования и provider evidence остаются pre-production gate `TASK-145`.                                                                                             |
| Content: pages                                            | TASK-091                       | Admin CRUD информационных страниц с черновиком по умолчанию, публикацией, проверкой slug и аудитом; публичный API выдаёт только опубликованные страницы. Admin `/content` использует существующий UI-kit. Тело страницы — обычный текст.                                                                                                                                                                                                                |
| Content: banners                                          | TASK-092                       | Admin CRUD баннеров с черновиком по умолчанию, публикацией, проверкой HTTP(S) ссылок и аудитом; публичный API выдаёт только опубликованные баннеры. Изображение задаётся внешним URL до `TASK-096` Media Library. Admin `/content` содержит отдельный раздел баннеров.                                                                                                                                                                                  |
| Content: sliders                                          | TASK-093                       | Admin CRUD именованных подборок баннеров с порядком, публикацией и аудитом; публичный API по slug выдаёт только опубликованный слайдер и опубликованные баннеры. Admin `/content` содержит раздел слайдеров.                                                                                                                                                                                                                                            |
| Content: stores                                           | TASK-094                       | Admin CRUD магазинов с черновиком по умолчанию, адресом, телефоном и аудитом; публичный API выдаёт только опубликованные магазины. Admin `/content` содержит раздел магазинов.                                                                                                                                                                                                                                                                          |
| Content: working hours                                    | TASK-095                       | Для каждого магазина хранится семь дней недели; admin заменяет расписание атомарно с проверкой интервалов и аудитом. Публичный API отдаёт расписание вместе с опубликованным магазином.                                                                                                                                                                                                                                                                 |
| Content: media library                                    | TASK-096                       | Управляемые изображения/PDF, WebP thumbnails, audit, RBAC и безопасное удаление через durable cleanup. FK для изображений категорий/логотипов брендов, отдельные упорядоченные привязки документов и managed изображение баннера с legacy URL fallback. Admin `/media`, выбор медиа в формах и OpenAPI.                                                                                                                                                 |
| Content workspace                                         | TASK-C001                      | Согласованный UX-контракт закреплён в `docs/CONTENT_WORKSPACE_UX.md`; Admin `/content` получил полноширинную адаптивную трёхзонную оболочку, выбор страницы по URL и встроенный редактор главной. Точный просмотр черновика запланирован в `TASK-C005`; текущая зона честно сообщает об этом. Проверены Admin build, lint, 50 unit, 6 сценарных E2E и 5 визуальных baseline-сценариев на Windows/Linux; UI Design Guard блокирующих замечаний не нашёл. |
| Content blocks and public pages                           | TASK-C002                      | Типизированные упорядоченные блоки, отдельные черновик и опубликованный snapshot, явная публикация в Admin, безопасная миграция главной и OpenAPI v4.0. Nuxt `/contacts`, `/about`, `/catalog` используют реальные API при SSR, SEO и честные состояния загрузки/ошибок/пустых данных. UI Design Guard не нашёл блокирующих замечаний; evidence и список файлов — [`TASK_C002_REPORT.md`](../docs/TASK_C002_REPORT.md).                                 |
| Content block editor                                      | TASK-C003                      | Редактор типизированных блоков всех страниц и главной, порядок/включение/добавление/удаление, сохранение черновика и отдельная публикация. Контекстные общие баннеры/слайдеры, выбор и inline-загрузка медиа с `media.manage`, preview и защита несохранённых правок. UI Design Guard: блокирующих замечаний нет; проверки и файлы — [`TASK_C003_REPORT.md`](../docs/TASK_C003_REPORT.md).                                                              |
| Content appearance workspace                              | TASK-C004                      | Общие шапка/навигация/подвал вынесены из главной, получили отдельные API и публикацию; Nuxt загружает оформление независимо от страницы. Магазины объединены с «Контентом», дублирующий вход главной заменён redirect. Панель вида фильтров выделена с честным состоянием до TASK-120. UI Design Guard принял результат; проверки, ограничения и файлы — [`TASK_C004_REPORT.md`](../docs/TASK_C004_REPORT.md).                                          |
| Saved draft Nuxt preview                                  | TASK-C005                      | Настоящий Nuxt-рендер сохранённых черновиков страницы/оформления с текущей Sanctum-сессией и content.manage, no-store/noindex, повторной проверкой доступа и очисткой при уходе в фон. Режимы экрана, полноразмерный просмотр и отдельная публичная ссылка; главная использует единый блоковый редактор/SEO, legacy UI закрыт. UI Design Guard принял результат; проверки и файлы — [`TASK_C005_REPORT.md`](../docs/TASK_C005_REPORT.md).               |
| Client dependency audit                                   | TASK-A056                      | Устранено падение Client CI после `f97a4c0`: совместимые версии `brace-expansion`, `undici` и `devalue` обновлены только в lock-файле. Чистые install, audit (0 vulnerabilities), Nuxt typecheck и SSR build прошли в изолированном Linux на Node 24.19.0; evidence и команды — в `docs/CI.md`.                                                                                                                                                         |
| Seller UI-kit                                             | TASK-A057                      | Белая основа, синий акцент, прежняя типографика Nunito/Poppins, компактные controls, surfaces и единый focus ring. Первоначальная реализация прошла UI Design Guard, lint/build, 64 unit, 177 E2E и 77 строгих baseline в каждой Windows/Linux. После уточнения типографики тесты и проверки не проводились по указанию владельца. Evidence и ограничения — в [TASK_A057_REPORT.md](../docs/TASK_A057_REPORT.md).                                       |
| Seller верхняя навигация                                  | TASK-A058                      | Двухстрочная шапка, текстовые grouped dropdown без иконок, единая конфигурация permissions/active routes и компактное меню UiDialog; глобальный sidebar/290 px отступ удалены. UI Design Guard принят; lint/build, 64 unit, 196 E2E и 77 strict baseline в каждой Windows/Linux. Evidence и ограничения — в [TASK_A058_REPORT.md](../docs/TASK_A058_REPORT.md).                                                                                         |
| Seller ширина и товары                                    | TASK-A059                      | Общий AdminWorkspace с четырьмя режимами, широкая таблица с полными названиями, compact preview, реальными единицами и локальной sticky-прокруткой. UI Design Guard Windows/Linux принят; lint/build, 64 unit, 198 E2E и 77 baseline каждой OS. Evidence и ограничения — в [TASK_A059_REPORT.md](../docs/TASK_A059_REPORT.md).                                                                                                                          |
| Seller списки и list/detail                               | TASK-A060                      | Широкие таблицы каталога, доступа, аудита и медиа; общий AdminListDetail для заказов/обращений. UI Design Guard принят; lint/format/build, 64 unit, 214 Linux E2E, включая 82 baseline; responsive 320–2560 px. Evidence и ограничения — в [TASK_A060_REPORT.md](../docs/TASK_A060_REPORT.md).                                                                                                                                                          |
| Seller контент и обзорные экраны                          | TASK-A061                      | Общий AdminEditorLayout по доступной ширине, настоящий Nuxt-preview, dashboard/магазины1280 и формы960. UI Design Guard принят; lint/format/build, 64 unit, 221 Linux E2E (82 strict baseline), real Admin/Nuxt3, Client preview6+dev2. Evidence и ограничения — в [TASK_A061_REPORT.md](../docs/TASK_A061_REPORT.md).                                                                                                                                  |
| Seller финальная приёмка                                  | TASK-A062                      | Независимый UI Design Guard принял Linux/macOS; 64 unit, lint/format/build, 223 E2E каждой OS (83 baseline/UI-kit/responsive), 20 маршрутов ×8 ширин с full axe. Darwin baseline обновлён после review; UIKit landmarks и test resize race исправлены. Реальный Nuxt3, Client preview6/dev2. Windows не проверен; evidence и ограничения — в [TASK_A062_REPORT.md](../docs/TASK_A062_REPORT.md).                                                        |
| Seller единый контейнер, доработка 2026-10-02             | TASK-A057–TASK-A062            | Общий контейнер 1280 для меню и нетабличных блоков всех страниц, широкие рабочие таблицы; детали заказов/обращений ниже с focus/scroll и возвратом, content 3 зоны от 1240. UI Design Guard принял macOS/Linux/realNuxt; lint/format/build, 64 unit, 242 E2E каждой OS, 3 real-preview. 28 platform baseline приняты до обновления; Windows не проверен. [Отчёт и файлы](../docs/ADMIN_CONTAINER_REFINEMENT_REPORT.md).                                 |
| Seller шапка, доработка 2026-10-02                        | TASK-A058/A062                 | Иконки и подменю по наведению, группы264/504/744, popup320, поиск удалён, divider в контейнере; общий UiPopover с keyboard/touch/viewport. UI Design Guard принят, 112 эталонов согласованы до обновления; lint/format/build, 64 unit, 250 строгих E2E каждой macOS/Linux, 3 real-preview. [Отчёт и файлы](../docs/ADMIN_HEADER_REFINEMENT_REPORT.md).                                                                                                  |
| Seller мягкие active/focus, доработка 2026-10-02          | TASK-A057/A062                 | Общие focus tokens: край1 px и halo14%, selected radio/checkbox primary200/25, без двойного outline; keyboard/error/disabled/forced-colors сохранены. UI Design Guard принят, 16 эталонов согласованы до замены; lint/format/build, 64 unit, 255 строгих E2E каждой macOS/Linux, real Nuxt3. [Отчёт и файлы](../docs/ADMIN_SOFT_FOCUS_REFINEMENT_REPORT.md).                                                                                            |
| Seller всплывающие уведомления, доработка 2026-10-02      | TASK-A057/A062                 | Общий правый верхний стек UiNotification/Host для операционных сообщений страниц/dialogs, paused auto-dismiss, manual close, modal ownership и focus к живому source/trigger. UI Design Guard принят, 28 эталонов согласованы до замены; lint/format/build, 69 unit, 258 строгих E2E каждой macOS/Linux, real Nuxt3. [Отчёт и файлы](../docs/ADMIN_NOTIFICATION_REFINEMENT_REPORT.md).                                                                  |
| Seller таблицы без боковых отступов, доработка 2026-10-02 | TASK-A059/A060/A062            | Общий UiTable fullBleed компенсирует активный gutter layout для 12 рабочих списков; controls/pagination/details и embedded tables сохраняют контейнер. UI Design Guard принял 24 эталона до замены и реальные 602 px снимки. Lint/format/build, 69 unit и 258 строгих E2E каждой macOS/Linux, real Nuxt3 пройдены на изолированной версии. [Отчёт и файлы](../docs/ADMIN_TABLE_FULL_BLEED_REPORT.md).                                                   |

- [x] Доработка товаров от 2026-10-02: «Добавить массово товары», «Цены и статусы»
      и «Объединить товары» перенесены в меню «Товары» и отдельные страницы вместо
      модальных окон. Сохранены import contracts, permissions и состояние при
      SPA-переходах. UI Design Guard accepted; lint/format/build, 69 unit,
      полный macOS E2E 267/267; Linux imports/navigation 37/37 и product baseline 6/6.
      [Отчёт и изменённые файлы](../docs/ADMIN_PRODUCT_IMPORT_PAGES_REPORT.md).

- [x] Доработка фильтров товаров от 2026-10-02: скрыты лейблы поиска/категории/бренда;
      активность и распродажа представлены общим сегментированным переключателем
      в Seller UI. Сохранены фильтрация, сброс и доступность. UI Design Guard accepted;
      lint/format исходников/build, 69 unit и полный strict E2E 268/268 каждой macOS/Linux.
      [Отчёт и изменённые файлы](../docs/ADMIN_PRODUCT_FILTER_REFINEMENT_REPORT.md).

- [x] Продолжение фильтров товаров от 2026-10-02: скрыты заголовки сегментов,
      белая активная кнопка сброса с голубым hover, четыре бейджа количества по
      текущему поиску/категории/бренду. Независимый UI Design Guard accepted;
      lint/format исходников/build, 73 unit, полный strict E2E 270/270 на macOS и Linux.
      [Отчёт и изменённые файлы](../docs/ADMIN_PRODUCT_FILTER_COUNTS_REPORT.md).

- [x] Уточнение кнопки «Сбросить» от 2026-10-02: удалён border у surface-варианта.
      Тесты и сборка после этой правки не запускались по указанию владельца.
      [Изменённые файлы и ограничения проверки](../docs/ADMIN_PRODUCT_FILTER_COUNTS_REPORT.md).

- [x] Компактная шапка от 2026-10-02: удалена подпись текущего раздела рядом
      с «Меню», восстановлен общий отступ layout 16/24 px. Ручной просмотр текущей
      страницы и независимое ревью кода/снимка пройдены; тесты и сборка не запускались
      по указанию владельца. [Отчёт и файлы](../docs/ADMIN_COMPACT_HEADER_SPACING_REPORT.md).

- [x] Отступы фильтров товаров от 2026-10-02: заголовок/фильтры и
      фильтры/таблица приведены к одинаковым 24 px. Ручной просмотр и независимое
      ревью пройдены; тесты и сборка не запускались по указанию владельца.
      [Отчёт и файлы](../docs/ADMIN_COMPACT_HEADER_SPACING_REPORT.md).

- [x] Прокрутка товаров от 2026-10-02: единый вертикальный scroll страницы,
      заголовки закрепляются под меню после достижения его нижней границы;
      горизонтальная прокрутка и действия сохранены. UI Design Guard accepted;
      build/lint/source format, 73 unit, 4 профильных E2E passed.
      Полный macOS E2E: 266/271, пять несовпадений прежних product visual baseline.
      [Отчёт, проверки и файлы](../docs/ADMIN_PRODUCT_PAGE_SCROLL_REPORT.md).

- [x] Шапка при прокрутке от 2026-10-02: меню перемещается между логотипом
      и иконками; высота уменьшается 109 → 65 px, таблица следует новой границе,
      положение документа сохраняется. UI Design Guard accepted; build/lint/source
      format, 73 unit, 27 профильных E2E passed. Итоговый полный macOS E2E 268/273:
      только пять прежних product baseline mismatch. Исправлено прямое подключение
      navigation CSS для dev/HMR; одна строка подтверждена на открытой странице
      и повторным независимым ревью. [Отчёт и файлы](../docs/ADMIN_SCROLL_HEADER_REPORT.md).

- [x] Центрирование меню и мобильный бургер от 2026-10-02: перенесённое desktop
      меню центрируется между брендом и действиями; mobile всегда однострочный,
      бургер рядом с уведомлениями/профилем использует общий стиль иконок. Build/lint/
      source format, 73 unit passed; полный macOS E2E 268/273 с пятью прежними
      product baseline mismatch. UI Design Guard accepted, live и production
      проверены. [Результат и проверки](../docs/ADMIN_SCROLL_HEADER_REPORT.md).


- [x] Единый формат цен, 2026-10-03: все существующие суммы и денежные поля Admin/Client показывают разделители тысяч. Цена, старая цена и сумма оплаты группируются во время ввода; API сохраняет точную decimal string без пробелов. Проверено в запускаемой Docker копии и localhost:5173; 85 unit и функциональные E2E passed, независимый UI Design Guard принял правку. Ограничения полного baseline — [Файлы и проверки](../docs/PRICE_FORMAT_REPORT.md).

- [x] Кнопки управления, 2026-10-03: единый заметный hover и доступные подсказки
      во всей Admin-панели. Исправление адаптировано в запускаемую Seller-копию;
      localhost:5173 проверен, независимый UI Design Guard принял результат.
      Lint/source format/build, 89 unit, 281/286 полный macOS E2E (только пять
      прежних products baseline), 11 Linux action/baseline проверок.
      [Файлы и результаты](../docs/ADMIN_ACTION_FEEDBACK_REPORT.md).

- [x] Уточнение кнопок управления, 2026-10-03: copy остаётся внутри столбца,
      tooltip белые, все Pencil действия редактирования используют общий синий стиль.
      89 unit, 8 профильных E2E, 282/287 полный macOS (те же пять products baseline),
      15 Linux action/baseline; 7 эталонов на каждой платформе приняты независимым
      UI Design Guard. [Отчёт и файлы](../docs/ADMIN_ACTION_FEEDBACK_REPORT.md).

- [x] Категории товаров, 2026-10-03: убраны ведущие тире у дочерних категорий в
      фильтре/форме товара и выборе категории для импорта. Lint, build и ручная
      проверка `/products` прошли; UI Design Guard accepted. Общий format check
      ограничен существующими предупреждениями в `.tmp`; изменённые файлы прошли
      scoped format check.

- [x] TASK-A063 Единый Seller-стиль обособленных панелей Admin (2026-10-03).
    Общие tokens и panel-классы применены к самостоятельным карточкам Admin и их
    вложенным поверхностям. UI Design Guard accepted; lint, build, scoped
    formatting и git diff --check прошли. Unit/E2E не запускались.
    [Отчёт и файлы](../docs/TASK_A063_REPORT.md).

- [x] TASK-A064 Секционные карточки Admin по референсу Seller (2026-10-03).
    Общий `UiCard #header` применён к UI-kit, дашборду, товарам и основным
    секциям настроек/контента; белое тело растягивается на высоту строки. UI
    Design Guard accepted по статическому аудиту; lint, build, scoped format и
    diff check прошли. Unit/E2E и отдельные мобильные viewport проверки не
    запускались. [Отчёт](../docs/TASK_A064_REPORT.md).

- [x] TASK-A065 Модальные окна Admin с размытым фоном (2026-10-03).
    Общий `UiDialog` получил светлую backdrop-вуаль с blur, радиус 24 px,
    выразительную тень, круглое закрытие и согласованные края sticky footer.
    UI Design Guard accepted; ручной viewport review: 320, 640, 768, 1024,
    1280 px. Lint, format и build прошли; unit/E2E не запускались.
    [Отчёт](../docs/TASK_A065_REPORT.md).

- [x] TASK-A087 Подсказка выбора групп вариантов товара и вторичные действия
    (2026-10-04): пояснено, как выбрать существующую группу или переключиться на
    «Новую группу»; селектор растянут на всю ширину, кнопки «Удалить группу» и
    «Не объединять» вторичные, «Сохранить группу» основная; селектор занимает
    полную ширину, его меню показано поверх карточки. Escape закрывает сначала меню,
    а подсказка связана с селектором через `aria-describedby`. Удаление остаётся
    красным, disabled-состояние нейтральным. UI Design Guard accepted.
    Lint, format и build passed; 7 целевых E2E passed на ширинах 320–1280 px
    и для похожих товаров. Полный E2E: 252/299 passed, 47 упали на baseline и
    других несвязанных сценариях.
    Unit suite: 89 passed, 2 не относящихся к diff failure в `authCard.test.ts`
    и `uiKitShowcase.test.ts`.

- [x] TASK-A088 Единая поверхность модальных окон Admin (2026-10-04):
    убраны горизонтальные линии между шапкой, содержимым, подвалом и внутри
    модальных окон; правило также охватывает портальные select-меню из диалогов.
    Сохранены текущие отступы и боковые контуры. Обновлён UI Kit. UI Design Guard
    принял изменения без замечаний. Lint, build и scoped Prettier прошли; целевые
    проверки меню и разделителей прошли на 320, 640, 768, 1024 и 1280 px (5/5).
    Полный E2E: 253 сценария прошли; оставшиеся падения относятся к baseline и
    другим сценариям. Unit suite: 89 passed, 2 ранее существовавших failures в
    `authCard.test.ts` и `uiKitShowcase.test.ts`.

- [x] TASK-A089 Обычный вид диалогов подтверждения (2026-10-04): пояснение в
    общем `ConfirmDialog` отображается простым текстом в теле окна, destructive
    кнопка остаётся красной, «Отмена» и подтверждение располагаются рядом.
    Ошибки по-прежнему выводятся уведомлением с alert-семантикой. UI Design Guard
    принял изменение. Lint, build, целевой unit test и целевые E2E на ширинах
    320/640/768/1024/1280 px прошли; полный E2E — 260 passed, 43 прежних
    failures на baseline и независимых сценариях. Unit suite: 89 passed,
    2 прежних failure в `authCard.test.ts` и `uiKitShowcase.test.ts`.
