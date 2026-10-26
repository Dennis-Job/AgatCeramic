# Автоматический copyright в подвале

Дата: 2026-10-26. Указание владельца в Browser annotation: убрать редактирование
левой подписи нижней строки, показывать текущий год по часовому поясу сайта.

## Реализация

Сервер вычисляет `© <год> AgatCeramic` через `SiteCopyright::text()` по
`config('app.timezone')` / `APP_TIMEZONE`. Текущая конфигурация — `Europe/Moscow`.
Значение формируется при каждом запросе public/admin appearance, legacy home
и content-preview. Публикация и изменение данных для смены года не требуются.
Браузерные часы не участвуют; существующий Nuxt `SiteFooter` получает уже готовую
строку через API и SSR payload. Client-код и его оформление не менялись.

В Admin вместо поля «Слева» показаны подпись и пояснение. Поле `bottom_left`
не отправляется новым клиентом. Legacy PATCH принимает необязательную строку,
но игнорирует её до сохранения. Исторический raw текст в HomePage, site_layout
и опубликованном snapshot не удаляется и не заменяется при чтении/сохранении.
Правая подпись редактируется прежним способом.

При ответе API редактор обновляет server-derived значение отдельно, сохраняя
настоящие локальные изменения подвала. Новый год не создаёт ложный dirty state.

## Проверки

- Backend API/OpenAPI: 28 tests, 2918 assertions PASS.
- Новый год и read-only legacy writes: 5 cases / 63 assertions PASS как на SQLite,
  так и на изолированной PostgreSQL `agatceramic_queue_test`; штатный safe-test guard
  подтвердил окружение, временный PostgreSQL остановлен после проверки.
- Часовые пояса: Moscow до/после местной полуночи, UTC, Honolulu при ещё старом годе.
- PHPStan: PASS. Pint изменённых файлов: PASS. Architecture guard: без блокирующих
  нарушений; существующие сообщения о размере других классов не относятся к задаче.
- Composer validate --strict: PASS. Composer audit --locked: не завершился из-за
  сетевого тайм-аута Packagist (curl 28); зависимости не менялись.
- Admin: 48 unit-файлов / 248 tests, lint, format:check, typecheck/build PASS.
- Appearance E2E: 16/16 PASS, включая read-only UI, отсутствие `bottom_left` в PATCH,
  сохранение остальных полей, guards, responsive/axe 320–1280 px.
- Live Admin, настоящий Nuxt-preview и публичный сайт: подпись `© 2026 AgatCeramic`,
  browser console errors не обнаружены.
- Финальное независимое UI Design Guard review: accepted, блокирующих замечаний нет;
  проверены diff и responsive-снимки на всех пяти ширинах.

Миграции и SQL/schema не менялись. Полные suites Client/Admin/backend повторно
не запускались; выполнены перечисленные профильные проверки.

Evidence: `.tmp/footer-copyright/admin-live.png`, `admin-full.png`,
`e2e-final.json`, `e2e-final/**/appearance-footer-*.png`.

## Изменённые файлы

- `backend/app/Support/SiteCopyright.php` — единое вычисление текущего года.
- `backend/app/Queries/SiteAppearanceQuery.php`, `HomePageContentQuery.php` — read-проекция.
- `backend/app/Services/HomePageManagementService.php` — сохранность legacy raw поля.
- `backend/app/Http/Requests/Api/V1/Admin/UpdateHomePageRequest.php` — совместимый optional input.
- `backend/app/Models/HomePage.php` — только уточнение существующего PHPDoc footer.
- `backend/tests/Feature/Api/SiteAppearanceManagementTest.php` — дата/timezone/read-only regression.
- `frontend/admin/src/features/appearance/components/SiteChromeEditor.vue` — информационная подпись.
- `frontend/admin/src/features/appearance/services/appearance.ts`, `composables/useAppearance.ts` — readonly transport/state.
- `frontend/admin/tests/appearanceCopyright.test.ts`, `e2e/appearance.spec.ts` — регрессии состояния и контракта.
- `docs/API.md`, `docs/openapi.json`, `docs/CONTENT_WORKSPACE_UX.md`, `docs/CLIENT_UI_KIT.md` — контракт и правила.
- `docs/FOOTER_COPYRIGHT_2026-10-26.md`, `design-qa.md`, `tasks/DONE.md` — итог и ревью.

Commit, публикация оформления и изменение данных рабочего сайта не выполнялись.
