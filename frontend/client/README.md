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

The homepage is the first client implementation. It follows `docs/CLIENT_UI_KIT.md` and fetches
managed content from Laravel `GET /api/v1/home-page` during SSR. Text, section images, the selected
published slider, header, footer, and homepage SEO are edited in Admin → «Главная сайта». Banner
content and order are edited in Admin → «Контент» → «Баннеры» / «Слайдеры». Local optimized images
remain as initial values until replaced through the media library. Category cards are editorial
tabs until public catalog endpoints are available; they do not represent real products, prices,
offers, stock, or checkout. Menu search navigates between homepage sections only.

Set `NUXT_PUBLIC_SITE_URL` to the production origin for canonical and Open Graph URLs. Without it, the homepage uses the current request origin. Catalog, cart, checkout, managed SEO content, sitemap, and robots remain separate tasks.
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
