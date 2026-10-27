# Ссылки и примечания каждого баннера меню

Дата: 2026-10-27. Требование владельца: добавить переход по ссылке из баннера
«Меню баннеров» без плашки «Смотреть»; заменить общее примечание индивидуальными.

## Результат

В Admin у каждого элемента меню есть собственные «Ссылка баннера» и «Примечание
под баннером». Общее поле убрано из формы. Поля используют текущие UiInput и
UiTextarea; изменение одного баннера не меняет соседние или сетку баннеров.
Пустое примечание остаётся пустым. Права, nullable URL, черновики, media guards
и отдельная публикация сохранены.

Client показывает только примечание выбранного баннера. Изображение и текст
образуют одну семантическую ссылку на его адрес; кнопки вкладок находятся снаружи
и только выбирают баннер. Плашка «Смотреть» в меню отсутствует. Без допустимого
URL панель остаётся обычным содержимым. Ссылки принимают внутренние пути от `/`
и HTTPS без credentials; unsafe URL отбрасываются и на backend, и при mapping.
Клавиатурные Tab/Enter, стрелки вкладок и видимый focus проверены.

## Данные и API

Добавлены optional nullable `materials.items.*.link_url` (до 2048 символов) и
`materials.items.*.note` (до 2000). Legacy `materials.note` остался optional/nullable
для совместимости; новый UI его не редактирует/не выводит. Runtime fallback из
общего примечания отсутствует. OpenAPI — v9.0, маршруты остаются `/api/v1`.

Миграция `2026_10_27_130000_move_banner_menu_notes_to_items.php` копирует старое
общее значение только в элементы без собственного ключа `note`. Собственные
строки, `''`, `null` и ссылки не перезаписываются. Черновики, опубликованные
snapshot и legacy home content обрабатываются отдельно, timestamps и флаги
публикации не меняются. Миграция повторяема; down запрещает потерю самостоятельных
примечаний. Rollout/rollback описаны в `OPENAPI_MIGRATION_PLAN.md`.

До применения создан защищённый backup вне репозитория с Windows DPAPI CurrentUser;
проверены расшифрование/hash и restore в отдельную временную PostgreSQL-базу.
Миграция успешно прошла на копии, повторный запуск безопасен, временная база удалена.
Backup ID: `menu-banner-notes-20261027T115814Z`, срок хранения до 30 дней.

Затем только эта миграция применена локально. Дополнительная проверка внутри
транзакции подтвердила сохранность исходных полей/текстов/ссылок пяти страниц и
одной legacy-записи главной, а также реальных dirty/publication состояний.
Реальные черновики не публиковались. Commit не создавался.

## Файлы

- Backend: `UpdateHomePageRequest.php`, `config/home_page.php`, новая миграция,
  `MenuBannerDetailsTest.php`; обновлена устаревшая проверка запрета menu URL
  в `GridBannerLinksTest.php`.
- Admin: `HomePageBodyEditor.vue`, `homepage.types.ts`, `validation/blocks.ts`,
  `homeBannerBlocks.test.ts`, `e2e/content-blocks.spec.ts`.
- Client: `HomeMaterialShowcase.vue`, `services/homePage.ts`, `types/homePage.ts`,
  `e2e/mock-api.mjs`, `e2e/menu-banner-details.spec.ts`.
- Документы: `openapi.json`, `OPENAPI_MIGRATION_PLAN.md`, `CLIENT_UI_KIT.md`,
  `CONTENT_WORKSPACE_UX.md`, этот отчёт и `tasks/DONE.md`.

Предыдущие незакоммиченные изменения ссылок и плашки сетки сохранены.

## Проверки

- RED: Admin показывал одно общее примечание и не имел ссылок меню;
  Client SSR не отдавал ссылку и выводил общий текст; API отклонял новые поля.
- Admin: build, lint, format PASS; 256 unit-тестов и 11 профильных E2E PASS,
  включая независимые примечания, сохранение/перезагрузку, URL и media guards.
- Client: typecheck, build, format PASS; полный E2E — 67/67 PASS. Дополнительно
  2 E2E PASS с реальным Tab/focus-visible, Enter, кликом по изображению,
  индивидуальными notes, отсутствием «Смотреть» и настоящим saved Nuxt-preview.
- Backend: PostgreSQL feature 485 тестов / 6234 assertions PASS; focused
  17 / 254 PASS; composer test — 581 PASS / 1 ожидаемый SQLite SKIP,
  6571 assertions. Pint, PHPStan, composer validate/audit/architecture,
  OpenAPI lint/compatibility и 5 tooling tests PASS.
- UI Design Guard — accepted. Проверены пять ширин 320/640/768/1024/1280,
  формы, пустое изображение, no-badge, вкладки и native keyboard focus.
- Live smoke: у всех четырёх опубликованных элементов меню есть свой `note`;
  Client `/` отвечает HTTP 200. `git diff --check` PASS.

Evidence: `.tmp/menu-banner-details/admin-e2e/`, `client-focused/`,
`client-full/`, `client-keyboard/` и `.tmp/menu-banner-details-*.log`.
Backup и содержимое базы в репозитории/evidence не сохранялись. Утверждённая
палитра не менялась; прежний контраст eyebrow меню 4.25:1 остаётся известным
замечанием вне этой задачи.
