# Product Collections Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Управляемые коллекции брендов, назначение товарам и условный блок коллекции на витрине с безопасным переносом старой характеристики.

**Architecture:** ProductCollection — отдельная сущность каталога. Laravel владеет связями/инвариантами и REST API, Vue Admin редактирует данные, Nuxt отображает публичную проекцию. Старые значения сохраняются для проверки, новый источник — collection_id.

**Tech Stack:** Laravel 13/PHP/PostgreSQL, Vue 3/TypeScript/Vite, Nuxt 4, существующая медиатека и UI-kit.

**Spec:** ../specs/2026-10-30-product-collections-design.md

## Global Constraints

- Название и headline до 255 символов; description до 10000 символов.
- collection_id nullable; uses_collections false по умолчанию, без наследования.
- Связь требует совпадения бренда и разрешённой категории; очистка только явная.
- Доступ catalog.manage; медиа сохраняет собственные permissions.
- Нет коллекции — нет блока и пустого отступа. Неполный блок также скрыт.
- Сохранить name/brand_slug публичного collection и старые ссылки каталога.
- Не менять утверждённую палитру и общие стили; независимый UI Design Guard обязателен.
- Не переписывать старые migrations, не менять рабочие данные без preflight/backup.
- Не создавать commit или push. Работа на codex/product-collections в текущем checkout.

## Review Focus

- Устаревший ответ списка другого бренда не должен подменять варианты коллекций в форме товара — Task 2.
- Пустая ячейка и отсутствие колонки в старом Excel имеют разные последствия — Task 4.
- Бренд/категория/коллекция, изменённые параллельно, не оставляют несовместимую связь — Task 1.
- Картинка из private media не должна попасть в публичный блок — Task 1/5.
- Неоднозначный регистр/тип legacy-значений не должен создавать догаданные связи — Task 3.

### Task 1: Backend collection boundary and public projection

**Files:** create ProductCollection model/factory/policy, Requests, Resource, Query,
CollectionController, ProductCollectionManagementService and assignment validation,
2026_10_30 migration; modify Product/Category/Brand, their management requests/resources/services,
MediaManagementService, PublicProductQuery/Resource, PublicCatalogQuery, integrity/completeness/group
services and audit snapshots. Tests: tests/Feature/Api/ProductCollectionManagementTest.php,
PublicProductTest.php, relevant PostgreSQL integration tests.

**Interfaces:** product_collections(id,brand_id,name,slug,headline,description,image_id,is_active);
products.collection_id; categories.uses_collections. REST /admin/collections supports search,
brand_id,is_active,page. Resource includes brand, image, products_count. Service
create(User,array):ProductCollection; update(User,ProductCollection,array):ProductCollection;
delete(User,ProductCollection):void. Product create/update enforce assignment for every caller.
Public collection adds id,slug,headline,description,country_code,image_url,image_alt.

- [x] Write tests for CRUD/403, normalized uniqueness, public-image validation, assignment/mismatch,
  inactive retention/new assignment rejection, deletion protection, category disable, legacy archival.
- [x] Run new tests and observe expected missing-route/schema failures.
- [x] Implement schema and application boundary; add composite FK/check/indexes on PostgreSQL.
- [x] Exclude legacy kollektsiya from writes/required/category/group shared validation while preserving rows;
  block creating new legacy axes. Preserve existing group semantics for other attributes.
- [x] Run feature tests, Pint, architecture/PHPStan; prove concurrent assignment and migrations on PostgreSQL.

### Task 2: Admin collection workspace and assignment

**Files:** create frontend/admin/src/features/collections/{components,composables,services,types,validation},
CollectionsView.vue; modify router/navigation, categories forms/types and products form/types/editor.
Tests: tests/productCollections.test.ts, e2e/productCollections.spec.ts.

**Interfaces:** consumes Task 1 REST/resource fields; collection selectors filter by brand_id;
current inactive record may be retained but not selected anew. Uses existing Ui*/MediaImageEditor.

- [x] Write/run tests for list errors/retry, search/filter, create/edit, nullable assignment,
  brand change retaining explicit incompatible selection, and stale response protection.
- [x] Implement feature workspace/dialog/composables/services and category toggle.
- [x] Add product selector using brand-filtered pagination/search without truncating results.
- [x] Run lint, format, unit/build and focused production E2E/axe at 320/640/768/1024/1280.

### Task 3: Legacy preflight and transfer

**Files:** create backend/app/Services/LegacyCollectionMigrationService.php,
backend/app/Console/Commands/MigrateProductCollections.php,
backend/tests/Feature/Console/MigrateProductCollectionsTest.php.

**Interfaces:** command catalog:collections-migrate defaults to read-only JSON preflight;
--apply requires actor, acknowledged backup and explicit enabled categories. Service uses Task 1
management methods for writes; report contains IDs/reasons/counts, no invented metadata.

- [x] Write/run tests for exact names per brand, no-brand/type/case ambiguity, existing assignments,
  axes blockers, idempotency, explicit category selection and denied actor.
- [x] Implement scan and apply with revalidation/transaction, exact matches only, source retention.
- [x] Test preflight/apply/reapply on isolated PostgreSQL copy and document maintenance cutover/restore.

### Task 4: Workbook, search and compatibility

**Files:** modify ProductWorkbookSchema, ProductImportTemplateSchema/Reader/CategoryValidator,
CategoryProductRowImporter, ProductImportRowMapper/PlanBuilder, ProductExportService and search owning
sources. Create ProductCollectionImportResolver; tests in ProductCollectionImportTest.php.

**Interfaces:** new collection_name column; old attribute.kollektsiya alias resolves only existing
collection for selected brand; absence preserves, explicit blank clears, conflicts reject atomically.

- [x] Write/run import/export tests for new/old columns, both equal/conflicting, blank/absent,
  unknown names, group independence and canonical source after stale legacy rows remain.
- [x] Implement resolver and wire every supported workbook path, templates/export/search/clone.
- [x] Run category/generic/group import/export regressions and collection-specific tests.

### Task 5: Storefront collection section

**Files:** create frontend/client/app/features/product/components/ProductCollectionStory.vue;
modify product types/services and page composition; tests/e2e/product-collections.spec.ts;
docs/CLIENT_UI_KIT.md registry rule.

**Interfaces:** consumes nullable public collection from Task 1; headline falls back to name;
all section markup requires collection, nonempty description and public image_url.

- [x] Write/run E2E for absent section with null, incomplete/private/inactive collection and present
  section for complete collection; test navigation between products and brand-scoped link.
- [x] Implement reference layout after detail tabs and before accessories using existing tokens.
- [x] Verify SSR/hydration, long text, mobile order, alt/aspect ratio, five widths and accessibility.
- [x] Run Client lint/format/typecheck/build and focused production E2E; record UI-kit rule.

### Task 6: Contract, integration and independent review

**Files:** docs/API.md, docs/ARCHITECTURE.md, docs/openapi.json owning tooling/schemas,
tasks/IN_PROGRESS.md/DONE.md, docs/PRODUCT_COLLECTIONS_2026-10-30.md; implementation plan status.

**Interfaces:** all fields/routes above; no unspecified API drift or automatic data loss.

- [x] Update OpenAPI and canonical docs plus runnable migration/backup/cutover instructions.
- [x] Run backend mandatory checks, PostgreSQL feature/integration, Admin/Client checks and relevant E2E.
- [x] Independent code review and UI Design Guard for both UIs; resolve blocking findings with tests.
- [x] Inspect git diff --check/stat/status and report changed areas, checks, remaining deployment steps.

## Execution record

Owner approved the full spec and implementation. No extra approval checkpoint is needed for
this technical decomposition. Primary agent owns integration, migration/import and docs; bounded
parallel implementers may own Backend, Admin and Client in non-overlapping files. No nested delegation.
An execution ledger lives in docs/superpowers/plans/2026-10-30-product-collections-progress.md.
