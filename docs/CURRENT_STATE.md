# Текущее состояние проекта

На 2026-10-01 Nuxt Client (`TASK-119`, `TASK-C002`–`TASK-C005`) подключён к управляемому
контенту Phase 7: типизированным упорядоченным блокам страниц, опубликованному слайдеру,
общим шапке/подвалу и SEO. Черновики отделены от опубликованных snapshots;
публикация выполняется отдельным действием. Сайт сохраняет визуальный язык
[`CLIENT_UI_KIT.md`](CLIENT_UI_KIT.md), адаптивные секции и локальные изображения
как начальные значения. Редактор блоков доступен в Admin → «Контент» → «Страницы».
Общие шапка, навигация и подвал редактируются и публикуются независимо во вкладке
«Общее оформление»; Nuxt получает их отдельным SSR-запросом. Магазины находятся
в той же рабочей области. Панель «Вид фильтров» отделена от данных каталога;
её настройки появятся с клиентскими фильтрами в `TASK-120`.
`/contacts`, `/about`, `/catalog` получают опубликованные блоки при SSR; контакты
используют реквизиты/магазины, каталог — публичную проекцию реальных товаров с ценами
и единицами продажи. Сохранённый черновик страницы и общего оформления отображается
настоящим Nuxt-рендером в защищённом `/preview/<slug>` внутри Admin, с режимами экрана
и отдельным полноразмерным просмотром. Доступ требует актуальной сессии и `content.manage`;
черновик не попадает в SSR payload, storage, URL или публичный API. Полные товарные
сценарии остаются последующими задачами.

Базовый срез после закрытия `TASK-A042` обновлён состоянием `TASK-119` и `TASK-C002`–`TASK-C005`. Исторические результаты
аудитов и их числовые срезы не обновляются задним числом; текущий набор обязательных проверок
описан в [`CI.md`](CI.md), завершённые задачи — в [`tasks/DONE.md`](../tasks/DONE.md).

Admin frontend refactoring принят. Follow-up проверки `TASK-A045`–`TASK-A050` закрыли cart-token,
OpenAPI, dependency bootstrap, PostgreSQL feature-suite, Redis delivery и Admin full-stack gaps.
Lifecycle policy `TASK-A043` принята для реализации, а repository-side controls `TASK-A044`
завершены. `TASK-090` добавила site settings и журнал согласований. Production apply остаётся закрыт
отдельным gate `TASK-145` до external
provider evidence и pre-production approvals. Импортный application
layer декомпозирован, production dependencies сделаны явными, сложные admin read queries
вынесены из Controllers, а направления зависимостей и тонкие Controllers защищены blocking
architecture gate.

## Реализовано

| Область | Фактическое состояние |
| --- | --- |
| Foundation | Laravel API-only, Vue Admin, Nuxt Client с первой главной страницей, Docker Compose, PostgreSQL, Redis, queue, scheduler, versioned `/api/v1`, stable error envelope и OpenAPI. |
| Backend architecture | Нетривиальные admin filters/search/sort, eager loading и audit metadata enrichment изолированы в Query objects; основные equality-filter/sort paths подкреплены PostgreSQL indexes. AST guard блокирует обратные зависимости слоёв, DB/Eloquent mutations и service locator/global request/auth helpers в Controllers; размер и complexity публикуются как review-сигналы. |
| Access control | Admin authentication, password reset, active-user guard, RBAC, granular permissions, audit log, snapshots, retention и PostgreSQL immutability. |
| Catalog | Categories, brands, attribute groups/typed attributes, standalone sellable products, product groups, generated immutable SKU, images, related products, filters/search и durable storage cleanup. Legacy variants остаются read-only до отдельной verified migration. |
| Import/export | XLSX export/import, category templates, preflight/error reports, resumable queue work, ZIP image import, price/status и product-group workbooks. Workbook I/O, parsing/validation, planning, mutation и report/template generation разделены на небольшие сервисы; orchestration сохраняет прежние transaction, locking и checkpoint contracts. Queue entry points получают обязательные сервисы через container injection; только прямые Laravel `failed()` callbacks используют документированный узкий adapter. |
| Cart and orders | Public guest cart with HMAC-only bearer lookup, configurable TTL and lock-safe cleanup; locked checkout, immutable order snapshots, random order number, status/payment management, history, internal comments и confirmation email. |
| Contacts | Public callback/email/partner forms, assignment, protected list/detail API, statuses, history и internal comments. |
| Content and Client | Типизированные блоки, изолированные snapshots публикаций и защита медиа обеих версий; явная публикация из Admin. Общее оформление имеет отдельный черновик и публикацию; магазины объединены с редактором в «Контенте». Nuxt `/`, `/contacts`, `/about`, `/catalog` используют API при SSR с SEO, адаптивностью и состояниями ошибок/пустых данных. C005 подключает точный защищённый предпросмотр сохранённых черновиков; legacy UI-входы закрыты. |
| Admin frontend | Route views и feature-слои выделены, compatibility adapters удалены, source-of-truth UI-kit и обязательные lint, unit, production E2E, accessibility, responsive и visual gates действуют. Orders и contacts имеют рабочие list/detail/workflow экраны. |
| Production-like проверки | Полный Laravel feature suite выполняется на PostgreSQL; отдельные CI profiles проверяют реальную Redis queue delivery, Admin SPA → Sanctum/Laravel API и lock-aware bootstrap dependency volumes. |
| Repository security | Database exports удалены из current tree/public branches/tags; ignore, history artifact gate и полный Gitleaks scan обнаруживают повторное добавление. Остаточная доступность test-only объектов через четыре GitHub-managed PR refs принята владельцем без удаления PR. GitHub ruleset/branch protection пока не настроен, поэтому CI не является pre-receive запретом. |

## Принятые границы

- Laravel — единственное бизнес-ядро для Admin и будущего Client; Blade и server-rendered
  storefront не используются.
- Товар — самостоятельная продаваемая позиция. Product group служит навигации между товарами и
  не является nested variant.
- Публичные формы, cart и checkout не принимают client-owned prices, totals, statuses или
  workflow fields.
- PII минимизируется и не попадает в обычные application logs или audit metadata.
- OpenAPI — машинный контракт; [`API.md`](API.md) описывает только сценарии и нетривиальные правила.

## Ограничения и обязательные follow-ups

| Приоритет | Ограничение | Владелец |
| --- | --- | --- |
| High | Для business PII orders/contacts policy, gated repository-side controls и журнал согласований готовы, но отсутствуют фактические pre-production approvals и external provider evidence; production apply выключен. | `TASK-145` |

Функциональная приёмка Phases 0–6 и Admin frontend refactoring сохраняется. `TASK-A042` закрыта с
явным принятием остаточного риска test-only PR refs; публикация новых database exports остаётся
запрещённой policy и проверяется обязательными CI checks. Подробный текущий статус активных работ находится в
[`tasks/IN_PROGRESS.md`](../tasks/IN_PROGRESS.md).

## Отложено по roadmap

| Phase | Не реализовано | Зависимость |
| --- | --- | --- |
| 7 — Content | `TASK-090`–`TASK-096`, `TASK-C001`–`TASK-C005` реализованы. Страницы имеют редактор блоков, контекстные ресурсы и точный Nuxt-предпросмотр сохранённого черновика. Общее оформление и магазины объединены в «Контенте»; legacy UI закрыт. | Категории и бренды используют managed media FK, документы — отдельные ordered associations. Внешние URL старых баннеров остаются fallback до явной замены. |
| 8 — SEO | Managed metadata, canonical, sitemap, robots, redirects, structured data и AI drafts. | SEO использует managed media для OG image; slug остаётся Catalog-owned. |
| 9 — Analytics | Orders/paid sales dashboards и reports. | Использует order `paid_at`, не только `created_at`. |
| 10 — Client | Страницы категорий/товаров, cart, checkout, confirmation и полный managed SEO. Главная, контакты, о нас и первая публичная страница каталога с реальными товарами уже готовы. | Использует существующие API contracts без дублирования business logic. |
| 11 — Production | Production Compose, CI/CD delivery, backups, monitoring, security hardening и deployment. | Требует завершённых operational policies. |

## Проверки и историческое evidence

Актуальные обязательные jobs, локальные команды и safety guards перечислены только в
[`CI.md`](CI.md). Количество маршрутов, файлов, тестов и assertions намеренно не копируется сюда:
оно изменяется вместе с кодом и подтверждается самим CI run.

Исторические снимки приёмки сохранены без ретроспективной подмены результатов:

- [`INTERIM_AUDIT_FINAL.md`](INTERIM_AUDIT_FINAL.md) — приёмка Phases 0–6 и последующие findings;
- [`ADMIN_REFACTORING_BASELINE.md`](ADMIN_REFACTORING_BASELINE.md) — baseline и повторная приёмка
  Admin refactoring;
- специализированные `*_AUDIT.md` — evidence конкретного слоя на указанную в них дату.
