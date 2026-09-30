# AgatCeramic Admin

Административная SPA для AgatCeramic: Vue 3, TypeScript, Vite, Vue Router, Pinia и Tailwind CSS.

Визуальная основа — TailAdmin Vue style. Бизнес-правила и доступ к данным остаются в Laravel API.

«Контент» → «Страницы» (`/content`, право `content.manage`) содержит типизированный
редактор блоков выбранной страницы. На главной он открывается через «Блоки и порядок»;
выбор страницы, режим и блок сохраняются в URL. Слайдеры и баннеры создаются и
редактируются прямо в слайдерном блоке. Медиа выбираются и загружаются рядом с
настройками изображения при наличии `media.manage`; уже выбранные файлы видны и
сотруднику с правом чтения контента. Сохранение черновика и публикация разделены.

Прежний `/home-page` и отдельные входы баннеров/слайдеров сохранены. Перенос общего
оформления и точный Nuxt-предпросмотр относятся к C004/C005. Контракт рабочей области —
[`CONTENT_WORKSPACE_UX.md`](../../docs/CONTENT_WORKSPACE_UX.md), evidence C003 —
[`TASK_C003_REPORT.md`](../../docs/TASK_C003_REPORT.md).

## Authentication

Admin SPA uses Laravel Sanctum cookie sessions. On startup it restores the active session,
redirects unauthenticated users to `/login`, requests the CSRF cookie before state-changing
requests, and supports logout from the application header.

## Commands

```bash
npm ci
npm run dev
npm run lint
npm run format:check
npm run check
npm run build
npm run test:unit
npm exec playwright install chromium
npm run test:e2e
npm run test:ci
```

`check` последовательно запускает ESLint, Prettier check, Vitest и production build. `test:ci`
добавляет к тем же lint/format/unit gates полный production Playwright suite. `test:e2e` запускает
его отдельно в Chromium; API изолирован browser-level fixtures в `e2e/catalogApi.ts`. Playwright
не повторяет упавшие тесты ни локально, ни в CI: любой нестабильный результат сразу завершает
команду с ошибкой.

## Initial structure

- `src/components` — shared UI components;
- `src/layouts` — application layouts;
- `src/views` — route views;
- `src/router` — SPA routing.
