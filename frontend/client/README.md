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

The homepage is the first client implementation. It follows `docs/CLIENT_UI_KIT.md` and uses local, optimized editorial imagery. Its category content is static until public catalog endpoints are available; it does not represent real products, prices, offers, stock, or checkout. Menu search navigates between homepage sections only.

Set `NUXT_PUBLIC_SITE_URL` to the production origin for canonical and Open Graph URLs. Without it, the homepage uses the current request origin. Catalog, cart, checkout, managed SEO content, sitemap, and robots remain separate tasks.

## Runtime compatibility

The scaffold uses Nuxt 4.5.2. It requires Node 22.19+, Node 24.11+, or Node 26+; the supported Node version must be used in development, CI, and production.
