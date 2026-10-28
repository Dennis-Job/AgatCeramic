# Подменю навигации магазина — 2026-10-28

Запрос владельца: управлять наличием и составом подменю при создании пункта
навигации. После интерактивного сравнения выбран вариант «Только раскрытие»;
спецификация согласована владельцем перед реализацией.

## Поведение

В Admin: «Контент → Общее оформление → Шапка и навигация → Навигация».
Переключатель «Есть подменю» заменяет ссылку родителя списком подпунктов.
У каждого подпункта название и адрес; доступны добавление, удаление и порядок.
Группа перемещается целиком. Один уровень вложенности, 1–16 родителей и 1–16
ссылок в каждой группе; названия до 255 символов, URL до 2048.

Скрытые при переключении значения сохраняются в локальном состоянии сессии
редактора, включая переходы между шапкой/подвалом/фильтрами и подтверждение
сохранения. В API передаются только поля активного режима. Неполный ввод
блокирует сохранение без потери данных. Сохранённый baseline не разделяет
изменяемый объект с формой: повторное редактирование после сохранения вновь
включает кнопку сохранения. Повторные ошибки/подтверждения показываются после
закрытия предыдущего уведомления; закрытие ошибки первоначальной загрузки
сохраняет возможность повторить загрузку.

На desktop название и стрелка — одна кнопка раскрытия. Выпадающий список
использует native manual popover: он не обрезается ограниченной прокруткой
шапки, ограничен viewport и при необходимости прокручивается. На телефоне и
в боковой панели — раскрывающаяся группа. Одновременно открыта одна группа.
Escape закрывает сначала подменю с возвратом фокуса, затем саму боковую панель.
Клик/фокус вне навигации, другая панель и переход закрывают подменю.

Обычные пункты остаются ссылками. Для перехода в общий раздел можно вручную
добавить подпункт «Весь каталог». Родитель сам по себе никуда не переходит.
Поиск учитывает названия детей и групп и удаляет повторяющиеся адреса.
Навигация подвала остаётся плоской проекцией обычных и дочерних ссылок;
самостоятельные footer explore_links не меняются. Текущая группа/дочерняя
ссылка учитывают путь, указанные query-параметры и существующее hash-поведение.

Палитра, typography и shared UI primitives не менялись. Используются UiSwitch,
UiInput, UiField, UiButton и действующие tokens. Ссылки присутствуют в SSR DOM;
скрытые списки исключены из Tab-порядка. Preview использует тот же Client.

## API и данные

Сохраняются обычные `{label,to}` и группы `{label,children:[{label,to}]}`.
У группы нет `to`. Сервер отклоняет пустые/слишком большие группы, третий
уровень, смешанные поля, неизвестные ключи и unsafe URL. Client дополнительно
отбрасывает опасные адреса и пустые после фильтрации группы.

OpenAPI — v10.0; маршруты остаются `/api/v1`. Shared inherited FormRequest
покрывает site-appearance и совместимый home-page. Прямые записи `site_layout`
через страницы остаются запрещены; существующие projections/preview сохраняют
JSON без дополнительных преобразований. Права, аудит, transaction границы,
черновик и отдельная публикация сохранены. Публичное меню меняется только
после публикации оформления владельцем.

Новые таблицы и миграции не нужны. Рабочая БД и опубликованные записи не
изменялись. Условия совместного выпуска API/обоих frontend и rollback —
в `OPENAPI_MIGRATION_PLAN.md`.

## Проверки

- Backend focused: 33 теста / 335 assertions PASS, независимо повторено root.
- Backend full: 617 PASS / 7050 assertions, один существующий SQLite SKIP
  для PostgreSQL-проверки Unicode LOWER в BrandManagementTest.
- Backend: Pint, PHPStan, architecture guard, Composer strict validate,
  OpenAPI lint/conventions, 5 tooling tests, compatibility policy и 114 AJV
  input/output/flat-footer fixtures PASS.
- Client: typecheck/build/format после исправления query active state PASS;
  отдельная query-регрессия RED→GREEN; финальный полный E2E — 74/74 PASS.
- Admin: build и 53 файла / 268 unit-тестов PASS; профильный E2E — 26/26 PASS,
  включая повторное сохранение, публикацию, ошибки, timeout/dismiss и пять
  ширин с axe. Lint/format PASS.
- Настоящий Admin + Nuxt iframe: два сценария, каждый повторён дважды — 4/4 PASS;
  desktop/375 px, pointer и keyboard, Escape/focus, реальный сохранённый preview.
  После смены размера проверка ждёт fonts/layout и начальный focus панели,
  как существующий iframe visual runner, без искусственной задержки по времени.
- Nuxt development iframe и standalone DevTools: 2/2 PASS; session cookie,
  Origin/Referer, запрет DevTools внутри iframe и отсутствие runtime ошибок.
- Независимый UI Design Guard просмотрел реальные screenshots Admin/Client
  на 320/640/768/1024/1280, desktop dropdown, max/short viewport и Nuxt preview.
  Итоговый verdict — accepted; найденная при delta-review потеря Retry после
  dismiss первоначальной ошибки загрузки исправлена с двумя регрессиями
  (ручное закрытие и автоматический timeout), исправление принято Guard.

Регрессии воспроизведены до исправлений: backend отклонял группы; Client
отбрасывал их и не выводил дочерние ссылки в SSR; Admin не имел переключателя.
Дополнительно воспроизведены потеря скрытого ввода при переходе к фильтрам,
неактивная кнопка сохранения после повторного редактирования, скрытое состояние
Retry после dismiss и отсутствие active state у query-ссылок. Тестовые
проверки доступного имени поиска и скрытой при resize навигации уточнены
без изменения продукта. Старая iframe-проверка текста topbar на телефоне
исправлена: CSS уже намеренно скрывал этот текст на малых ширинах.

## Изменённые файлы

- Backend: `app/Http/Requests/Api/V1/Admin/UpdateHomePageRequest.php`,
  `tests/Feature/Api/NavigationSubmenusTest.php`.
- Admin appearance: `components/AppearanceWorkspace.vue`, `SiteChromeEditor.vue`,
  новый `NavigationEditor.vue`, `composables/useAppearance.ts`,
  `types/appearance.types.ts`, новый `validation/navigation.ts`.
- Admin tests: `tests/navigationEditor.test.ts`, `navigationAppearance.test.ts`,
  `e2e/appearance.spec.ts`, `e2e-preview/contentPreview.spec.ts`,
  новый `e2e-preview/navigationSubmenus.spec.ts`.
- Client: `app/components/shared/SiteHeader.vue`, новый `SiteNavigation.vue`,
  `app/types/homePage.ts`, `app/services/homePage.ts`, новый
  `app/utils/navigation.ts`, `app/layouts/default.vue`,
  `app/pages/preview/[slug].vue`.
- Client tests: `e2e/mock-api.mjs`, новый `e2e/navigation-submenus.spec.ts`,
  `e2e-dev/preview.spec.ts`.
- Документы: `API.md`, `openapi.json`, `OPENAPI_MIGRATION_PLAN.md`,
  `CLIENT_UI_KIT.md`, `CONTENT_WORKSPACE_UX.md`, этот отчёт,
  `superpowers/specs/2026-10-28-navigation-submenus-design.md`,
  `superpowers/plans/2026-10-28-navigation-submenus.md`, tasks status.

Пути Backend/Admin/Client указаны относительно соответствующих корней проекта,
документы — относительно docs/. Коммитов, push, новых зависимостей или
автоматической публикации содержимого нет. Рабочая ветка `codex/navigation-submenus`.

## Evidence и ограничения

Локальные проверочные артефакты: `.tmp/navigation-submenus/` — backend summary,
test/build logs, Admin screenshots пяти ширин, Client screenshots и genuine
Admin/Nuxt iframe captures. Артефакты в .tmp не входят в Git.

Основные результаты: `backend-summary.txt`, `admin-summary.txt`,
`client-build-final.log`, `client-final.log`, `client-dev-final.log`,
`admin-preview-verified.log`. Скриншоты финальных Client-сценариев в
`client-final/`, реальный iframe — в `admin-preview-verified/`.

PostgreSQL suite не запускался: схема/SQL не менялись; полная SQLite memory
suite проверяет migrations в изоляции. Composer audit завершился с кодом 0
без advisories, но после timeout Packagist использовал локальный cache;
это не подтверждение актуальности security feed. Chromium — проверенный браузер;
отдельной матрицы legacy-браузеров без Popover API нет.

Первоначальные Windows sandbox-попытки упирались в vendor is_readable, EPERM
в Nuxt output/temp и завершение npm webServer. Проверки повторены с разрешёнными
runtime и установленным Chromium; Admin runner завершает сервер отдельно.
Существующие предупреждения о размере Admin chunk/времени Nuxt hooks сохранены.
