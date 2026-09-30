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
published slider, header, footer, and homepage SEO are edited in Admin → «Главная сайта». Banner
content and order are edited in Admin → «Контент» → «Баннеры» / «Слайдеры». Saving changes updates a
draft; explicit publication updates the public snapshot, including the shared header/footer.
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

## Runtime compatibility

The scaffold uses Nuxt 4.5.2. It requires Node 22.19+, Node 24.11+, or Node 26+; the supported Node version must be used in development, CI, and production.
