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
with real prices/units, pagination, and image fallback. Product routes, filters, cart, and checkout
remain Phase 10. Menu search filters managed navigation links.

After `npm ci`, `npm run build` and `npx playwright install chromium`, run `npm run test:e2e`.
The suite starts the production SSR build on port 3015 and a synthetic API on 8015; it never
uses the development database. It checks SSR, safe text, block order/disabled states, pagination,
retry/empty/404/503 states, hydration, axe, and 320/640/768/1024/1280 layouts. Screenshots are saved
in ignored `.tmp/client-e2e` at the repository root.

Set `NUXT_PUBLIC_SITE_URL` to the production origin for canonical and Open Graph URLs. Without it, pages use the current request origin. Full catalog/category/product flows, cart, checkout, entity SEO, sitemap, and robots remain separate tasks.
Set `NUXT_PUBLIC_API_BASE` to the Laravel API v1 base URL (default `http://localhost:8000/api/v1`).
When Nuxt runs in Docker, `NUXT_API_BASE_INTERNAL` points server-side requests to
`http://backend:8000/api/v1`; browser requests and media URLs continue to use
`NUXT_PUBLIC_API_BASE`. Docker Compose sets the internal URL automatically.
For an existing local Laravel installation, include `http://localhost:3000` and
`http://127.0.0.1:3000` in `backend/.env` → `CORS_ALLOWED_ORIGINS`, then restart
the backend process. The updated `backend/.env.example` contains both origins.
If Laravel is unavailable, the homepage shows a visible retryable error instead of stale content.

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
