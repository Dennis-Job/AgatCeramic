# Product Group Attribute Roles Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans for integration; delegate independent backend and Excel work as authorized by AGENTS.md. No commits requested.

**Goal:** Сохранение трёх ролей характеристик единообразно в Admin и Excel.

**Architecture:** Существующие оси плюс новая связь индивидуальных характеристик; общий список вычисляется как дополнение. Самостоятельные товары и SKU сохраняются.

**Tech Stack:** Laravel/PostgreSQL, PhpSpreadsheet, Vue 3/TypeScript, Vitest/Playwright.

**Spec:** `docs/superpowers/specs/2026-11-02-product-group-attribute-roles-design.md`.

## Global Constraints

- Не менять исходные незакоммиченные изменения, палитру, правила целого количества и коммерческую идентичность SKU.
- Нет миграции значений: существующие роли сохраняются, PATCH omission отличается от explicit clear.
- Контракт, документация и статусы задачи обновляются вместе с кодом.

## Review Focus

- Редактирование индивидуальной упаковки не меняет соседей, включая Excel preflight/resume.
- Черновой выбор группы/ролей не обещает другую область сохранения характеристик.
- Одинаковые оси не мешают сохранению разных SKU; нет молчаливого выбора товара.
- Старый XLSX без новых колонок не сбрасывает индивидуальные роли; новый пустой список очищает их явно.
- Общие несовпадающие значения при смене роли отвергаются без потери данных.

## Tasks

- [x] 1. Backend: regression API tests RED; migration/relation/request/resource/group validation/shared synchronization; GREEN focused API tests and migration checks.
  Interfaces: `ProductGroup::individualAttributes()`, optional request `individual_attribute_ids`, response `individual_attributes`; bulk changes accept optional list.
- [x] 2. Excel: real workbook regression tests RED; append individual-role columns/dictionaries, reader/planner/resume payload and preflight; GREEN import/export suites.
  Interfaces: same role lists; old workbook omission preserves roles, new blank columns clears them.
- [x] 3. Admin: behavioral UI/composable tests RED; typed form and persisted-role helpers, single role selector per characteristic, explicit shared-impact copy; GREEN unit/E2E keyboard/responsive/axe and build/lint/format.
- [x] 4. Integration: OpenAPI/schema checks, API/DB/decision docs and task report; whole backend/admin suites, PostgreSQL and independent code/UI Design Guard review; final diff and no commit.

## Execution ledger

- Initial: existing uncommitted Client attribute grouping changes recorded; implementation stays in the user's running checkout, with non-overlapping agent ownership.
- Backend: core role regressions RED→GREEN; full final suite 717 tests / 7876 assertions, 4 SQLite skips, no failures. Pint 678 files PASS; PHPStan, architecture guard, composer validate/audit PASS.
- Excel/PostgreSQL: 55 tests / 501 assertions PASS on guarded `agatceramic_feature_test`; additive migration up/down/up PASS. Legacy/new real XLSX, resume, atomic errors, local decimal/multiselect and shared synchronization covered.
- Admin: 285 unit tests PASS; 85 relevant E2E PASS, including five widths, keyboard, axe, clone/group/import workflows. Build/lint/format PASS.
- Client: 2 Node regressions RED→GREEN; 14 E2E PASS including identical axes/names/SKU, SSR, navigation/cart and five widths. Typecheck/build/format PASS.
- Contract: 43 contract/public identity tests PASS; OpenAPI major migration compatibility policy PASS, v15.0 documents Admin roles and public group SKU.
- Review: independent code review and UI Design Guard accepted Admin/Client actual 320/768/1280 screenshots. Required duplicate-name finding fixed through SKU and regression tests; no deferred blocking findings.
- Local app: only additive role migration applied, queue graceful restart completed and worker running. Existing product values untouched. Final diff checked; no commit.
