# Real Product Pages Implementation Plan

> **For agentic workers:** use superpowers:executing-plans for the task you own; independent backend/cart work may proceed in parallel. Do not create commits.

**Goal:** replace the approved standalone product preview with actual Nuxt product pages backed by Laravel, then remove the demo and its task-owned temporary artifacts.

**Architecture:** Laravel adds a separate public product projection and validated catalog filters without changing the current catalog projection. Nuxt SSR composes small product components using shared UI/tokens, canonical product URLs, and the existing server cart API. No database schema or product data changes.

**Tech Stack:** current Laravel/PostgreSQL, Nuxt4/Vue3/TypeScript, existing Inter/Lucide/UI primitives, Playwright, PHPUnit/PostgreSQL.

## Approved decisions and scope

- User approved the HTML layout and centered information tabs. Final update 2026-10-30: tabs use productExample.html's persistent active underline (1px, bottom0, center-origin scaleX, .35s); inactive hover does not show a line. Consultation, purchase quick links and the integer-only caption are removed.
- User explicitly chose integer sale units; quantity1..9999 and step1. On 2026-10-30 the owner confirmed quantities are always integers and excluded fractional quantities from further development.
- One product, one URL: /products/{slug}; category breadcrumb, separate brand and collection links.
- Public endpoint /api/v1/products/{slug} returns SKU/article, description, prices, unit, is_on_sale, in_stock, category, brand/logo, ordered public images, visible resolved attributes, collection, real group siblings, outgoing related/recommended.
- No exact inventory in public detail; stock stays authoritative in cart validation.
- Category/brand/collection links use /catalog query filters. A collection filter is scoped by brand; no new Collection entity.
- Cart connects existing API. Checkout/order-form implementation remains a separate task.
- Keep shell/callback/search/footer and approved tokens. No hardcoded SKU, business contacts, image data or demo success messages in production.
- Working in current checkout: prior dirty files are exclusively this demo and its task status; retain the demo until implementation verification.
- Do not modify production DB, run destructive migrations, create commits or publish anything.

## Task 1 — public product data (backend owner)

Files: public product query/controller/resources and feature tests; CatalogController/PublicCatalogQuery and a validated filter request; cart availability guard regression; docs/API.md and docs/openapi.json.

- [x] Add failing tests for404 inactive/category, ordered media, visible select labels, actual sibling identities, filtered ordered relations, no internal fields/exactstock, nullable data and catalog filter compatibility.
- [x] Implement GET/products/{slug} via Query -> thin Controller -> Resource; eager-load relations to avoidN+1.
- [x] Add optional category/brand/collection catalog filters before pagination; collection requiresbrand.
- [x] Cover and enforce existing cart documentation: reject off-sale/inactive-category products at server boundary.
- [x] Run focused tests, PostgreSQL query tests on isolated test DB, Pint, architecture, PHPStan, OpenAPI validation. Do not refresh the development DB.

Shared detail contract:
images[{id,url,alt,is_primary,sort_order}];
attributes[{id,slug,name,type,unit,value,display_value}];
collection{name,brand_slug}|null;
group{axes[{id,slug,name,type}],products[ProductCard + axis_values[{slug,value,display_value}]]}|null;
ProductCard{id,name,slug,price,old_price,unit,is_on_sale,in_stock,image_url,image_alt,category,brand}.
brand{id,name,slug,logo_url,logo_alt}|null; no stock_quantity.

## Task 2 — actual client cart (cart owner)

Files: app/features/cart/**; app/app.vue; shared/SiteHeader.vue and callback/StorefrontHeader.vue; e2e/cart.spec.ts and e2e/fixtures/cart-api.mjs. Do not edit shared mock-api.mjs or product files.

Interface: useCart() exposes addItem(productId:number,quantity:number):Promise<boolean>, busy,error,items,itemCount,open,loadCart,updateItem/removeItem. Cart UI in app host; header opens it. Item fields follow existing API and sale units.

- [x] Write failing browser/API fixture tests for actual add/edit/delete, errors, expired token, header counter and SSR isolation.
- [x] Implement token transport only in X-Cart-Token; never URL/body/logs/SSR payload. No eager creation of carts on server/page load.
- [x] Use shared UiDialog/UiButton/UiCloseButton, real server totals, integer units, loading/error/empty states; no mock checkout.
- [x] Export fixture handler for root to wire into shared test API, then run relevant tests/typecheck/format.

## Task 3 — product UI, catalog and SEO (root)

Files: pages/products/[slug].vue; features/product/{types,services,composables,components}; common unit formatter; CatalogListing/contentPages; shared UI additions only where necessary; e2e/product-pages.spec.ts and fixtures; client sitemap; docs/CLIENT_UI_KIT.md.

- [x] Add failing product E2E fixtures/tests for SSR data and canonical404/error behavior, real links/filters, gallery, tabs and integer cart use.
- [x] Implement service DTO mapping and SSR page with metadata/Product+BreadcrumbList structured data. All independent products retain their own canonical.
- [x] Transfer gallery, buying/variant controls, centered tabs, visible attributes, actual description/empty state, related and recommended sections using approved primitives.
- [x] Preserve aspect ratio for portrait/wide media; handle failed/missing media. Use actual dynamic brand/contact data.
- [x] Link catalog cards; preserve filters in pagination, display relevant context and avoid indexing arbitrary filter combinations.
- [x] Include public product URLs in sitemap without partial success on API failure.
- [x] Build/typecheck/format plus focused and existing client E2E; inspect real SKU1000002 route in browser.

## Task 4 — integration, independent review, cleanup (root)

- [x] Review backend/cart changes and contracts; run relevant combined checks.
- [x] Independent UI Design Guard: actual screenshots five widths, gallery, tabs, states, keyboard, reducedmotion and product/cart flow. Fix material findings.
- [x] Save a concise implementation report and update task status. TASK122 product pages and bounded cart integration done; checkout staysTODO.
- [x] Only after verification, remove outputs/product-card-1000002 and task-owned .tmp/product-html-work, scripts, snapshots, generated demo images referenced by this task where authorized/in workspace; stop only the known demo server process.
- [x] Preserve original Downloads reference and exampleSite.html. Do not delete unrelated .tmp content or other processes.
- [x] Inspect git diff/list/status and report remaining verification limits. No commit.

## Review focus

- A slug for inactive product/category must404, and off-sale cart writes must fail.
- Missing/failed media and nullable price/description must stay usable, not invent data.
- Same collection name across brands must not mix products.
- Token expiry/concurrent add and server validation must not silently duplicate cart writes.
- Integer quantity must be enforced consistently; decimal prototype behavior must not leak into API.

