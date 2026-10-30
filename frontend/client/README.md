# AgatCeramic Client

Nuxt 4 storefront with SSR enabled by default. This project is the public, SEO-first frontend; business data is supplied by the Laravel API.

## Local commands

```powershell
npm install
npm run dev -- --host 127.0.0.1
npm run typecheck
npm run format:check
npm run build
```

The storefront follows `docs/CLIENT_UI_KIT.md` and fetches
managed content from Laravel `GET /api/v1/home-page` during SSR. Text, section images, the selected
published slider and homepage SEO are edited in Admin → «Контент» → «Страницы» → «Главная».
Banner content and order are edited in the page's slider block. Saving changes updates a
draft; explicit page publication updates its public snapshot. The shared header, navigation and
footer use `GET /api/v1/site-appearance` in the persistent Nuxt layout and are edited/published
separately in «Контент» → «Общее оформление». They remain available when the homepage is unpublished.
Up to four navigation items appear in the desktop header with wrapping for long labels; longer
menus use the existing drawer with every configured link. Publication of a page preserves the
published appearance, and publication of appearance preserves all page drafts and snapshots.
The homepage renders the ordered typed `blocks` instead of a fixed section order. Local optimized images
remain as initial values until replaced through the media library. Homepage category cards are editorial
tabs; they become noninteractive when their materials block is disabled. `/about`, `/contacts`, and
`/catalog` fetch published `pages/{slug}` during SSR with metadata and structured data. Contacts load
seller details and every published store with working hours. Catalog uses the public catalog endpoint
with real prices/units, pagination, and image fallback. Catalog product links open the SSR
`/products/{slug}` route. Category, brand and brand-scoped collection links filter `/catalog`
through URL query parameters; the broader configurable filter interface remains a separate task.
Menu search filters managed navigation links.

After `npm ci`, `npm run build` and `npx playwright install chromium`, run `npm run test:e2e`.
The suite starts the production SSR build on port 3015 and a synthetic API on 8015; it never
uses the development database. It checks SSR, safe text, block order/disabled states, pagination,
retry/empty/404/503 states, hydration, axe, and 320/640/768/1024/1280 layouts. Screenshots are saved
in ignored `.tmp/client-e2e` at the repository root.

Managed pages also render at `/<slug>` using the same content view. `/home` permanently
redirects to `/`; `/preview` remains reserved. Published `is_indexable: false` produces
`noindex,follow`; an absent legacy flag defaults to `index,follow`. Errors, preview and
catalog pagination retain their stricter robots rules. Missing public pages return 404;
upstream failures return 503 with noindex.

`/sitemap.xml` collects every page of the public `GET /api/v1/pages` and `GET /api/v1/catalog`
collections. It includes indexable published content, with `home` mapped to `/`, and
public product URLs at `/products/{slug}`. Content entries use published slugs and optional
`published_at` values, never drafts. Any upstream or pagination failure returns 503 without
a partial sitemap; successful empty lists remain valid XML. The
response is not cached. `/robots.txt` advertises this sitemap and blocks only `/preview/`;
noindex pages remain crawlable so robots metadata can be read.

Set `NUXT_PUBLIC_SITE_URL` to the production origin for canonical, Open Graph and sitemap
URLs. Without it, pages use the current request origin. Dedicated category pages, checkout,
order confirmation, the remaining catalog flows and the managed entity SEO layer remain separate tasks.
Set `NUXT_PUBLIC_API_BASE` to the Laravel API v1 base URL (default `http://localhost:8000/api/v1`).
When Nuxt runs in Docker, `NUXT_API_BASE_INTERNAL` points server-side requests to
`http://backend:8000/api/v1`; browser requests and media URLs continue to use
`NUXT_PUBLIC_API_BASE`. Docker Compose sets the internal URL automatically.
For an existing local Laravel installation, include `http://localhost:3000` and
`http://127.0.0.1:3000` in `backend/.env` → `CORS_ALLOWED_ORIGINS`, then restart
the backend process. The updated `backend/.env.example` contains both origins.
If Laravel is unavailable, the homepage shows a visible retryable error instead of stale content.

## Product pages and cart

`app/pages/products/[slug].vue` loads `GET /api/v1/products/{slug}` during SSR and owns
response status, canonical, metadata, and Product/BreadcrumbList structured data. Missing or
unpublished products return 404; upstream failures return 503 with retry and noindex.
Each product has one `/products/{slug}` URL regardless of entry point. Breadcrumbs contain
home, the product category, and its name. Brand and collection are separate catalog links.

`app/features/product/` owns the detail DTO, media mapping, gallery, purchase controls,
information tabs, real variant links, and related/recommended products. It uses public API data,
not a hardcoded SKU or the former HTML demonstration. Missing photos, descriptions and
characteristics have explicit fallback states. Variants link to their actual product URLs.
The gallery uses `UiDialog` with `size="wide"`; product and cart quantities share
`UiQuantityInput`, and centered information navigation uses `UiTabs` without an underline.
Shared primitives own presentation and keyboard behavior; domain requests stay in features.

`app/features/cart/` connects the existing server cart API. `CartHost` in `app.vue` hosts
its `UiDialog`; `StorefrontHeader` supplies cart state/actions to the presentational `SiteHeader`.
`useCart()` exposes items, line count, loading/error/open state, and add/update/remove/load
actions. Cart creation and browser storage access occur on client interaction, without eager
SSR or page-load requests. The bearer token is kept in browser storage under
`agatceramic.cart.token` and sent only in `X-Cart-Token`; it never enters URLs or request bodies.
Confirmed expired reads can create a new cart; failed mutations are not automatically replayed.
The basket shows authoritative server prices and line totals; totals are summed in integer
minor units. The product-page estimate is informational and does not supply cart prices.

The owner chose integer sale quantities from 1 to 9999, with step 1, for every displayed
unit, including m². Quantities are always integers; fractional quantities are excluded
from further development by the owner's decision of 2026-10-30.
Cart supports loading, empty, unavailable-product, validation and network-error states,
quantity updates and removal. “Продолжить выбор” closes it; checkout is not implemented here.

The synthetic fixture modules in `e2e/fixtures/` are connected by `e2e/mock-api.mjs`.
`product-pages.spec.ts` and `cart.spec.ts` cover product/cart behavior without the development
database. Acceptance requires the current build, browser suite and independent UI Design Guard
review; test names in this document are coverage references, not a claim that a run passed.

## Saved draft preview

`/preview/<slug>` (`/preview/home` for the homepage) is embedded by the Admin content
workspace. It uses the same storefront bodies, blocks, header/footer and animations.
The browser requests `GET /api/v1/admin/content-preview/{slug}` with credentials;
only an active employee with `content.manage` can read saved page and appearance drafts.
No draft enters the server HTML/payload, persistent storage, URL, or public API.
The document and API prohibit caching/indexing; the document suppresses referrers.
The API fetch sends only the origin as Referer for same-origin Sanctum session recognition.

Include the exact Client hostname/port in `SANCTUM_STATEFUL_DOMAINS` and the origin in
`CORS_ALLOWED_ORIGINS` for an existing backend `.env`, then clear config/restart Laravel.
Use the same hostname consistently (`localhost` and `127.0.0.1` have different cookies).
Production Admin, API and Client must share the configured cookie site/domain; unrelated
sites with blocked third-party cookies cannot reuse this session. Do not enable wildcard
credentialed CORS or expose a preview token to bypass configuration.

While visible, the preview rechecks access every 15 seconds and clears content on failures.
Hidden/pagehide views clear drafts immediately; returning performs a new check.
Unchanged responses preserve slider state. Loading/401/403/404/network failures have
accessible retry controls. The E2E suite covers these states, actual iframe keyboard/axe,
responsive widths and both normal/reduced motion with synthetic authenticated fixtures.

Run `npm run test:e2e:dev` separately to check the development build in a
cross-origin editor iframe on ports 8015/3016. The test checks credentialed
requests, origin-only Referer and console errors. Nuxt DevTools stays enabled
in standalone windows; embedded `/preview/` views disable its client before
it attempts to read the editor's cross-origin window.

After changing `.env`, `php artisan serve --no-reload` must be restarted even
if a fresh CLI config check already shows the updated values. For Docker use
`docker compose exec backend php artisan config:clear` and
`docker compose restart backend`; session data remains in the database.

## Runtime compatibility

The scaffold uses Nuxt 4.5.2. It requires Node 22.19+, Node 24.11+, or Node 26+; the supported Node version must be used in development, CI, and production.
