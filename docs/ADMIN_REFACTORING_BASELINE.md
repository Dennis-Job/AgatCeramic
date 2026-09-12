# Baseline Admin перед рефакторингом

Статус: зафиксирован в `TASK-A023` 2026-09-11. Этот документ являлся
контрактом сохранения поведения для `TASK-A024`–`TASK-A030`. Он не меняет
публичный API, permissions или пользовательские сценарии.

Повторный аудит 2026-09-12 отозвал финальную приёмку `TASK-A030`: выявлены blocking findings по
mobile sidebar, цветовому контрасту/design tokens, завершённости products/auth migration и
устойчивости acceptance suite. Исправления вынесены в `TASK-A031`–`TASK-A035`; после них
`TASK-A030` проходит повторную приёмку. Переход к Phase 7 заблокирован.

Документ ниже сохраняется как исторический baseline и объясняет исходные пути/контракты.
Актуальные правила находятся в `frontend/admin/AGENTS.md` и
`frontend/admin/src/components/shared/UI_KIT.md`; переходные `Base*`, layout, state/pagination и
domain-service re-export adapters удалены после перевода consumers на source-of-truth слои.

## Исторический результат первой приёмки TASK-A030

На 2026-09-12 первоначально были зафиксированы Admin production build, 30 unit-тестов и 136
production E2E/visual/axe/responsive тестов локально и в Linux Compose. Проверены обязательные маршруты,
loading/empty/error состояния, destructive dialog и ширины 320, 640, 768, 1024
и 1280 px. Повторный аудит показал, что этот набор evidence был недостаточен для финальной приёмки.

Route views не содержат raw HTTP, доменных DTO, крупных форм или копий UI
primitives. Временные `Base*`, layout, state/pagination и service re-export
adapters удалены после миграции consumers. API, permissions, backend и OpenAPI
в рамках первой приёмки не изменялись. Phase 7 остаётся заблокирована до повторной приёмки.

## Воспроизводимая visual и accessibility-проверка

`frontend/admin/e2e/adminBaseline.spec.ts` изолирует SPA от backend через
browser-level fixtures. Он создаёт и сравнивает visual snapshots следующих
маршрутов: `/login`, `/forgot-password`, `/reset-password`, `/`, `/profile`,
`/products`, `/categories`, `/brands`, `/attribute-groups`, `/attributes`,
`/employees`, `/roles`, `/permissions`, `/audit-log`, `/orders`, `/contacts`,
`/content`, `/settings`. Для всех маршрутов проверяется отсутствие
горизонтального overflow на 320, 640, 768, 1024 и 1280 px. Для data-driven
экранов (`products`, Catalog dictionaries, employees, RBAC, audit, orders и
contacts) отдельно фиксируются empty и error screenshots; error также обязан
содержать `role=alert`. Для тех же экранов baseline задерживает API fixture и
фиксирует видимое `role=status` loading-состояние. Отдельный snapshot закрывает
доступный destructive confirmation dialog на `/roles`.

Первое создание либо намеренное обновление snapshot выполняется только после
визуального review:

```powershell
Set-Location frontend/admin
npm run build
npx playwright test e2e/adminBaseline.spec.ts --update-snapshots
```

Обычная защита baseline: `npm run test:e2e`. Snapshot-артефакты располагаются
рядом со spec в `e2e/adminBaseline.spec.ts-snapshots/`; изменение изображения
равносильно изменению UI и должно быть обосновано в задаче.

Существующие E2E дополняют snapshot-базу интерактивными состояниями: Catalog
проверяет loading/empty/error/forbidden и destructive-диалоги, imports —
loading/error/result dialogs, Orders — detail и permission-safe controls.
Все эти проверки запускаются той же командой. Axe блокирует все `serious` и
`critical`, кроме узко описанного временного исключения ниже; `color-contrast` исключён осознанно согласно
[`UI_DESIGN_REVIEW.md`](UI_DESIGN_REVIEW.md) и проверяется визуально.

Исправление в `TASK-A024`: `BaseDatePicker` больше не устанавливает
`aria-expanded` на text input, поэтому baseline не допускает serious/critical
исключений Axe.

Для каждого изменённого UI также вручную проверить ширины 320, 640, 768, 1024
и 1280 px, keyboard focus/Escape и длинные русские строки. Это не заменяется
snapshot-тестом на desktop.

## Текущая карта ответственности

| Текущий путь | Назначение | Владелец feature | Целевой слой при переносе |
| --- | --- | --- | --- |
| `views/LoginView.vue`, `ForgotPasswordView.vue`, `ResetPasswordView.vue` | guest auth forms | auth | `pages/auth` + `features/auth` |
| `views/DashboardView.vue` | статический dashboard shell | dashboard | `pages/dashboard` |
| `views/ProfileView.vue` | профиль текущего сотрудника | auth/profile | `pages/profile` + `features/profile` |
| `views/ProductsView.vue`, `AttributeValueField.vue`, `EditorSteps.vue`, `Product*ImportDialog.vue` | list/editor/import/groups/products | products | `pages/products` + `features/products` |
| `views/CategoriesView.vue` | tree и editor категорий | categories | `pages/categories` + `features/categories` |
| `views/BrandsView.vue` | list/editor брендов | brands | `pages/brands` + `features/brands` |
| `views/AttributeGroupsView.vue`, `AttributesView.vue` | справочники характеристик | attributes | `pages/attributes` + `features/attributes` |
| `views/EmployeesView.vue` | сотрудники | employees | `pages/employees` + `features/employees` |
| `views/RolesView.vue`, `PermissionsView.vue` | RBAC catalogue | access-control | `pages/access-control` + `features/access-control` |
| `views/AuditLogView.vue` | read-only audit log | audit | `pages/audit-log` + `features/audit-log` |
| `views/OrdersView.vue` | order list/detail/workflow | orders | `pages/orders` + `features/orders` |
| `views/ContactsView.vue` | contact list/detail/workflow | contacts | `pages/contacts` + `features/contacts` |
| `views/PlaceholderView.vue` | temporary content/settings route | content/settings | `pages/content`, `pages/settings` until Phase 7 |
| `layouts/AppLayout.vue`, `components/AppSidebar.vue` | authenticated application shell | shared layout | `layouts` and `layouts/components` |
| `components/Base*.vue` | transition UI primitives | UI-kit | `components/ui` through adapters |
| `components/CollectionLoadingState.vue`, `BaseEmptyState.vue`, `PaginationControls.vue` | cross-feature states/navigation | shared | `components/shared` |
| `composables/usePaginatedCollection.ts` | reusable page collection state | shared | `composables` |
| `stores/auth.ts` | session/current user/permissions | auth | `app/stores` |
| `services/auth.ts` | http client + auth API | shared/auth | `services/http` and `features/auth/services` |
| `services/{products,categories,brands,attributes,attributeGroups,productAttributes,productGroups,productImages,productImageImports,productRelations}.ts` | catalog API contracts | catalog features | `features/*/services` |
| `services/{employees,roles,permissions,audit-logs,orders,contacts}.ts` | domain API contracts | named feature | matching `features/*/services` |
| `services/pagination.ts`, `utils/*`, `constants/countries.ts` | generic helpers/data | shared | `services`, `utils`, `constants` |
| `router/index.ts`, `App.vue`, `main.ts` | application composition | app | `app/router`, `app/App.vue`, `app/main.ts` |
| `style.css` | tokens, global type/layout utilities | design system | `styles/` split only after token contract is covered |

The table declares ownership, not permission to bulk-move a directory. A task
migrates one responsibility at a time and retains the existing route and API
contract.

## Переходный публичный контракт Base-компонентов

До миграции всех потребителей `Base*` остаются adapters. Новый `Ui*` может
изменить внутреннюю реализацию, но не указанные props, emits, slots, focus,
keyboard или ARIA behaviour без отдельного migration plan.

| Компонент | Сохранить props | Сохранить emits / contract |
| --- | --- | --- |
| `BaseAlert` | `tone?: error\|success`, `live?: assertive\|polite` | default slot; `alert`/`status` live region |
| `BaseCheckbox` | `modelValue?`, `value?`, `checked?`, `mode?: boolean`, `accessibleName?` | `update:modelValue`, `update:checked` |
| `BaseConfirmDialog` | `open`, `title`, `description`, `confirmLabel?`, `busy?`, `error?` | `close`, `confirm`; busy blocks close |
| `BaseDatePicker` | `modelValue`, `placeholder?`, `accessibleName?` | `update:modelValue`; date dialog, Escape and focus return |
| `BaseDialog` | `open`, `labelledby`, `describedby?`, `closeDisabled?`, `suspended?`, `overlayClass?`, `panelClass?` | default slot, `close`; focus trap, Escape/backdrop and opener focus restoration |
| `BaseEmptyState` | `label` | `status` live region |
| `BaseInput` | `modelValue?`, `type?`, `searchable?`, native attrs | `update:modelValue`; forwards native attrs and clear affordance |
| `BaseRadio` | `modelValue`, `value`, `name` | `update:modelValue`; default slot |
| `BaseSelect` | `modelValue`, `options`, `placeholder?`, `accessibleName`, `searchable?`, `searchPlaceholder?`, `clearable?`, `teleportMenu?` | `update:modelValue`, `change`; keyboard navigation and close/focus behaviour |
| `BaseTextarea` | `modelValue?`, native attrs | `update:modelValue`; forwards native attrs |

`options` in `BaseSelect` is the existing `{ label, value }` public shape.
The exact CSS classes are not API; accessible names, labels and interaction
semantics are. Other named components are domain/shared components, not UI
primitives, and are migrated only with their feature.

## Правила каждого инкремента

1. Сначала выберите одну строку карты и сохраните её route, permission, API
   service contract and visible states.
2. Search for an existing `Ui*`/shared component before adding markup. Use a
   `Base*` adapter while any legacy consumer remains.
3. Page becomes composition only; feature owns domain UI, composable owns local
   state, service owns endpoint knowledge, and global auth stays in Pinia.
4. Do not change OpenAPI, backend behavior, role/permission codes or route URLs
   as part of a structural migration.
5. Run unit tests, build, the relevant E2E plus full baseline when shared UI,
   layout, tokens or adapters change. Complete the independent UI Design Guard
   review required by `UI_DESIGN_REVIEW.md`.

## Исторически отложено в TASK-A023

No `pages/`, `features/`, `components/ui/`, `components/shared/` or `styles/`
directory is created by this task. The target names above are a dependency map,
not a second source of implementation. Content and settings remain placeholders
until their Phase 7 product work.
