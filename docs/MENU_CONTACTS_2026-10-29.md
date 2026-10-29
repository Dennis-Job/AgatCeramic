# Нижний блок бокового меню

Запрос владельца: повторить нижнюю часть бокового меню из exampleSite.html —
телефон сайта, почта, текст, ниже ссылки на соцсети. Реализация начата 2026-10-28,
завершена 2026-10-29.

Источники данных:

- Телефон и email — существующие «Настройки сайта». Повторных запросов нет:
  StorefrontHeader уже загружает контакты для верхней полосы. На экране общая
  маска, в tel — канонический номер. Неоднозначный старый номер не становится
  другим tel; отсутствующие значения не заменяются контактами референса.
- Текст — «Контент → Общее оформление → Шапка и навигация → Боковое меню →
  Текст под контактами». Необязательный текст до 1000 символов, с переносами
  строк, без исполнения HTML. Сохранение и публикация раздельные.
- Соцсети — существующий список «Подвал → Мы в соцсетях», общий для двух мест.
  Адреса фильтруются safeLink. Если список пуст, строка скрыта.

Новый shared SiteMenuFooter — только представление. SiteHeader сохраняет
навигацию, поиск, общий focus trap, Escape/возврат фокуса, scroll lock и motion.
Нижний блок прижат вниз, на коротком экране вся панель прокручивается; длинный
контент и соцсети переносятся. Контакты13px, текст12px, соцсети11px uppercase
с тонкой границей по .sm-foot. Палитра не менялась. Публичный сайт и preview
используют одну реализацию; на desktop 1280 px бургер по-прежнему отсутствует.

Совместимость: header.menu_note отсутствует в старом JSON — читается прежний
footer.tagline; явно пустое/nullable значение нормализуется в ''. Старый writer
не стирает сохранённое поле, если не передаёт его. Чтение не меняет БД. Новый
ответ требует OpenAPI v12.0 по существующей strict-schema policy; порядок
rollout/rollback описан в OPENAPI_MIGRATION_PLAN.md. Миграций БД нет.

Основные изменённые файлы:

- Backend: UpdateHomePageRequest, HomePageManagementService, queries
  SiteAppearanceQuery/HomePageContentQuery/PublishedPageQuery, новый
  Support/SiteHeader.php; HeaderMenuNoteTest.php.
- Admin: SiteChromeEditor, useAppearance, services/appearance, appearance.types,
  navigationAppearance.test.ts, e2e/appearance.spec.ts. Нормализация зависимых
  старых значений не создаёт ложный dirty state и сохраняет локальные правки.
- Client: SiteMenuFooter, SiteHeader, StorefrontHeader, default layout и preview,
  types/homePage и services/homePage; menu-contacts.spec.ts, mock-api и актуальные
  ожидания навигации/фокуса в callback/content-pages/header-navigation/submenus.
- Документация: API, OpenAPI, migration plan, CLIENT_UI_KIT,
  CONTENT_WORKSPACE_UX, план docs/superpowers/plans/2026-10-28-menu-contacts.md.

Проверки:

- Backend: 665 passed, 1 existing skip, 7369 assertions; профильные 9/105.
  PHPStan, Pint, architecture, Composer validate/audit, OpenAPI lint,
  5 tooling tests, 42 schema fixtures и compatibility policy PASS.
  Skip — существующий PostgreSQL-only Cyrillic brand search под SQLite memory.
  SQL/schema не менялись; PostgreSQL-прогон не выполнялся.
- Admin: 274 unit tests, ESLint, Prettier, build PASS; E2E оформления 28/28 PASS.
  Проверены save/reload/publish/clear, разные ширины и axe.
- Client: typecheck/Prettier/build PASS; полный production E2E 84/84 PASS.
  Новые menu-contacts сценарии 3/3 PASS:
  порядок/данные/preview, пустые и legacy значения, 12 длинных соцсетей,
  короткий экран 320×360, unsafe URL, focus trap и Escape.
- Nuxt development preview: 2/2 PASS — аутентификация внутри cross-origin
  iframe, корректные Origin/Referer и отсутствие ошибок DevTools; самостоятельные
  страницы сохраняют DevTools. Evidence: client-dev.log, client-dev/.
- Независимый UI Design Guard: blocking findings отсутствуют. Просмотрены
  Client 320/640/768/1024 px, длинный контент 320×360 и Admin на пяти ширинах.
- Живой публичный Docker-сайт открыт отдельно от пользовательской админки;
  при 640 px проверены реальные контакты и соцсеть. Размер браузера восстановлен,
  проверочная вкладка закрыта. Контент сайта не менялся и не публиковался.

Evidence: `.tmp/menu-contacts/` — admin-unit/lint/format/build logs,
admin-e2e-final.log, client-typecheck/format/build logs, client-focused.log,
client-final.log и responsive screenshots в client/, admin-final/.
Первый полный Client-прогон обнаружил устаревшее ожидание Shift+Tab на поиск:
теперь последний focusable элемент — ссылка email (либо соцсеть при наличии).
Сценарий приведён к новому порядку; production logic для этого не менялась.
