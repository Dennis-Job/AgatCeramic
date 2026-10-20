# Обратный звонок в верхней строке сайта

Дата: 2026-10-20. Основание: согласованный запрос владельца по Browser comment
на главной странице сайта.

В общем оформлении шапки остаётся редактор правого текста. Вместо левого поля
показано пояснение: кнопка «Заказать звонок», первый телефон и email используют
публичные настройки сайта. Старый `topbar_left` сохранён в API и данных для
совместимости. Публикация страниц/оформления не меняет реквизиты продавца.

Последующее уточнение владельца меняет расположение: редактируемый текст
теперь слева, «Заказать звонок», единственный телефон и email — справа,
как в `exampleSite.html`. Единая маска и каноническое хранение телефонов
описаны в [PHONE_STANDARD_2026-10-20.md](PHONE_STANDARD_2026-10-20.md).
Ниже сохранены проверки первоначального этапа до этого уточнения.

`features/callback/components/StorefrontHeader.vue` соединяет публичные настройки,
общий `SiteHeader` и доменную форму; его используют публичный layout и preview.
Shared-шапка получает левый блок через slot и не знает способа отправки заявки.
Форма и её состояния отделены от composable и transport service; общие
`UiDialog`/`UiTextField` используют существующие tokens и `UiButton`.

Форма принимает обязательный телефон и необязательное имя, вызывает существующий
`POST /callback-requests` без автоматических повторов. Backend создаёт обращение
`callback`/`website`; существующий раздел «Обращения» сохраняет permissions,
фильтры, назначение, статусы и историю. Новые endpoint, поля БД и миграции не нужны.
При ошибке ввод сохраняется; после успеха очищается. Во время отправки повторный
submit и закрытие блокируются. Нет записи клиентских контактов в URL/storage/logs.

Проверяются SSR контактов, отсутствие hydration ошибок, первая запись телефона,
пустые/недоступные настройки, 422/429/503, обязательность телефона, защита от
повторной отправки, открытие/закрытие и фокус, axe и отсутствие overflow на
320/640/768/1024/1280 px. Регрессия редактора проверяет сохранение правого текста,
сохранность старого левого значения, публикацию, unsaved guard и permissions.

Независимый UI Design Guard принял код и screenshots всех пяти контрольных
ширин без blocking findings; палитра и утверждённые стили сохранены.

## Результаты проверки

- Client: `npm run typecheck`, `npm run build`, `npm run format:check` — PASS.
- Полный Client production E2E: **25/25 PASS**, включая пять новых callback
  сценариев, SSR/hydration, все публичные маршруты и настоящий preview/iframe.
- Client development E2E: **2/2 PASS**, cross-origin iframe, session cookie,
  Origin/Referer, отсутствие ошибок DevTools и сохранение DevTools вне iframe.
- Admin: `npm run build`, `npm run lint`, `npm run format:check` — PASS;
  полный unit-прогон **165/165 PASS**.
- Admin адресные production E2E: `appearance.spec.ts`, `navigation.spec.ts`,
  `contentWorkspaces.spec.ts`, `accessSalesContacts.spec.ts` — **55/55 PASS**.
- Backend: существующие `CallbackRequestTest` и `ContactWorkflowTest` внутри
  контейнера на SQLite `:memory:` — **7/7 PASS**, 80 assertions.
- `git diff --check` — PASS. API-схемы и миграции не изменены.
- Live `http://localhost:3000/`: новая шапка, открытие/закрытие формы,
  SSR/hydration и отсутствие overflow на 320 px — PASS. Публичные настройки
  сейчас возвращают пустые `phones`/`email`; после заполнения в «Настройках сайта»
  они выводятся автоматически. Тестовая заявка в рабочую БД не отправлялась.

Работающие Client/Admin dev-контейнеры перезапущены: они видели новые файлы,
но файловый watcher продолжал отдавать прежнюю шапку до перезапуска.

Для Windows запускать Playwright с `PLAYWRIGHT_BROWSERS_PATH`, указывающим на
установленный Chromium. Полные E2E запускались вне sandbox, чтобы Playwright
корректно завершал тестовые dev-серверы; иначе все сценарии выполнялись, но
teardown зависал. Для Vitest sandbox TEMP/TMP перенаправлялись в workspace
`.tmp`: исходный системный temporary path давал EPERM при rename до запуска тестов.
В локальной папке backend отсутствуют vendor-зависимости; использовано уже
работающее контейнерное окружение без установки пакетов и изменения рабочей БД.

## Изменённые файлы

- Client UI: `app/components/shared/SiteHeader.vue`,
  `app/components/ui/UiDialog.vue`, `app/components/ui/UiTextField.vue`.
- Client композиция: `app/layouts/default.vue`, `app/pages/preview/[slug].vue`.
- Client feature: `app/features/callback/components/StorefrontHeader.vue`,
  `CallbackRequestDialog.vue`, `composables/useCallbackRequest.ts`,
  `services/callbackRequests.ts`.
- Client E2E: `e2e/callback.spec.ts`, `e2e/mock-api.mjs`.
- Admin: `src/features/appearance/components/SiteChromeEditor.vue`,
  `e2e/appearance.spec.ts`, `e2e/navigation.spec.ts`.
- Документация: `docs/API.md`, `docs/CLIENT_UI_KIT.md`,
  `docs/CONTENT_WORKSPACE_UX.md`, этот отчёт и `tasks/DONE.md`.

Пути Client и Admin выше указаны относительно `frontend/client/` и
`frontend/admin/` соответственно. Commit не создавался.
