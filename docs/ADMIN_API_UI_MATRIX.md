# Interim Audit — Backend API → Admin UI matrix (TASK-A016)

Audit date: 2026-09-10. Scope: implemented Phase 0–6 administrative API
operations in [`openapi.json`](openapi.json), their Laravel policies and the
Vue Admin SPA. Public storefront operations are outside this matrix.

## Result

The completed Phase 0–6 catalog, access-control, authentication and audit-log
capabilities have an Admin consumer. The two manager workflows are deliberately
not presented as complete: the order route is a placeholder and there is no
contacts route or frontend service. Their backend operations are not orphaned;
they are the planned foundation for the addressable high-priority follow-ups
`TASK-A018` and `TASK-A019` below.

`GET` detail endpoints and `PUT` aliases that do not have a direct SPA call are
not gaps. Current catalog and access-control editors open selected list data in
dialogs; their list projections contain the data required by the current UI.
The `PUT` endpoints are documented HTTP compatibility aliases, while the SPA
uses the canonical `PATCH` mutation. They remain contract-tested in OpenAPI.

## State convention

Catalog and employees screens implement loading, empty and in-context error
states; the audit log implements loading, empty and error table states.
Mutating catalog/access controls have a busy/disabled state and render the
resulting API error. Route guards redirect a user without the route's read
permission to the dashboard; action controls are additionally hidden for users
without the mutation permission. The backend remains authoritative and returns
the standard `403` envelope for forged or stale requests.

Roles and the permission catalogue have an error state but currently lack an
explicit initial loading state; roles also lack an explicit empty collection
state. These are UI-consistency/accessibility gaps, not missing business API
coverage, and are owned by **TASK-A017** (shared states/UI-kit normalization).

The table uses this shorthand:

| State label | Meaning |
| --- | --- |
| `standard` | Relevant loading, empty, error and mutation-busy handling is implemented for the screen. |
| `deferred` | No UI is present yet; the follow-up must implement all five states. |
| `not needed` | A compatibility/read endpoint has no standalone current interaction. |

## Implemented Admin mapping

| Backend operations | Permission | Screen and UI actions | State | Status |
| --- | --- | --- | --- | --- |
| `POST /admin/auth/login`; `GET`, `PATCH /admin/auth/me`; `POST /admin/auth/logout` | Active authenticated administrator; self-profile needs no RBAC permission | `/login` sign-in; application boot restores session; profile `/profile` edits own data; header logs out | standard | Mapped |
| `POST /admin/auth/forgot-password`; `POST /admin/auth/reset-password` | Guest; throttled by backend | `/forgot-password`, `/reset-password`; success confirmation and validation/API error messages | standard | Mapped |
| `GET /admin/users`; `GET /admin/users/roles`; `POST /admin/users`; `PATCH`, `DELETE /admin/users/{user}` | `admin-users.view` reads; `admin-users.manage` mutations | `/employees`: filterable/paginated table, create/edit dialog, role selector, destructive confirmation | standard | Mapped |
| `GET /admin/users/{user}`; `PUT /admin/users/{user}` | Same policy as the equivalent read/update operations | No separate current detail route; selected list record populates the dialog; `PUT` is compatibility-only | not needed | Mapped contract / no direct call |
| `GET /admin/roles`; `GET /admin/roles/permissions`; `POST /admin/roles`; `PATCH`, `DELETE /admin/roles/{role}` | `roles.view` reads; `roles.manage` mutations; backend also constrains assignable permissions | `/roles`: role cards, permission checklist, create/edit dialog and delete control (not for system roles) | error; initial loading and empty collection deferred to A017 | Mapped; state follow-up A017 |
| `GET /admin/roles/{role}`; `PUT /admin/roles/{role}` | Same policy as equivalent read/update | No separate detail route; list projection serves the dialog; `PUT` is compatibility-only | not needed | Mapped contract / no direct call |
| `GET /admin/permissions` | `permissions.view` | `/permissions`: searchable, module-filtered, read-only catalogue with assigned roles | error/empty; initial loading deferred to A017 | Mapped; state follow-up A017 |
| `GET /admin/permissions/{permission}` | `permissions.view` | No separate detail route; catalogue card exposes current required data | not needed | Mapped contract / no direct call |
| `GET /admin/audit-logs` | `audit-log.view` | `/audit-log`: filters, pageable audit table and sanitized detail summary | standard | Mapped |
| `GET /admin/audit-logs/{audit_log}` | `audit-log.view` | No separate drill-down; the list resource supplies the displayed detail summary | not needed | Mapped contract / no direct call |
| `GET /admin/categories/tree`; `POST /admin/categories`; `PATCH`, `DELETE /admin/categories/{category}`; `GET`, `PUT /admin/categories/{category}/attributes`; `GET`, `PUT /admin/categories/{category}/attribute-groups` | `catalog.manage` | `/categories`: tree, category editor, attribute/group assignment controls | standard | Mapped |
| `GET /admin/categories`; `GET /admin/categories/{category}`; `PUT /admin/categories/{category}` | `catalog.manage` | Tree is the current catalog navigation projection; no separate list/detail page; `PUT` is compatibility-only | not needed | Mapped contract / no direct call |
| `GET`, `POST /admin/brands`; `PATCH`, `DELETE /admin/brands/{brand}` | `catalog.manage` | `/brands`: paginated list, create/edit dialog and delete confirmation | standard | Mapped |
| `GET /admin/brands/{brand}`; `PUT /admin/brands/{brand}` | `catalog.manage` | Selected list item opens the editor; `PUT` is compatibility-only | not needed | Mapped contract / no direct call |
| `GET`, `POST /admin/attribute-groups`; `PATCH`, `DELETE /admin/attribute-groups/{attribute_group}` | `catalog.manage` | `/attribute-groups`: paginated list, create/edit dialog and delete confirmation | standard | Mapped |
| `GET /admin/attribute-groups/{attribute_group}`; `PUT /admin/attribute-groups/{attribute_group}` | `catalog.manage` | Selected list item opens the editor; `PUT` is compatibility-only | not needed | Mapped contract / no direct call |
| `GET`, `POST /admin/attributes`; `PATCH`, `DELETE /admin/attributes/{attribute}` | `catalog.manage` | `/attributes`: paginated list, typed attribute/options editor and delete confirmation | standard | Mapped |
| `GET /admin/attributes/{attribute}`; `PUT /admin/attributes/{attribute}` | `catalog.manage` | Selected list item opens the editor; `PUT` is compatibility-only | not needed | Mapped contract / no direct call |
| `GET`, `POST /admin/products`; `PATCH`, `DELETE /admin/products/{product}` | `catalog.manage` | `/products`: filters/sort/pagination, multi-step create/edit, copy, delete confirmation | standard | Mapped |
| `GET /admin/products/{product}`; `PUT /admin/products/{product}` | `catalog.manage` | List projection opens the multi-step editor; `PUT` is compatibility-only | not needed | Mapped contract / no direct call |
| `GET`, `PUT /admin/products/{product}/attributes`; `GET`, `POST`, `PATCH`, `DELETE /admin/products/{product}/images`; `GET /admin/products/{product}/relation-candidates`; `GET`, `PUT /admin/products/{product}/relations` | `catalog.manage` | `/products` editor steps: characteristics, gallery/primary image/order, related-product search and relations | standard | Mapped |
| `GET`, `POST /admin/product-groups`; `PATCH`, `DELETE /admin/product-groups/{product_group}` | `catalog.manage` | `/products` editor's group step: list/select, create/update and delete a group | standard | Mapped |
| `GET /admin/product-groups/{product_group}`; `PUT /admin/product-groups/{product_group}` | `catalog.manage` | Current editor uses group list projection; `PUT` is compatibility-only | not needed | Mapped contract / no direct call |
| `GET /admin/products/export`; `GET /admin/products/import-template`; `POST /admin/products/import`; `GET /admin/product-imports/{productImport}` and `/errors` | `imports.manage` (catalog management is also required where enforced by backend) | `/products` export control and `ProductImportDialog`: templates, upload, polling and error-report download | standard | Mapped |
| `GET /admin/products/price-status-template`; `POST /admin/products/price-status-import`; `GET /admin/product-price-status-imports/{productImport}` and `/errors` | `imports.manage` | `/products` `ProductPriceStatusImportDialog`: template, upload, polling and error-report download | standard | Mapped |
| `GET /admin/products/group-import-template`; `POST /admin/products/group-import`; `GET /admin/product-group-imports/{productImport}` and `/errors` | `imports.manage` plus `catalog.manage` | `/products` `ProductGroupImportDialog`: template, upload, polling and error-report download | standard | Mapped |
| `POST /admin/product-image-imports`; `GET /admin/product-image-imports/{productImageImport}` and `/errors` | `imports.manage` | `/products` `ProductImportDialog` image tab: ZIP upload, polling and error-report download | standard | Mapped |

## Deferred Phase 0–6 manager workflows

| Backend operations | Permission | Current UI state | Required follow-up |
| --- | --- | --- | --- |
| `GET /admin/orders`; `GET /admin/orders/{order}`; `GET /admin/order-statuses`; `PATCH /admin/orders/{order}/status`; `PATCH /admin/orders/{order}/payment`; `GET /admin/orders/{order}/status-history`; `GET`, `POST /admin/orders/{order}/comments` | `orders.view`, `orders.manage`, `payments.manage` according to action | `/orders`: paginated searchable list and detail workspace with protected contact/delivery snapshots, items, status/payment changes, history and comments. Sidebar and route require `orders.view`; mutation controls require their dedicated permissions. | standard | Mapped by TASK-A018 |
| `GET /admin/contact-assignees`; `GET /admin/contact-statuses`; `GET /admin/contact-requests`; `GET /admin/contact-requests/{contactRequest}`; `PATCH .../assignee`; `PATCH .../status`; `GET .../status-history`; `GET`, `POST .../comments` | `contacts.view`, `contacts.manage` according to action | `/contacts`: filtered list and detail workspace with assignment, status workflow, history and comments. Navigation/route require `contacts.view`; mutation controls and assignee catalogue require `contacts.manage`. | standard | Mapped by TASK-A019 |

## Intentionally future-domain permissions

The permission catalogue is correctly broader than Phase 0–6. The following
permissions have no implemented Phase 0–6 administrative endpoint and must not
be represented as a missing current API/UI mapping:

| Permission | Planned owner |
| --- | --- |
| `content.manage`, `media.manage`, `settings.manage` | Phase 7 (`TASK-090`–`TASK-096`) |
| `seo.manage` | Phase 8 (`TASK-100`–`TASK-106`) |
| `analytics.view` | Phase 9 (`TASK-110`–`TASK-114`) |

`/content` and `/settings` are visible placeholders that predate their modules.
Their eventual routes/navigation must be permission-gated by their respective
permissions; they are not authorization for future API calls today.

## Verification evidence

- The route-to-OpenAPI coverage gate from `TASK-A014` establishes the complete
  administrative operation inventory used above.
- Vue router, sidebar, services and views were inspected against that inventory.
- Laravel policy/controller authorization was checked against
  `PermissionSeeder` and the relevant policies, rather than inferred from SPA
  visibility.
