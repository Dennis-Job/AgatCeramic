# Алфавитное меню брендов — 2026-11-17

## Уточнение: компактная полоса по центру

По указанию владельца убрано растягивание букв через `space-between`.
Подпись и алфавит собраны по центру контейнера; буквы разделены постоянным
gap 12 px, на узком экране группа центрированно переносится. Выпадающая
панель сохраняет полную ширину контейнера и прежние колонки/поведение.

Изменены `CatalogBrandNavigation.vue`, правило UI-032 в `CLIENT_UI_KIT.md`,
этот отчёт и статус задачи. Typecheck, format:check, production build и все
7 существующих `catalog-brand-menu` E2E — PASS. Скриншоты пяти ширин
320/640/768/1024/1280 px просмотрены; независимый UI Design Guard PASS.
Новые тесты для локальной CSS-коррекции не добавлялись. Логи:
`.tmp/brand-menu-centered-build.log`, `.tmp/brand-menu-centered-e2e.log`.
Коммит не создавался.

## Уточнение: управление прямо из списка брендов

По дополнительному запросу владельца в строки `/brands` добавлен UiSwitch
«В алфавитном меню». Он сохраняет только `show_in_catalog_menu` обычным PATCH,
без модального окна, обновления списка или сброса поиска/страницы. Во время
запроса блокируются повторное переключение, редактирование и удаление этой
строки. При ошибке остаются прежнее значение и видимый список; сообщение
содержит имя бренда. Фокус после native disabled возвращается кнопке, если
пользователь не перешёл к другому элементу. Старые ответы загрузки списка
защищены от перезаписи подтверждённого флага, новые запросы авторитетны.

Дополнительно изменены:

- `frontend/admin/src/features/brands/components/BrandsList.vue`
- `frontend/admin/src/features/brands/components/BrandsWorkspace.vue`
- `frontend/admin/src/features/brands/composables/useBrandCatalog.ts`
- `frontend/admin/src/features/brands/services/brands.ts`
- `frontend/admin/tests/brandInlineMenu.test.ts`
- `frontend/admin/e2e/brandCatalogMenu.spec.ts`
- `frontend/admin/e2e/adminBaselineApi.ts`
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-brands-chromium-win32.png`
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-brands-chromium-linux.png`

Актуальные проверки уточнения: `npm run check` PASS, 327 unit-тестов;
24/24 релевантных browser E2E PASS; axe, keyboard/focus/error/retry,
320/640/768/1024/1280 px, независимый UI Design Guard PASS.
Изначальные пять новых unit-сценариев и сценарий native focus loss проверены
RED→GREEN. Фактические рабочие бренды не изменялись; новые Backend/Client
изменения и миграции для быстрого переключателя не требуются.
Скриншоты: `.tmp/brand-inline-menu/row-{width}.png`; логи:
`.tmp/brand-inline-check.log`, `.tmp/brand-inline-e2e.log`.

Дополнительно: состояния списка, адаптивность до 2560 px и длинные 25 строк —
Windows 13/13 PASS. Linux production build и 17/17 проверок новых inline-сценариев
и страницы брендов — PASS. На обеих платформах исходный visual regression
ожидаемо обнаружил добавленный control/высоту строки; после просмотра реальных
Windows/Linux PNG и независимого UI Design Guard обновлены только два
эталона заполненной страницы. Последующие обычные прогоны без обновления
эталонов прошли. Логи: `.tmp/brand-inline-baseline.log`,
`.tmp/brand-inline-linux-e2e.log`. До Linux-сборки синхронизирован устаревший
изолированный test volume (`npm ci`); manifest/lockfile не менялись.

Статус: реализовано, независимый UI Design Guard PASS. Коммит не создавался.

## Результат

На `/catalog` под категориями добавлено меню выбранных активных брендов.
Показываются только непустые группы: `0–9`, латинские начальные буквы, `РУС`.
Выпадающая панель занимает ширину контейнера, содержит четыре колонки на
desktop, две от 640 px и одну ниже 640 px. Работают наведение, нажатие,
касание, клавиатура, Escape и закрытие вне меню. Ссылки присутствуют в SSR.
Выбор бренда начинает подбор без предыдущих ограничений выдачи. Preview
использует тот же компонент. Липкое поведение категорий сохранено.

В Admin → Бренды → создание/редактирование добавлен переключатель
«Показывать в алфавитном меню каталога». Он независим от активности бренда,
сохраняется обычным CRUD и отражается в аудите. Публичный список не зависит
от категории, фильтров, наличия товаров или страницы выдачи.

Значение по умолчанию выключено, включая существующие бренды. Поэтому до
ручного выбора хотя бы одного активного бренда меню не показывается.
Миграция `2026_11_17_000001_add_show_in_catalog_menu_to_brands.php` применена
отдельным `--path` к проверенной локальной PostgreSQL БД проекта
(`APP_ENV=local`, database `agatceramic`). Другие миграции не запускались,
настройки конкретных брендов не изменялись. Реальный API после миграции
вернул `brand_navigation: []` и прежние 11 товаров.

## Изменённые файлы

Backend:

- `backend/database/migrations/2026_11_17_000001_add_show_in_catalog_menu_to_brands.php`
- `backend/app/Models/Brand.php`
- `backend/app/Http/Requests/Api/V1/Admin/StoreBrandRequest.php`
- `backend/app/Http/Requests/Api/V1/Admin/UpdateBrandRequest.php`
- `backend/app/Http/Resources/Catalog/BrandResource.php`
- `backend/app/Http/Resources/PublicCatalogBrandResource.php`
- `backend/app/Http/Controllers/Api/V1/CatalogController.php`
- `backend/app/Queries/PublicCatalogQuery.php`
- `backend/app/Services/AuditSnapshotService.php`
- `backend/app/Support/AuditLabels.php`
- `backend/tests/Feature/Api/BrandCatalogNavigationTest.php`
- `backend/tests/Unit/OpenApiCatalogContractTest.php`

Admin:

- `frontend/admin/src/features/brands/types/brand.types.ts`
- `frontend/admin/src/features/brands/validation/brand.schema.ts`
- `frontend/admin/src/features/brands/composables/useBrandForm.ts`
- `frontend/admin/src/features/brands/components/BrandFormDialog.vue`
- `frontend/admin/tests/brandCatalogMenu.test.ts`
- `frontend/admin/tests/brandSearch.test.ts`
- `frontend/admin/e2e/brandCatalogMenu.spec.ts`
- `frontend/admin/e2e/catalogApi.ts`

Client:

- `frontend/client/app/features/catalog/types/catalog.ts`
- `frontend/client/app/features/catalog/services/catalog.ts`
- `frontend/client/app/features/catalog/components/CatalogPageBody.vue`
- `frontend/client/app/features/catalog/components/CatalogBrandNavigation.vue`
- `frontend/client/app/features/catalog/composables/useBrandAlphabetMenu.ts`
- `frontend/client/app/features/catalog/utils/brandAlphabet.ts`
- `frontend/client/e2e/catalog-brand-menu.spec.ts`
- `frontend/client/e2e/fixtures/catalog-api.mjs`

Контракт, документация и задачи:

- `docs/API.md`, `docs/openapi.json`, `docs/OPENAPI_MIGRATION_PLAN.md`
- `docs/ADMIN_BRANDS_REDESIGN.md`, `docs/CLIENT_UI_KIT.md`
- `docs/superpowers/specs/2026-11-17-catalog-brand-alphabet-design.md`
- `docs/superpowers/plans/2026-11-17-catalog-brand-alphabet.md`
- этот отчёт, `tasks/TODO.md`, `tasks/DONE.md`

## Проверки

| Область | Результат |
| --- | --- |
| Backend `composer test` | 846 passed, 8 PostgreSQL-only skipped, 9422 assertions |
| PostgreSQL: связанные feature-тесты | 46 passed, 274 assertions |
| PostgreSQL: новый набор, включая migration down/up | 6 passed, 71 assertions |
| Backend Pint / architecture / Composer validate | PASS, прежние complexity warnings |
| Backend PHPStan | PASS, 575 файлов |
| OpenAPI tooling tests / Redocly / conventions / compatibility | PASS; версия спецификации v22.0 по политике строгих response schemas; rollout описан в OPENAPI_MIGRATION_PLAN |
| Admin lint / format:check / build | PASS; прежнее предупреждение chunk >500 kB |
| Admin полный unit-набор | 322/322 PASS, 64 файла |
| Admin E2E brandCatalogMenu / brandOverview / brandDocuments / brandSearch | 20/20 PASS |
| Client `npm run typecheck` / `npm run format:check` / `npm run build` | PASS |
| Client `npx playwright test catalog --reporter=line` | 30/30 PASS, включая 7 новых сценариев меню |
| Визуальная проверка и независимый UI Design Guard | PASS: Client и Admin 320/640/768/1024/1280 px |
| Доступность | Client axe, normal/reduced motion, hover/click/touch, Tab/Enter/Space/Escape, отсутствие hidden focus; Admin axe с действующей политикой утверждённой палитры |
| Короткий экран / обновление данных | PASS: 1280×400, клавиатура до последнего бренда, обновление названия/группы и очистка панели |
| `git diff --check` | PASS |

TDD: новые backend и Admin тесты сначала падали из-за отсутствия поля,
публичного списка и переключателя; первый Client тест — из-за отсутствия
SSR-ссылок. После реализации пройдены проверки. Найденный UI Guard рывок
закрывающейся панели воспроизведён тестом геометрии и устранён сохранением
позиции каждой группы до конца общей анимации. Отдельно устранена неверная
автоматическая отметка всех одноимённых маршрутов NuxtLink как текущих:
`aria-current` теперь зависит от выбранного brand query.

Browser-проверки использовали изолированные API fixtures и установленный
Chromium (`PLAYWRIGHT_BROWSERS_PATH`), без изменения реальных брендов.
Windows sandbox не позволял завершить production build/Playwright teardown;
успешные проверки выполнены вне песочницы после автоматического review.
Backend full-suite сначала исчерпал стандартный лимит PHP 128 MB в прежнем
тесте архива изображений; повторный прогон прошёл с временным лимитом 1 GB
только в тестовом контейнере, без изменений project configuration.

Оставшийся неуспешный gate: `composer audit --locked --no-cache` сообщает
существующую low-severity уязвимость Laravel CVE-2026-128083 /
GHSA-63hm-g96r-2m2q. Зависимости в рамках этой задачи не менялись.
Полные Admin/Client E2E вне затронутого каталога/брендов и macOS browser
прогоны не выполнялись. Linux-проверки быстрого переключателя описаны выше.

Локальные артефакты: `.tmp/catalog-brand-client-build.log`,
`.tmp/catalog-brand-client-regression.log`, Client скриншоты
`.tmp/catalog-brand-menu/brand-menu-{width}.png`, Admin скриншоты
`.tmp/brand-catalog-menu/form-{width}.png`.
