# Navigation Submenus Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking. Selective parallel work follows the repository AGENTS.md; no commits without owner instruction.

**Goal:** Admin-managed optional one-level navigation submenus with disclosure-only parents.

**Architecture:** Extend header.navigation with a union of existing {label,to} links and {label,children:SiteLink[]} groups. Keep JSON storage and publication workflow. Separate Admin navigation editing from chrome sections; Client uses a shared navigation component and composable for menu lifecycle.

**Tech Stack:** Laravel/PHP, Vue 3/TypeScript, Nuxt 4, Playwright, Vitest, PHPUnit.

**Spec:** docs/superpowers/specs/2026-10-28-navigation-submenus-design.md (approved).

## Global Constraints

- One submenu level, 1–16 parents, 1–16 children per group, labels ≤255 and URLs ≤2048.
- Parent with children only expands; no parent URL. Preserve old links and footer types.
- Use existing SafeSiteUrl and frontend URL filtering. No automatic real content publication.
- Preserve palette, type, shared UI primitives, permissions, audit and draft/publish boundaries.
- Work in supplied checkout on codex/navigation-submenus; no commits, new dependencies or live database mutations.

## Review Focus

- Open dropdown must escape the clipped/scrolled header, including short viewports and last-column placement.
- Switching Admin mode back and forth must preserve current unsaved URLs and children without leaking inactive fields into saves.
- Search must include children, expand group-name matches, deduplicate by URL and keep old results valid.
- Escape must close child disclosure before the modal panel, without losing focus or scroll lock.
- Legacy read/write and genuine saved preview must preserve groups through every appearance projection and validate nested URLs.

## Task 1: API and contract (backend owner)

**Files:** backend/app/Http/Requests/Api/V1/Admin/UpdateHomePageRequest.php; backend/app/Http/Requests/Api/V1/Admin/Concerns/ValidatesPageContent.php; relevant appearance queries/resources; backend/tests/Feature/Api/NavigationSubmenusTest.php; docs/openapi.json; docs/OPENAPI_MIGRATION_PLAN.md.

**Interfaces:** Consumes existing header.navigation; produces unchanged links or groups `{label:string,children:Array<{label:string,to:string}>}` in site-appearance/home-page/page site_layout/preview. No footer extension.

- [x] Add regression tests for mixed menu roundtrip, draft isolation/publication and permissions, legacy links, conflicting fields/empty groups/too deep/unsafe URLs/limits and page preview.
- [x] Run new tests first and confirm unsupported groups fail with 422.
- [x] Implement shared one-level validation with existing request and projection architecture; preserve storage/publication.
- [x] Extend OpenAPI input/output schemas and migration compatibility notes.
- [x] Run focused and full backend tests, formatting/static analysis, architecture and API contract checks; report actual outputs.

## Task 2: Admin editor (Admin owner)

**Files:** frontend/admin/src/features/appearance/types/appearance.types.ts; components/SiteChromeEditor.vue; new components/NavigationEditor.vue; any focused editor composable/types; frontend/admin tests and e2e/appearance.spec.ts.

**Interfaces:** SiteLink remains `{label:string,to:string}`; new `SiteNavigationItem = SiteLink | {label:string,children:SiteLink[]}`. Header uses SiteNavigationItem[]. API submits only active union fields. Existing parent section save/publish owns lifecycle.

- [x] Write tests for disclosure mode editor, child add/reorder/delete, mode reversal without lost input, disabled behavior and active-only payload.
- [x] Run tests and observe missing toggle/children behavior fail.
- [x] Use existing UiSwitch/UiInput/UiField/UiButton and feature components for editor; do not extend footer types.
- [x] Add save/reload/publish/error responsive E2E with exact API contract and screenshot evidence on five widths.
- [x] Run Admin lint/format/unit/build and focused E2E. Coordinate real Nuxt preview separately with root.

## Task 3: Client navigation (root)

**Files:** frontend/client/app/types/homePage.ts; app/services/homePage.ts; app/components/shared/SiteHeader.vue; new SiteNavigation.vue and composables/useNavigationDisclosure.ts as justified; e2e/mock-api.mjs; new e2e/navigation-submenus.spec.ts.

**Interfaces:** Header's SiteNavigationItem union matches Task 2, mapped defensively from API. Shared SiteNavigation handles desktop and panel variants. Header owns panels/search, child navigation component owns single open group and keyboard lifecycle.

- [x] Add mock mixed/oversize/unsafe data and E2E for SSR, parent-only expansion, child navigation, search, keyboard/Escape/outside/panel close, responsive and oversize header positioning.
- [x] Build/run focused tests before code; confirm submenu disclosure missing.
- [x] Implement mapping/union and child search flattening/deduplication, shared component and disclosure behavior; keep links rendered in SSR and hidden when closed.
- [x] Integrate panel Escape ordering, close on route/panel change and responsive viewport lifecycle; retain existing overlays/animation conventions.
- [x] Run typecheck/format/build, focused then full Client E2E, and actual saved-preview/iframe checks as relevant.

## Task 4: Integration, review and documentation (root)

**Files:** docs/CLIENT_UI_KIT.md; docs/CONTENT_WORKSPACE_UX.md; docs/API.md when applicable; tasks/IN_PROGRESS.md and tasks/DONE.md; docs/NAVIGATION_SUBMENUS_2026-10-28.md.

**Interfaces:** Accept completed backend/Admin/Client changes; preserve one union and existing endpoints throughout.

- [x] Independently inspect each delegated diff, run combined contract checks and genuine Nuxt preview.
- [x] Obtain independent code/UI Design Guard review against spec and screenshots; address blocking findings and retest affected paths.
- [x] Document behavior, exact checks and any limitations; update task status and plan checkboxes.
- [x] Verify git diff --check and changed files. Deliver concise outcome, usage location and tests. No commit/push or content publication.

## Execution notes

Status: complete 2026-10-28. Detailed results and changed files:
`docs/NAVIGATION_SUBMENUS_2026-10-28.md`.

Verification: Backend 617 passed/1 existing SQLite skip; focused 33/335 assertions.
Admin 268 units, 26 appearance E2E, lint/format/build passed. Client 74 E2E,
typecheck/format/build passed. Real Admin/Nuxt iframe 4 repeated checks and
development iframe 2 checks passed. Independent UI Design Guard accepted after
fixing notification-dismiss loss of Retry, verified by manual+timeout regressions.
No unresolved actionable findings. No commits or live data publication.

Decisions recorded during execution: page site_layout remains read-only through
page editing, using existing appearance writers; footer receives a flat projection
of navigation children; query links match explicit query values for active state.
All choices preserve the approved scope and existing publication boundaries.

- Approved design and owner instruction authorize implementation; do not add another approval gate for this plan.
- Selectively delegate Tasks 1 and 2 with non-overlapping edit scopes; root implements Task 3 and integration.
- Use the current configured checkout and installed dependencies; branch creation isolates the intended Git history without copying local runtimes or connection files.
