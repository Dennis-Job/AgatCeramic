# AgatCeramic Admin

Административная SPA для AgatCeramic: Vue 3, TypeScript, Vite, Vue Router, Pinia и Tailwind CSS.

Визуальная основа — TailAdmin Vue style. Бизнес-правила и доступ к данным остаются в Laravel API.

## Authentication

Admin SPA uses Laravel Sanctum cookie sessions. On startup it restores the active session,
redirects unauthenticated users to `/login`, requests the CSRF cookie before state-changing
requests, and supports logout from the application header.

## Commands

```bash
npm install
npm run dev
npm run build
npm run test:unit
npm exec playwright install chromium
npm run test:e2e
```

`test:unit` запускает Vitest для composable и Vue component-тестов. `test:e2e` запускает authenticated Catalog flows в Chromium; API изолирован browser-level fixtures в `e2e/catalogApi.ts`.

## Initial structure

- `src/components` — shared UI components;
- `src/layouts` — application layouts;
- `src/views` — route views;
- `src/router` — SPA routing.
