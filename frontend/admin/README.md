# AgatCeramic Admin

Административная SPA для AgatCeramic: Vue 3, TypeScript, Vite, Vue Router, Pinia и Tailwind CSS.

Визуальная основа — TailAdmin Vue style. Бизнес-правила и доступ к данным остаются в Laravel API.

«Контент» → «Страницы» (`/content`, право `content.manage`) содержит типизированный
редактор блоков выбранной страницы. Главная использует тот же редактор блоков и SEO;
выбор страницы, режим и блок сохраняются в URL. Слайдеры и баннеры создаются и
редактируются прямо в слайдерном блоке. Медиа выбираются и загружаются рядом с
настройками изображения при наличии `media.manage`; уже выбранные файлы видны и
сотруднику с правом чтения контента. Сохранение черновика и публикация разделены.

Вкладки «Контента» — «Страницы», «Общее оформление», «Магазины». Шапка, навигация
и подвал сохраняются/публикуются отдельно от страниц; выбор панели — `panel=header|footer|filters`.
Логотип поддерживает выбор и inline-загрузку с `media.manage`. Панель представления
фильтров сообщает, что клиентские фильтры ещё не реализованы. Формы магазинов
и расписания защищают несохранённые правки. Старый `/home-page` ведёт в редактор главной
внутри `/content`; дублирующий пункт sidebar удалён. Legacy URL баннеров/слайдеров
перенаправляются к страницам с сохранением page/block; верхних вкладок для них нет.
Главная открывает единый редактор блоков и SEO; секционный UI закрыт.
Точный Nuxt-предпросмотр подключён в C005. Контракт рабочей области —
[`CONTENT_WORKSPACE_UX.md`](../../docs/CONTENT_WORKSPACE_UX.md), evidence C003 —
[`TASK_C003_REPORT.md`](../../docs/TASK_C003_REPORT.md).

## Предпросмотр сохранённого черновика

Правая зона страниц и общего оформления встраивает настоящий клиентский Nuxt-маршрут `/preview/<slug>` (`/preview/home` для главной). Доступны компьютер (1280 px), телефон (375 px), обновление и безопасное открытие в новой вкладке. Широкий клиентский viewport прокручивается внутри панели. После сохранения панель перезагружается; локальные правки явно отмечены как не вошедшие в просмотр. Публичная страница открывается отдельной ссылкой.

`VITE_CLIENT_URL` в `.env` задаёт URL клиента (по умолчанию `http://localhost:3000`); требуется HTTP(S) адрес без credentials, query и fragment. Nuxt получает черновик напрямую из защищённого API с действующей Sanctum cookie-сессией и правом `content.manage`. Сессия должна быть доступна для клиентского hostname, а backend CORS/stateful domains — разрешать Admin и Client origins. Токены и данные черновика не передаются в URL или через postMessage. Сообщения загрузки, ошибок доступа и повторная загрузка находятся внутри Nuxt-просмотра.

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
