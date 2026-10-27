# Переходы из сетки баннеров

Дата: 2026-10-27. По уточнению владельца сохранено существующее наведение;
переход ведёт на собственную заданную ссылку баннера, а не на «Меню баннеров».

## Реализация

В Admin: «Контент → Страницы → Главная → Сетка баннеров → Ссылка баннера».
Поле необязательное, допускает внутренний путь `/catalog/...` или HTTPS URL.
После изменения нужно сохранить черновик и отдельно опубликовать страницу.
В «Меню баннеров» поле не добавляется; его контент остаётся независимым.

API принимает/возвращает optional nullable `categories.items.*.link_url`.
Отсутствующее/пустое значение не создаёт переход; старые записи не переписываются.
Backend проверяет URL через существующее SafeSiteUrl. Client дополнительно
отбрасывает небезопасные схемы, protocol-relative URL, credentials, обратные
слеши и управляющие символы. Внутренняя карточка использует NuxtLink, внешняя —
штатный HTTPS-переход. Ссылка доступна по клику, Enter и на сенсорном экране.
Увеличение картинки при наведении сохранено и поддержано через focus-visible.

Исправление после замечания владельца: восстановлена прежняя всплывающая плашка
«Смотреть» поверх кликабельного баннера. Сохранены её исходные отступы, шрифт,
surface token и анимация; появление работает при hover и focus-visible, в том
числе с reduced motion. Плашка не меняет адрес перехода и не появляется на
карточках без ссылки. Регрессионный браузерный тест сначала подтвердил отсутствие
плашки до исправления, затем проверяет default/hover/focus на пяти ширинах.

OpenAPI обновлён до v8.0 по политике закрытых response-схем; URL API остаются
`/api/v1`. Миграция БД не нужна. Существующие адреса не назначались автоматически,
реальный контент не сохранялся и не публиковался. Commit не создавался.

## Изменённые файлы

- Backend: `UpdateHomePageRequest.php`, `GridBannerLinksTest.php`.
- API: `docs/openapi.json`, `docs/OPENAPI_MIGRATION_PLAN.md`.
- Admin: `HomePageBodyEditor.vue`, `homepage.types.ts`,
  `homeBannerBlocks.test.ts`, `e2e/content-blocks.spec.ts`.
- Client: `HomeCategoryCard.vue`, `services/homePage.ts`, `types/homePage.ts`,
  `e2e/banner-links.spec.ts`, `e2e/mock-api.mjs`.
- UX/UI-документация: `CONTENT_WORKSPACE_UX.md`, `CLIENT_UI_KIT.md`,
  этот отчёт и `tasks/DONE.md`.

## Проверки

- До реализации: Admin regression RED (поля ссылки нет), Client SSR regression
  RED (ссылки в HTML нет), backend PostgreSQL RED (новое поле отклоняется).
- Backend PostgreSQL: 32 теста / 446 assertions PASS, включая отдельные ссылки
  баннеров, сохранение/публикацию, nullable/отсутствующие значения и отклонение
  небезопасных URL. Pint, весь PHPStan, architecture guard, OpenAPI contract,
  lint, compatibility и 5 tooling tests PASS.
- Admin build PASS; профильные E2E 10/10 PASS: сохранение, публикация,
  перезагрузка ссылки, отсутствие поля в меню и пять responsive ширин.
- Client typecheck/build PASS; два новых E2E PASS: SSR/hydration, внутренний
  переход по Enter, внешний href, hover без навигации/переключения меню,
  безопасный fallback и настоящий Nuxt-preview.
- Admin: 255 unit-тестов, lint и format:check PASS. Client format:check PASS.
- Независимый UI Design Guard — accepted, замечаний P1/P2/P3 нет.
  Лично просмотрены 15 Admin/Client screenshots на 320/640/768/1024/1280 px.
- Полный Client E2E при добавлении ссылок: 63/63 PASS.
- После восстановления плашки: typecheck/build PASS, профильные E2E 4/4 PASS,
  включая default/hover/focus на пяти ширинах и normal/reduced motion.
  Независимый UI Design Guard принял восстановление после просмотра 12 PNG;
  исходный вид сверён с прежним компонентом. Дополнительное evidence:
  `.tmp/grid-watch-badge/final/`, `.tmp/grid-watch-badge-e2e.log`.

Evidence: `.tmp/grid-banner-links/admin-e2e/`, `client-e2e/`, `client-full/`
и `.tmp/grid-banner-links-*.log`. Известное утверждённое замечание по контрасту
подписи в соседнем меню остаётся вне этой задачи; палитра не менялась.
