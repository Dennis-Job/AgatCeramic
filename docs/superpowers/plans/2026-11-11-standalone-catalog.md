# Standalone Catalog Implementation Plan

> **For agentic workers:** Use superpowers:subagent-driven-development for independent API/client tasks and integrated review. Track steps below. User approved execution; no further planning approval or commits.

**Goal:** Independent `/catalog` with complete category-scoped filtering and admin-controlled category order.
**Architecture:** Extend the existing Laravel public catalog with opt-in filter metadata; add a Nuxt catalog feature independent of CMS blocks. CMS title/intro/SEO are optional. Keep existing block renderer and public card projection compatible.
**Tech Stack:** Laravel 13, PostgreSQL/SQLite, Nuxt 4, Vue 3, TypeScript, Playwright.
**Spec:** `docs/superpowers/specs/2026-11-11-standalone-catalog-design.md`

## Global Constraints

- Characteristics require `is_filterable` AND explicit assignment to the selected category. No inheritance or union; none without a category.
- Preserve approved palette, product cards, PageIntro, UiDialog, focus/motion rules.
- No commits, publication, destructive data operations, or new dependencies.
- Work in current checkout to retain running local project; independent file ownership below.
- Retain existing API inputs and card fields; never expose inventory quantities/private media.

## API Interface (shared across tasks)

Extend `GET /api/v1/catalog`:

- Existing `category`, `brand`, `collection`, `page` remain compatible.
- `include_filters=1` opts into a `filters` member.
- `category_descendants=1` opts into active subtree products; exact category otherwise.
- `price_from`, `price_to`: nonnegative decimals, inclusive bounds.
- `in_stock=1` restricts to positive stock; omitted means all.
- `sort`: `name_asc` (default), `name_desc`, `price_asc`, `price_desc`, `newest`.
- `attributes`: JSON string mapping attribute IDs to `{values: string[]}` for select/multiselect/boolean (`"true"`/`"false"`), `{min?: string,max?: string}` for number/date, or `{text: string}` for string/text.
- Validation errors 422; reject malformed JSON, inappropriate shape/types, unknown/nonfilterable/unassigned characteristics, oversized inputs, collection without brand, reversed bounds.
- Category entries gain nullable `parent_id`; order remains sort_order/name/id.
- `filters`: `{brands: {id:number,name:string,slug:string}[], collections: {id:number,name:string,brand_slug:string}[], attributes: {id:number,name:string,slug:string,type:string,unit:string|null,options:{value:string,label:string}[]}[]}`.
- Metadata from public products in selected category/subtree before other filters, collection choices scoped to selected brand. Attribute definitions only from explicit selected-category assignments. Options reflect real eligible product values, not one result page. No misleading counts or loading full product catalog into PHP.
- Name/ID default order; price nulls last; OR within attribute and AND across attributes; SQL filtering before pagination. JSON comparisons work on SQLite and PostgreSQL.

## Review Focus

- Direct URL characteristic from another category must be rejected, not ignored.
- False/zero/array JSON values must compare by type and preserve meaning.
- CMS failures/unpublication must not block independent catalog SSR.
- Parent category and inactive ancestors must preserve product visibility and reachable navigation.
- Drawer, stale requests, back/forward, draft vs applied filters must retain usable state/focus.

## Task 1 — API and server regression tests (backend implementer)

**Owns:** `backend/app/Queries/PublicCatalog*`, filter query collaborators, `backend/app/Http/Requests/Api/V1/PublicCatalogRequest.php`, `backend/app/Http/Controllers/Api/V1/CatalogController.php`, category/filter resources, backend tests, `docs/openapi.json`, catalog section of `docs/API.md`.

- [x] Add failing feature tests for combined filters, category assignment, no-category rejection, boolean false/zero, all eight attribute types, numeric/date bounds, real option metadata, subtree and inactive ancestors, null price order, malformed input, compatible default projection and pagination.
- [x] Run focused tests and record RED; implement parameter validation, SQL queries, opt-in metadata and public resource additions.
- [x] Run focused catalog/product/collection tests, contract tests, Pint and PHPStan/architecture checks as available; record results, including PostgreSQL coverage or limitation.
- [x] Update API/OpenAPI to exact interface above; do not touch frontend files.

## Task 2 — Nuxt standalone catalog (client implementer)

**Owns:** `frontend/client/app/features/catalog/**`, `app/pages/catalog.vue`, `app/pages/preview/[slug].vue`, new UI primitives if needed, `frontend/client/e2e/catalog.spec.ts`, `e2e/fixtures/catalog-api.mjs`, mock-api hook and relevant existing catalog regression expectations; `docs/CLIENT_UI_KIT.md` catalog rules.

**Consumes:** Task 1 interface. Use feature-local DTO/service; existing CMS block service unchanged.

- [x] Add failing Playwright scenarios for independent SSR with missing/error CMS and absent/disabled block, complete filters and URL, category-specific characteristics and no inheritance, empty/error/retry, 24-product pagination, metadata scoping, filtering/sorting reset, null price, back/forward.
- [x] Implement thin route, optional CMS decoration/SEO with fallback, CatalogPage body, category tree, filter form, toolbar/applied filters/pagination, SSR-safe composable and transport service. Shared ProductCardList; reuse current primitives and add documented generic select/checkbox only if absent.
- [x] Reuse body on protected draft preview without SSR draft leakage or changing guard. Preserve normal CMS block on other routes.
- [x] Test drawer dismissal/focus/reduced motion, five viewport widths, long values, console/hydration and axe; update old tests that explicitly assumed old /catalog layout while preserving CMS renderer regression coverage.
- [x] Run typecheck/build/format and targeted E2E, document tokens/components/states in UI-kit.

## Task 3 — Admin guidance and integrated review (primary)

**Owns:** category sort helper, `frontend/admin/src/features/appearance/components/AppearanceWorkspace.vue`, `frontend/admin/src/features/pages/components/PageBlocksEditor.vue`, admin tests as appropriate, `docs/CONTENT_WORKSPACE_UX.md`, tasks and progress record.

- [x] Clarify category field order affects storefront; explain catalog page blocks do not define product listing, preserve saved blocks/normal CMS editor; replace obsolete appearance notice with catalog data source guidance and permitted links.
- [x] Update docs and retain future presentation controls as explicitly deferred task requirement.
- [x] Integrate API/client, inspect diff, run relevant admin lint/build/unit/format and regression checks.
- [x] Independent task code review and UI Design Guard; address findings and run relevant tests again.
- [x] Verify real local catalog, SSR, preview, responsive screenshots, category order source, filtering; record final commands and limitations.

## Progress / decisions

- Design approved by owner after category-specific characteristic clarification.
- Initial working tree: only this task's spec and TODO note modified.
- No separate worktree: retain active local project and avoid moving uncommitted task documents; no git mutations requested.
- New appearance controls remain deferred; original TASK-120 appearance subrequirement must not be marked done.
- Admin baseline build passed. Updated existing appearance regression fails against old build with expected obsolete notice (RED); source guidance updated. Unit suite: 63 files / 319 tests passed.
- Test environment: set `PLAYWRIGHT_BROWSERS_PATH=C:/Users/dkargopolov/AppData/Local/ms-playwright`; sandbox default resolves absent browser cache. No repository/browser dependency changes needed.
- Admin final selected E2E: 52 cases, `.tmp/standalone-catalog/admin-final/.last-run.json` status passed. Avoid Playwright Windows owned-server shutdown hang using temporary local runner; no product configuration changed.
- API review fixed present-but-empty range bounds returning 500 (now 422), and batched metadata option extraction to at most three distinct SQL scans across categorical attributes.
- Final full SQLite verification: 847 tests, 9342 assertions, 8 skips (including PostgreSQL-only Unicode case). PostgreSQL focused verification: 53 tests, 3061 assertions. Pint/PHPStan/architecture/Composer validate/audit and OpenAPI gates passed. API evidence: `.tmp/standalone-catalog/api-results.md`.
- Real local Nuxt restarted to invalidate stale Windows bind-mount watcher output. Browser confirmed standalone category navigation; keramogranit showed 4 products, gray color characteristic restricted results to 2 with applied label «Цвет: Серый».
- UI review findings resolved: date ranges now full-width; returning to applied/empty brand restores compatible choices; same-location reset clears draft and money validity. Pending collection lookups abort on navigation/reset and check current scope before applying.
- Final 2026-11-12: Client typecheck/build/format passed; 84 browser tests passed in 1.6 minutes including all catalog and shared consumer regressions. UI Design Guard integrated PASS, known preexisting image-placeholder contrast4.25 preserved. Final report: `docs/CATALOG_2026-11-12.md`.
