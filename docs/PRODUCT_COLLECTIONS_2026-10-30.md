# Коллекции товаров — 2026-10-30

Спецификация утверждена владельцем: [design](superpowers/specs/2026-10-30-product-collections-design.md).
План и прогресс: [plan](superpowers/plans/2026-10-30-product-collections.md),
[ledger](superpowers/plans/2026-10-30-product-collections-progress.md).

## Пользовательский сценарий

Admin: «Товары → Коллекции» (`/collections`). Доступ — `catalog.manage`.
По уточнению владельца список оформлен по образцу брендов: название и slug,
отдельная колонка статусов и сведений, действия справа; описание под заголовком.
На узких экранах сведения и действия перестраиваются в соответствии с брендами.
Проверка этого уточнения: Admin lint, format, build и 281 unit-тест прошли;
focused Playwright/axe на 320/640/768/1024/1280 px прошёл, независимый
UI Design Guard принял результат.
Создание/редактирование: название, бренд, технический код, необязательный заголовок
блока, описание, фото из медиатеки и активность. Страна показывается из бренда.
Доступны поиск, фильтры, счётчик товаров и отметка незаполненного блока.
Коллекции с одинаковым названием у разных брендов независимы.

В категории включается «Использовать коллекции». В карточке товара рядом с
брендом выбирается его коллекция или «Без коллекции». При смене бренда/категории
несовместимая связь не теряется молча: требуется явное исправление. Неактивная
текущая коллекция сохраняется, новое назначение запрещено. Занятую коллекцию
нельзя удалить или передать другому бренду; бренд с коллекциями также защищён.

На сайте интерьерный блок по `productExample.html` находится после вкладок
характеристик/описания. Слева — название/заголовок, бренд/страна, описание и
«Товары коллекции», справа — фото; до 900 px — вертикальная компоновка.
Нет коллекции — нет всего блока и пустого отступа. Незаполненное описание,
отсутствующее/непубличное/сломанное фото также скрывают секцию. Текстовая ссылка
на активную коллекцию может работать до заполнения интерьерного блока.
Фиктивной визуализации, демонстрационного текста и фотографий в данные не добавлено.

## Данные и совместимость

Новая migration: `2026_10_30_000001_create_product_collections.php`; старые
migrations не менялись. `product_collections` хранит бренд, название, slug,
headline, description, image_id, is_active; `products.collection_id` nullable;
`categories.uses_collections` false по умолчанию, без наследования.
Индексы, normalized unique name, составной FK и CHECK проверены на PostgreSQL.

Коллекция отделена от групп вариантов и не синхронизируется другим участникам.
`kollektsiya` остаётся в БД архивом, исключена из записи/обязательности/категорийной
совместимости и вариантов осей. Старые значения не заменяются при изменении
новой коллекции. Поиск, экспорт и клонирование используют новую связь.

Excel: `collection_name` (пользовательский заголовок «Коллекция»); старый
`attribute.kollektsiya` распознаётся как alias. Разрешаются только существующие
коллекции бренда; неизвестное имя/разные значения двух колонок — ошибка строки.
Пустая ячейка явно снимает связь; отсутствующая колонка старого файла сохраняет
текущее назначение. Архивные значения при замене остальных характеристик сохранены.

API и OpenAPI обновлены до v13.0 с сохранением HTTP `/api/v1`. Schema объекта
называется `CatalogCollection`: имя `ProductCollection` уже используется для
постраничного списка товаров. План совместимого выпуска —
[OPENAPI_MIGRATION_PLAN.md](OPENAPI_MIGRATION_PLAN.md).

## Перенос: порядок для следующего окружения

1. Выпустить согласованную версию Backend/Admin/Client в закрытом окне, заранее
   проверить PostgreSQL-копию и сохранённый backup/restore-порядок.
2. Read-only проверка до schema migration:

   ```sh
   php artisan catalog:collections-migrate
   ```

   Проверить `issues`, `suggested_category_ids` и будущие назначения.
   Группы с legacy-осью, разные регистры/написания, нестроковые значения,
   отсутствие бренда и конфликт готового назначения не исправляются догадкой.
3. Остановить записи каталога и queue/scheduler, включить maintenance; создать
   свежий защищённый backup. Backup с ПДн не хранить в public/репозитории.
   Восстановление выполнять в явно разрешённом локальном изолированном хранилище.
4. `php artisan migrate --force`, повторить preflight, затем:

   ```sh
   php artisan catalog:collections-migrate --apply --actor=<ACTIVE_ADMIN_ID> \
     --backup-verified --enable-category=<REVIEWED_CATEGORY_ID>
   ```

   Для нескольких категорий повторить `--enable-category`. Команда требует
   активного сотрудника с правом каталога, подтверждения backup и maintenance
   вне testing; изменения проходят application layer и аудит.
5. `ready_for_cutover: true` обязателен перед открытием. Повторный apply должен
   дать `assigned: 0`. В случае оставшихся проблем apply возвращает ненулевой код;
   он может уже перенести безопасные строки, поэтому смотреть отчёт, не считать
   код выхода признаком полного rollback. Неразобранные записи не перезаписывать.
6. Возобновить queue/scheduler, снять maintenance, проверить настоящие маршруты.
   Неполные перенесённые коллекции видны в Admin для заполнения; новых фото/текстов
   автоматически не создаётся.

Rollback схемы не сохраняет новые метаданные коллекций. После записи новых данных
нужна защищённая копия нового состояния и восстановление проверенной пары
прежняя БД/приложение. Архивная характеристика не содержит нового контента.

## Локальная проверка данных

Preflight текущей БД: 6 значений, все однозначны; категории 27 «Керамогранит»,
28 «Ступени», 29 «Плитка». Перенос сантехнике/сопутствующим категориям не включался.
Защищённый backup создан в Docker PostgreSQL:
`/var/lib/postgresql/data/collections-backups/before-collections-2026-10-30.dump`,
права 0600. Пользователь явно разрешил локальную проверку восстановления в
`agatceramic-collections-test-pg`, БД `agatceramic_collections_copy`.
Полное восстановление успешно; schema/apply на копии дали `assigned: 6`,
`issues: []`, `ready_for_cutover: true`; повторный apply — `assigned: 0`.
Перед переключением остановлены queue/scheduler, включён maintenance и создан
свежий backup `before-collections-cutover-2026-10-30.dump` в том же защищённом
каталоге (0600). Он также полностью восстановлен и проверен на копии перед
применением к текущей БД. Временные host backup-файлы после передачи удалены.

Текущая БД переключена: 2 коллекции («Аллея», Eclettica), 6 назначений,
6 исходных legacy-значений сохранены. Несовместимых brand/category связей — 0.
Повторный apply: 0 изменений, `issues: []`, `ready_for_cutover: true`.
Queue/scheduler возобновлены, maintenance снят. Admin `/collections`, публичный
API товара и настоящая SSR-карточка возвращают HTTP 200. У перенесённой «Аллея»
без description/image интерьерная секция отсутствует, текстовая навигация работает.
Временный `agatceramic-collections-test-pg` удалён вместе с tmpfs-копиями данных;
защищённые backups сохранены только в основном PostgreSQL volume.

## Проверки и независимое ревью

- Backend SQLite: 699 tests, 7728 assertions, 4 ожидаемых skip, без failures.
- PostgreSQL feature коллекций/переноса/публичной карточки: 27 tests/189 assertions.
- PostgreSQL concurrency: весь файл, 15 tests/77 assertions, включая четыре
  чередования category/assignment/brand/deactivation. Schema up/down/up прошла.
- Полный PostgreSQL feature suite: 603 tests/7389 assertions, без failures/skips.
- Composer validate, Pint (673 файла), PHPStan, architecture guard прошли.
  Architecture report содержит review-сигналы длины/сложности, без блокирующих
  нарушений; границы нового модуля рассмотрены независимым reviewer.
- Composer audit НЕ прошёл: существующий `symfony/mime`, CVE-2026-45070,
  medium. Composer manifest/lock эта задача не изменяет. Обновление зависимости
  не включено в модуль коллекций.
- OpenAPI route/schema tests (4/2823), Redocly/project conventions, 5 tooling
  tests и compatibility checker с v13.0 migration plan прошли.
- Admin lint/format/build; unit 55 файлов/281 тест; production E2E 28/28,
  включая коллекции и существующую навигацию, прошли.
- Client typecheck/format/build прошли; в исходном focused run 15/17,
  два найденных дефекта исправлены, оба теста прошли; финальный responsive/axe
  тест после согласования breakpoint 900 px также прошёл. Отдельного lint script
  в Client нет, полный Client E2E в этой задаче не запускался.
- Независимый UI Design Guard ACCEPTED: 30 screenshots, пять ширин
  320/640/768/1024/1280, Admin и Client с длинным текстом, без блокирующих
  визуальных замечаний. Утверждённая палитра и shared controls сохранены.
- Независимый code reviewer выявил два Important: варианты false/0/'0' в
  защите занятой категории и переполнение slug при переносе длинных имён.
  Оба воспроизведены regression tests, исправлены; связанный suite 17/122 GREEN.
  Critical и Minor замечаний нет.

Артефакты проверки находятся в игнорируемых `.tmp/collections/`,
`.tmp/client-collections-review/`, `frontend/admin/.tmp/collections-review/`.
В логах нет полного backup или персональных данных.

## Изменённые области

Backend: migration/model/factory/policy, REST Requests/Controller/Query/Resource,
application collection/assignment services, product/category/brand management,
public queries/resources, media usage, audit, legacy preflight/command,
workbook import/export/template, group axes, feature/integration/contract tests.
Admin: `features/collections/`, CollectionsView, router/navigation,
category/product forms/types, product clone and attribute boundary, unit/E2E.
Client: ProductCollectionStory, ProductDetail composition, DTO/mapping,
product fixture/E2E; CLIENT_UI_KIT UI-019. Canonical API/architecture/OpenAPI,
migration plan, specification, implementation plan и task status обновлены.

Полный список изменённых файлов приведён ниже.

## Полный список файлов (105)

- `backend/app/Console/Commands/MigrateProductCollections.php`
- `backend/app/Http/Controllers/Api/V1/Admin/ProductAttributeValueController.php`
- `backend/app/Http/Controllers/Api/V1/Admin/ProductCollectionController.php`
- `backend/app/Http/Controllers/Api/V1/Admin/ProductController.php`
- `backend/app/Http/Requests/Api/V1/Admin/Concerns/ValidatesCategoryAttributeValues.php`
- `backend/app/Http/Requests/Api/V1/Admin/ListProductCollectionsRequest.php`
- `backend/app/Http/Requests/Api/V1/Admin/StoreCategoryRequest.php`
- `backend/app/Http/Requests/Api/V1/Admin/StoreProductCollectionRequest.php`
- `backend/app/Http/Requests/Api/V1/Admin/StoreProductRequest.php`
- `backend/app/Http/Requests/Api/V1/Admin/UpdateCategoryRequest.php`
- `backend/app/Http/Requests/Api/V1/Admin/UpdateProductCollectionRequest.php`
- `backend/app/Http/Requests/Api/V1/Admin/UpdateProductRequest.php`
- `backend/app/Http/Resources/Catalog/CategoryResource.php`
- `backend/app/Http/Resources/Catalog/ProductCollectionResource.php`
- `backend/app/Http/Resources/Catalog/ProductResource.php`
- `backend/app/Http/Resources/PublicProductDetailResource.php`
- `backend/app/Models/Brand.php`
- `backend/app/Models/Category.php`
- `backend/app/Models/Product.php`
- `backend/app/Models/ProductCollection.php`
- `backend/app/Policies/ProductCollectionPolicy.php`
- `backend/app/Providers/AppServiceProvider.php`
- `backend/app/Queries/MediaUsageQuery.php`
- `backend/app/Queries/ProductCollectionQuery.php`
- `backend/app/Queries/ProductQuery.php`
- `backend/app/Queries/PublicCatalogQuery.php`
- `backend/app/Queries/PublicProductQuery.php`
- `backend/app/Services/AuditSnapshotService.php`
- `backend/app/Services/BrandManagementService.php`
- `backend/app/Services/CatalogAttributeIntegrityService.php`
- `backend/app/Services/CategoryManagementService.php`
- `backend/app/Services/CategoryProductRowImporter.php`
- `backend/app/Services/LegacyCollectionMigrationService.php`
- `backend/app/Services/LegacyCollectionPreflight.php`
- `backend/app/Services/ProductAttributeValueManagementService.php`
- `backend/app/Services/ProductCollectionAssignmentValidator.php`
- `backend/app/Services/ProductCollectionImportResolver.php`
- `backend/app/Services/ProductCollectionManagementService.php`
- `backend/app/Services/ProductCompletenessService.php`
- `backend/app/Services/ProductExportService.php`
- `backend/app/Services/ProductGroupAxisChoices.php`
- `backend/app/Services/ProductGroupManagementService.php`
- `backend/app/Services/ProductImportGroupValidationService.php`
- `backend/app/Services/ProductImportRowMapper.php`
- `backend/app/Services/ProductImportService.php`
- `backend/app/Services/ProductImportTemplateReader.php`
- `backend/app/Services/ProductImportTemplateSchema.php`
- `backend/app/Services/ProductImportTemplateWriter.php`
- `backend/app/Services/ProductManagementService.php`
- `backend/app/Support/AuditLabels.php`
- `backend/app/Support/ProductWorkbookSchema.php`
- `backend/database/factories/ProductCollectionFactory.php`
- `backend/database/migrations/2026_10_30_000001_create_product_collections.php`
- `backend/routes/api/v1/admin.php`
- `backend/tests/Feature/Api/ProductCollectionConstraintTest.php`
- `backend/tests/Feature/Api/ProductCollectionImportTest.php`
- `backend/tests/Feature/Api/ProductCollectionManagementTest.php`
- `backend/tests/Feature/Api/ProductExportTest.php`
- `backend/tests/Feature/Api/PublicProductCollectionTest.php`
- `backend/tests/Feature/Api/PublicProductTest.php`
- `backend/tests/Feature/Console/MigrateProductCollectionsTest.php`
- `backend/tests/Integration/PostgresCatalogConcurrencyTest.php`
- `backend/tests/Support/catalog_concurrency_worker.php`
- `backend/tests/Unit/OpenApiCatalogContractTest.php`
- `backend/tests/Unit/Services/ProductGroupAxisChoicesTest.php`
- `docs/API.md`
- `docs/ARCHITECTURE.md`
- `docs/CLIENT_UI_KIT.md`
- `docs/OPENAPI_MIGRATION_PLAN.md`
- `docs/PRODUCT_COLLECTIONS_2026-10-30.md`
- `docs/openapi.json`
- `docs/superpowers/plans/2026-10-30-product-collections-progress.md`
- `docs/superpowers/plans/2026-10-30-product-collections.md`
- `docs/superpowers/specs/2026-10-30-product-collections-design.md`
- `frontend/admin/e2e/navigation.spec.ts`
- `frontend/admin/e2e/productCollections.spec.ts`
- `frontend/admin/src/features/categories/components/CategoryMainSection.vue`
- `frontend/admin/src/features/categories/composables/useCategoryForm.ts`
- `frontend/admin/src/features/categories/types/category.types.ts`
- `frontend/admin/src/features/categories/validation/category.schema.ts`
- `frontend/admin/src/features/collections/components/CollectionFormDialog.vue`
- `frontend/admin/src/features/collections/components/CollectionsList.vue`
- `frontend/admin/src/features/collections/components/CollectionsWorkspace.vue`
- `frontend/admin/src/features/collections/components/ProductCollectionField.vue`
- `frontend/admin/src/features/collections/composables/useCollectionAssignment.ts`
- `frontend/admin/src/features/collections/composables/useCollectionCatalog.ts`
- `frontend/admin/src/features/collections/composables/useCollectionForm.ts`
- `frontend/admin/src/features/collections/services/collections.ts`
- `frontend/admin/src/features/collections/types/collection.types.ts`
- `frontend/admin/src/features/collections/validation/collection.schema.ts`
- `frontend/admin/src/features/products/components/ProductMainSection.vue`
- `frontend/admin/src/features/products/composables/useProductEditor.ts`
- `frontend/admin/src/features/products/services/productAttributes.ts`
- `frontend/admin/src/features/products/types/product.types.ts`
- `frontend/admin/src/layouts/navigation.ts`
- `frontend/admin/src/router/index.ts`
- `frontend/admin/src/views/CollectionsView.vue`
- `frontend/admin/tests/productCollections.test.ts`
- `frontend/client/app/features/product/components/ProductCollectionStory.vue`
- `frontend/client/app/features/product/components/ProductDetail.vue`
- `frontend/client/app/features/product/services/products.ts`
- `frontend/client/app/features/product/types/product.ts`
- `frontend/client/e2e/fixtures/product-api.mjs`
- `frontend/client/e2e/product-collections.spec.ts`
- `tasks/DONE.md`
