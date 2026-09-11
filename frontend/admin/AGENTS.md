I want to establish permanent architecture rules for the Vue.js Admin application in the AgatCeramic project.

Do NOT refactor any application code yet.

First inspect the repository and determine the actual root directory of the Vue Admin application.

Then create or update an `AGENTS.md` file inside that Admin application directory so these instructions apply specifically to the Vue Admin code.

Preserve any existing important project instructions.

Add the following architecture rules.

# AgatCeramic Admin Architecture

## Primary goal

The Admin application must be easy to maintain, redesign, and extend.

Large Vue pages must not contain UI implementation, API access, validation, business logic, and page composition all in one file.

The architecture must clearly separate:

- pages
- layouts
- domain features
- reusable UI components
- composables
- API/services
- validation
- types
- global styles
- design tokens

The Admin application should use a pragmatic feature-based architecture combined with a reusable UI Kit and design tokens.

Do not introduce unnecessary enterprise complexity or abstractions.

---

# Dependency direction

The preferred dependency direction is:

Page
→ Feature
→ Shared UI component
→ Design tokens

For data/business logic:

Feature
→ Composable
→ Service/API
→ Backend API

Dependencies should generally flow downward.

Shared UI components must never depend on domain features such as products, categories, orders, users, etc.

---

# Pages

Pages are route-level composition components.

Examples:

- `ProductsPage.vue`
- `ProductCreatePage.vue`
- `ProductEditPage.vue`
- `OrdersPage.vue`

Pages should remain thin.

A page may:

- read route parameters
- load page-level data
- compose feature components
- select a layout
- display loading/error states

A page should NOT:

- implement reusable buttons, inputs, selects, checkboxes, switches, modals, tables, etc.
- contain large forms
- contain direct HTTP requests
- contain extensive business logic
- contain large amounts of CSS
- define API DTOs or domain types

Prefer pages that mainly compose other components.

---

# Features

Domain-specific functionality should live inside feature modules.

Examples:

```text
features/
├── products/
├── categories/
├── orders/
├── users/
└── auth/
```

A typical feature may contain:

```text
features/products/
├── components/
├── composables/
├── services/
├── stores/
├── types/
└── validation/
```

Domain-specific components belong to their feature.

Examples:

```text
ProductForm.vue
ProductGeneralSection.vue
ProductPriceSection.vue
ProductImagesSection.vue
ProductCategorySelect.vue
ProductStatusToggle.vue
```

Do not put Product-specific components into the generic UI library.

---

# Large forms

Large forms must be composed from logical sections instead of being implemented as one massive Vue file.

For example:

```text
ProductForm
├── ProductGeneralSection
├── ProductPriceSection
├── ProductCategorySection
├── ProductImagesSection
└── ProductSeoSection
```

`ProductForm.vue` should orchestrate these sections rather than contain their complete implementation.

Form state and submit logic should normally be handled by an appropriate composable such as:

```text
useProductForm.ts
```

Validation should be kept outside presentation components when practical.

---

# Shared UI Kit

All reusable primitive UI elements must be implemented in a central UI component layer.

Preferred location:

```text
components/ui/
```

Examples:

```text
components/ui/
├── button/
│   └── UiButton.vue
├── input/
│   └── UiInput.vue
├── checkbox/
│   └── UiCheckbox.vue
├── select/
│   └── UiSelect.vue
├── textarea/
│   └── UiTextarea.vue
├── switch/
│   └── UiSwitch.vue
├── modal/
│   └── UiModal.vue
├── table/
│   └── UiTable.vue
├── badge/
│   └── UiBadge.vue
├── card/
│   └── UiCard.vue
└── icon/
    └── UiIcon.vue
```

Before creating a new UI element, ALWAYS check whether an appropriate `Ui*` component already exists.

Do not create custom versions of an existing primitive inside pages or features.

For example, prefer:

```vue
<UiButton variant="primary">
    Save
</UiButton>
```

instead of implementing another styled `<button>`.

Likewise prefer:

```vue
<UiInput />
<UiCheckbox />
<UiSelect />
<UiTextarea />
<UiSwitch />
```

instead of styling native controls separately throughout the application.

Native elements may still exist inside the implementation of the corresponding UI primitive.

The goal is to make global visual redesigns possible by editing a small number of UI components.

---

# Shared components

Reusable components that are more complex than UI primitives but are not tied to one business domain should live in a shared components layer.

Examples:

```text
components/shared/
├── PageHeader.vue
├── EmptyState.vue
├── LoadingState.vue
├── ErrorState.vue
├── Pagination.vue
└── ConfirmDialog.vue
```

Do not confuse shared application components with primitive UI controls.

---

# Layouts

Application shell components should be isolated from pages.

Example:

```text
layouts/
├── AdminLayout.vue
├── AuthLayout.vue
└── components/
    ├── AdminSidebar.vue
    ├── AdminHeader.vue
    ├── AdminBreadcrumbs.vue
    └── AdminUserMenu.vue
```

Pages should not duplicate sidebar/header/layout markup.

---

# API and services

Vue presentation components must not make raw HTTP requests directly unless there is a very strong reason.

Do NOT spread code such as:

```javascript
axios.get(...)
axios.post(...)
fetch(...)
```

across Vue pages and components.

HTTP communication should go through dedicated services/API modules.

Example:

```text
ProductForm.vue
→ useProductForm.ts
→ productApi.ts
→ httpClient.ts
→ Laravel API
```

A service should encapsulate endpoint knowledge.

Example:

```text
productApi.get(...)
productApi.create(...)
productApi.update(...)
productApi.delete(...)
```

Vue components should not need to know backend endpoint URLs.

---

# Composables

Reusable stateful Vue logic should be extracted into composables.

Examples:

```text
useProduct.ts
useProductForm.ts
usePagination.ts
useModal.ts
useNotifications.ts
```

Use composables for local feature behavior and reusable Vue logic.

Do not automatically move everything into Pinia.

---

# Pinia

Use Pinia primarily for truly shared/global application state.

Good candidates include:

- authenticated user
- authentication state
- global Admin settings
- state shared by multiple unrelated routes/components

Local form state normally belongs in the form or its composable.

Do not create giant stores containing all logic for an entire feature.

---

# Types

TypeScript types and API DTO definitions must not be scattered throughout Vue files.

Prefer dedicated files such as:

```text
features/products/types/product.types.ts
types/api.types.ts
types/common.types.ts
```

Use clear domain-specific names.

---

# Validation

Validation logic should be separated from the visual implementation of controls.

Prefer feature-specific validation modules, for example:

```text
features/products/validation/product.schema.ts
```

UI primitives must remain generic and must not contain Product/Order/etc. business validation.

---

# Design tokens

Visual constants should be centralized so that the Admin panel can be redesigned without editing every page.

Maintain design tokens for values such as:

- colors
- typography
- border radius
- spacing
- shadows
- control heights
- borders
- transitions

For example:

```css
:root {
    --admin-color-primary: #171717;
    --admin-color-primary-hover: #292929;

    --admin-color-background: #f7f7f7;
    --admin-color-surface: #ffffff;

    --admin-color-text: #171717;
    --admin-color-text-muted: #737373;

    --admin-color-border: #e5e5e5;

    --admin-radius-sm: 6px;
    --admin-radius-md: 10px;
    --admin-radius-lg: 14px;

    --admin-control-height-sm: 32px;
    --admin-control-height-md: 40px;
    --admin-control-height-lg: 48px;

    --admin-spacing-xs: 4px;
    --admin-spacing-sm: 8px;
    --admin-spacing-md: 16px;
    --admin-spacing-lg: 24px;
}
```

Do not scatter arbitrary repeated visual values throughout feature components when an appropriate design token should exist.

---

# Styling rules

Global design decisions should be controlled through:

```text
styles/
├── index.css
├── reset.css
├── tokens.css
├── typography.css
└── utilities.css
```

Component-specific styles may stay within their components.

Avoid page-specific copies of styles that already belong to the UI Kit.

The goal is that changes such as:

- button radius
- input height
- checkbox appearance
- form border
- primary color
- typography
- surface colors

can be made centrally.

---

# Component responsibilities

Follow the Single Responsibility Principle.

A component should have one understandable responsibility.

Do not split components purely to reduce line count.

Extract a component when it represents:

- an independent UI element
- a reusable UI pattern
- a logical form section
- a domain-specific unit
- an independently understandable responsibility

Do not create meaningless one-line wrapper components.

---

# Architecture before code

Whenever modifying Admin UI:

1. Inspect the existing architecture first.
2. Look for an existing reusable component.
3. Look for an existing composable/service/type.
4. Reuse existing abstractions when appropriate.
5. Only create a new abstraction when there is a clear responsibility for it.
6. Keep dependency direction intact.

Do not bypass the architecture simply because writing inline code is faster.

---

# Refactoring strategy

When refactoring existing Admin code, do it incrementally.

Do not rewrite the entire Admin application at once.

For each large page:

1. understand its current behavior
2. identify UI primitives
3. identify shared components
4. identify feature-specific components
5. identify business/state logic
6. identify API calls
7. identify validation
8. identify types
9. move each responsibility into the appropriate layer
10. preserve existing behavior
11. run the appropriate tests/build/lint checks

Refactoring must preserve application behavior unless the task explicitly requests a behavior change.

---

# Preferred target structure

Adapt the structure to the existing repository instead of blindly creating directories, but use approximately this architecture:

```text
src/
├── app/
│   ├── App.vue
│   ├── router/
│   ├── stores/
│   └── plugins/
│
├── layouts/
│   ├── AdminLayout.vue
│   ├── AuthLayout.vue
│   └── components/
│
├── pages/
│   ├── dashboard/
│   ├── products/
│   ├── categories/
│   ├── orders/
│   ├── users/
│   └── settings/
│
├── features/
│   ├── products/
│   │   ├── components/
│   │   ├── composables/
│   │   ├── services/
│   │   ├── stores/
│   │   ├── types/
│   │   └── validation/
│   ├── orders/
│   ├── categories/
│   └── auth/
│
├── components/
│   ├── ui/
│   └── shared/
│
├── composables/
├── services/
├── styles/
├── types/
└── utils/
```

Do not reorganize existing code only to match this tree exactly.

The responsibility boundaries are more important than directory names.

Respect existing Vue, Laravel, build-tool, routing, TypeScript, linting, formatting and testing conventions discovered in the repository.

---

# SOLID and clean code

Follow SOLID principles where applicable.

Prefer:

- small clear responsibilities
- explicit dependencies
- understandable names
- low coupling
- high cohesion
- predictable file locations
- framework conventions

Avoid:

- god components
- god stores
- duplicated UI
- duplicated API logic
- premature abstraction
- unnecessary indirection
- clever code that reduces readability

Follow current official Vue conventions and the conventions of the project's installed libraries.

---

## Source of truth for Admin UI

The current Admin UI Kit and design tokens are the source of truth for visual implementation.

Do not treat the original TailAdmin template as the permanent visual specification.

TailAdmin may be used as historical implementation context only.

When the Admin design system evolves, existing `Ui*` components and design tokens take precedence over TailAdmin defaults and examples.

---

# Important rule for AI-generated UI

When asked to implement or modify Admin UI, never immediately create new raw UI markup.

First search the project for:

- `UiButton`
- `UiInput`
- `UiCheckbox`
- `UiSelect`
- `UiTextarea`
- `UiSwitch`
- `UiModal`
- `UiTable`
- other existing `Ui*` components

Reuse or extend the existing design system whenever possible.

If a requested visual change should affect all controls of one type, modify the shared UI component or design token instead of editing every page separately.

---

After updating `AGENTS.md`, report:

1. where the file is located;
2. what scope it applies to;
3. whether another `AGENTS.md` already existed;
4. whether any existing instructions conflicted with these rules;
5. the current detected Vue Admin root directory.

Do not modify any application source code as part of this task.