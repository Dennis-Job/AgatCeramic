# Catalog Brand Alphabet Implementation Plan

> **For agentic workers:** use test-driven-development; selective parallel implementation of Backend, Admin, and Client with one independent final UI/code review. Do not commit.

**Goal:** Алфавитное меню выбранных брендов под категориями каталога.
**Architecture:** Brand boolean → validated admin CRUD → global public `brand_navigation` → SSR catalog feature component. Existing product filtering remains the destination.
**Tech Stack:** Laravel/PostgreSQL, Vue 3 Admin, Nuxt 4 Client, Playwright.
**Spec:** `docs/superpowers/specs/2026-11-17-catalog-brand-alphabet-design.md`

## Global Constraints

- `show_in_catalog_menu` defaults to false; only active opted-in brands published.
- `brand_navigation: { id: number; name: string; slug: string }[]` independent of filters/pagination.
- Nonempty groups only: `0–9`, Latin initials, `РУС`; full container width, four desktop columns.
- Existing UI tokens, SSR, preview parity, permissions and audit retained. No commits.

## Review Focus

- Hover → click must not immediately close a freshly opened group.
- Long names and many entries must not overflow or become unreachable in a short viewport.
- Closing/switching groups must not leave invisible focusable links.
- Menu must not be clipped by sticky category overflow or cover sticky header.
- A renamed/deactivated brand must disappear or move groups after fresh data arrives.

### Task 1: Backend (independent)

Files: Brand model, new migration, Store/UpdateBrandRequest, BrandResource,
PublicCatalogQuery, public Brand navigation Resource, CatalogController,
backend feature tests, docs/API.md, docs/openapi.json.

Produces: validated boolean admin field; global `brand_navigation` public metadata.

- [x] Write and run failing tests for CRUD/default/PATCH/validation/permissions and public selection independent of scope/page.
- [x] Implement minimal schema, casts, request/resource/query/controller changes.
- [x] Run relevant feature/contract tests, formatting/static analysis and migration compatibility checks.
- [x] Update API documentation and report exact verification evidence.

### Task 2: Admin (independent)

Files: brand.types.ts, brand.schema.ts, useBrandForm.ts, BrandFormDialog.vue,
brand form unit/E2E tests and feature documentation.

Consumes: `show_in_catalog_menu: boolean` in Brand and create/update payload.

- [x] Write/run a failing behavior test for default off and create/edit persistence.
- [x] Use existing switch primitive with label «Показывать в алфавитном меню каталога».
- [x] Run unit/build/format and relevant E2E with safe fixtures; report evidence.

### Task 3: Client (primary agent)

Files: catalog types/service/PageBody, new CatalogBrandNavigation.vue and
grouping utility/composable as needed; catalog E2E fixture and new test.

Consumes: `brand_navigation` list; tolerates missing field as empty for old APIs.

- [x] Add E2E scenarios and failing checks for nonempty grouping/SSR, hover/click/keyboard/touch, links and width/columns.
- [x] Implement SSR groups and panel using existing tokens/overlay motion. Links reset prior filters and go to selected brand.
- [x] Verify removal/renaming behavior, outside/focus/Escape close, no hidden focus targets, long names/short viewport, reduced motion.
- [x] Run Client typecheck/build/format and relevant catalog regressions; capture five viewport screenshots.

### Task 4: Integration and independent review

- [x] Review combined diff/API contracts, run required checks for affected areas.
- [x] Independent UI Design Guard/code review; address blocking findings and rerun appropriate checks.
- [x] Update CLIENT_UI_KIT and task status with actual results/limitations. Verify git diff.

## Decisions and evidence

- Continue in the user's current workspace: no unrelated initial changes except the design draft; no commits or destructive operations.
- Backend/Admin changes may proceed in parallel because the field name and payload contract above are fixed. Client receives the public list and does not duplicate publication rules.
- User's latest message is the clarification of the proposed behavior; implement it without another approval round.

## Follow-up: inline brand list switch

- [x] User requested the existing menu setting directly in each `/brands` row.
- [x] TDD: missing inline method/control, failed-save handling and stale list/deletion reload red → green.
- [x] Reuse UiSwitch; PATCH only the flag via existing transport; keep previous value on failure, per-row pending/read-only, search/page preservation.
- [x] Restore initiating focus after native disabled only when user has not moved; browser failure reproduced then fixed and no-focus-steal test added.
- [x] `npm run check`: lint/format/build and 327 unit tests PASS; 24 brand E2E PASS; five width screenshots/axe and independent UI Design Guard PASS.
- [x] Inspect and accept intended populated `/brands` Windows-only visual baseline change; loading/error/empty and long-list checks passed.
- [x] Check/update the equivalent Linux baseline in isolated Compose test container; Linux build +17 tests PASS. Post-update normal Windows baseline/state/long-list 13 tests PASS. Both native platform PNGs inspected/accepted by independent UI Guard; git diff checked.
