# TASK-C002 — результат проверки

Завершено 2026-09-30. Это evidence конкретного запуска, а не новый источник обязательных CI gates.
Актуальные команды и правила проверок находятся в [CI.md](CI.md), контракт — в
[openapi.json](openapi.json), переход клиентов — в [OPENAPI_MIGRATION_PLAN.md](OPENAPI_MIGRATION_PLAN.md).

## Реализовано

- Проверяемые типизированные блоки с уникальными идентификаторами, порядком и флагом включения.
  Черновик хранится отдельно от опубликованного snapshot. Сохранение не обновляет сайт;
  публикация выполняется отдельным POST с `content.manage`. Публичный slug сохраняется до
  публикации переименования. Системные страницы защищены от переименования и удаления.
- Главная перенесена из прежней модели без удаления текста, настроек и медиа. Шапка/подвал
  включены в snapshot главной: их редактирование также требует публикации. Медиа черновика и
  опубликованной версии защищены от удаления. Старый секционный Admin-редактор продолжает работать.
- Admin показывает статус, неопубликованные изменения и отдельное действие публикации;
  запрещает публикацию при несохранённых правках, сохраняет другие локальные разделы формы,
  показывает ошибки и позволяет повторить запрос. `is_published: false` снимает обычную страницу
  с публикации; `true` в create/update больше не публикует её.
- Nuxt рендерит упорядоченные блоки главной, `/contacts`, `/about`, `/catalog` при SSR.
  Контакты используют опубликованные магазины, расписание и реквизиты; каталог — ограниченную
  публичную проекцию активных товаров с ценами, единицами продажи, изображениями и пагинацией.
  Добавлены title/description, canonical, Open Graph и структурированные данные страниц.
- Пустые данные, ошибки страницы/магазинов/каталога и повторная загрузка имеют явные состояния.
  Неопубликованная страница возвращает HTTP 404, ошибка page API — 503. Текст экранируется;
  произвольный HTML не исполняется. Категории без блока фактур не показывают неработающие действия.
- OpenAPI обновлён до v4.0 с планом перехода; адрес API остаётся `/api/v1`.

## Выполненные проверки

| Проверка | Результат |
| --- | --- |
| Backend full suite, SQLite in-memory | 341 тест, 4704 assertions прошли до добавления двух заключительных регрессионных тестов. |
| Backend после заключительных правок: PageBlockContent, HomePageManagement, PageManagement, OpenApi | 58 тестов, 2812 assertions прошли; включены проверки опубликованного slug и изоляции шапки/подвала. |
| Изолированный PostgreSQL 17.9: PageBlockContent, HomePageManagement, PageManagement, Stores | 16 тестов, 157 assertions прошли. Использована отдельная временная БД; контейнер удалён после проверки. |
| PHPStan, architecture guard, Laravel Pint | Прошли; Pint проверил 582 файла. Architecture guard не нашёл блокирующих нарушений. |
| Composer validate/audit | Validate strict прошёл; audit не нашёл уязвимостей. |
| OpenAPI compatibility | Подтверждён разрешённый breaking major release с migration plan. |
| Client typecheck, SSR build, format check | Прошли. |
| Client production E2E | 6 тестов прошли: SSR/SEO/порядок блоков/экранирование, отсутствие hydration errors, пагинация, 404/503/empty/retry, независимые ошибки магазинов/каталога, длинные строки и категории без фактур. |
| Client responsive/accessibility | Четыре маршрута на 320/640/768/1024/1280 px, без горизонтального переполнения; axe — 0 violations. |
| Admin lint, build, unit | Lint и build прошли; 50 unit-тестов прошли. На Windows использован native config loader. |
| Admin production E2E | 3 сценария прошли; публикация, ошибка и повтор, сохранение черновика, снятие с публикации, удаление обычной страницы и открытие страницы по URL. |
| Admin responsive/accessibility | Главная и обычная страница на 320/640/768/1024/1280 px; обычная страница также на 2560 px. Нет горизонтального переполнения; axe — 0 violations. Контрольные снимки пересняты с отключением анимаций. |
| Независимый UI Design Guard | Проверены исходники, 20 клиентских снимков и оба Admin-сценария на пяти ширинах; блокирующих замечаний после исправлений нет. |
| Реальное локальное API | В браузере проверены четыре маршрута: сохранены блоки/слайдер главной, каталог показывает существующие товары; неопубликованные реквизиты/магазины дают честное пустое состояние. |
| Локальная миграция | Применена только новая миграция C002; существующая рабочая БД не сбрасывалась. |
| Repository hygiene / git diff check | Проверены ссылки, запрещённые артефакты и whitespace. |

Команды Admin на этом Windows-окружении:

```sh
npm run lint
npm run build -- --configLoader native
npx vitest run --configLoader native --pool threads --maxWorkers 1 --testTimeout 20000
npx playwright test e2e/homepage.spec.ts e2e/pages.spec.ts --workers=1
```

Глобальный Admin `format:check` выявляет 205 существующих файлов с CRLF; массовая нормализация
несвязанных файлов не выполнялась. Изменённые Admin-файлы проверены Prettier отдельно.
UI Guard оценил SSR/hydration/axe по исходникам и результатам E2E исполнителя; самостоятельный
запуск тестов ревьюером не выполнялся. Визуальные E2E используют синтетические fixtures.

После отправки `2dd0b49` семь из восьми jobs GitHub Actions прошли. В Admin E2E прошли
157 сценариев; четыре visual baseline `/content` не совпали из-за изменённой подсказки
о черновике и отдельной публикации. Обновлены только эти снимки для Linux и Windows.
Сравнение изображений подтвердило, что изменения ограничены текстом подсказки; UI и
pixel-diff threshold не менялись. В обоих окружениях пять профильных baseline/responsive
сценариев прошли при обновлении и повторном запуске без перезаписи снимков. Полный CI
повторяется после отдельного исправляющего коммита.

## Границы и эксплуатация

Редактор произвольных блоков/медиа остаётся `TASK-C003`, отдельная рабочая область общего
оформления — `TASK-C004`, точный авторизованный Nuxt-предпросмотр черновика — `TASK-C005`.
Карточки категорий/товаров, фильтры и сценарии покупки относятся к Phase 10.
Публикация страницы фиксирует редакционные блоки; доменные товары/магазины и отдельно
публикуемые баннеры/слайдеры читаются из своих актуальных API.

Миграция аддитивная. Её `down()` намеренно запрещает уничтожение независимых версий контента.
Откат приложения сохраняет дополнительные поля/таблицу; полный downgrade схемы требует
проверенного восстановления резервной копии до миграции. См. план перехода OpenAPI.

Реализация отправлена в `main` коммитом `2dd0b49` по отдельному запросу пользователя.
Артефакты браузерных проверок находятся в игнорируемых
`.tmp/client-e2e/` и `frontend/admin/test-results/`.

## Изменённые файлы

- [backend/app/Http/Controllers/Api/V1/Admin/HomePageController.php](../backend/app/Http/Controllers/Api/V1/Admin/HomePageController.php)
- [backend/app/Http/Controllers/Api/V1/Admin/PageController.php](../backend/app/Http/Controllers/Api/V1/Admin/PageController.php)
- [backend/app/Http/Controllers/Api/V1/CatalogController.php](../backend/app/Http/Controllers/Api/V1/CatalogController.php)
- [backend/app/Http/Controllers/Api/V1/PageController.php](../backend/app/Http/Controllers/Api/V1/PageController.php)
- [backend/app/Http/Requests/Api/V1/Admin/Concerns/ValidatesPageContent.php](../backend/app/Http/Requests/Api/V1/Admin/Concerns/ValidatesPageContent.php)
- [backend/app/Http/Requests/Api/V1/Admin/StorePageRequest.php](../backend/app/Http/Requests/Api/V1/Admin/StorePageRequest.php)
- [backend/app/Http/Requests/Api/V1/Admin/UpdateHomePageRequest.php](../backend/app/Http/Requests/Api/V1/Admin/UpdateHomePageRequest.php)
- [backend/app/Http/Requests/Api/V1/Admin/UpdatePageRequest.php](../backend/app/Http/Requests/Api/V1/Admin/UpdatePageRequest.php)
- [backend/app/Http/Resources/PageResource.php](../backend/app/Http/Resources/PageResource.php)
- [backend/app/Http/Resources/PublicCatalogCategoryResource.php](../backend/app/Http/Resources/PublicCatalogCategoryResource.php)
- [backend/app/Http/Resources/PublicCatalogProductResource.php](../backend/app/Http/Resources/PublicCatalogProductResource.php)
- [backend/app/Http/Resources/PublishedPageResource.php](../backend/app/Http/Resources/PublishedPageResource.php)
- [backend/app/Models/Page.php](../backend/app/Models/Page.php)
- [backend/app/Queries/HomePageContentQuery.php](../backend/app/Queries/HomePageContentQuery.php)
- [backend/app/Queries/PageBlockContentQuery.php](../backend/app/Queries/PageBlockContentQuery.php)
- [backend/app/Queries/PublicCatalogQuery.php](../backend/app/Queries/PublicCatalogQuery.php)
- [backend/app/Queries/PublishedPageQuery.php](../backend/app/Queries/PublishedPageQuery.php)
- [backend/app/Services/HomePageManagementService.php](../backend/app/Services/HomePageManagementService.php)
- [backend/app/Services/MediaManagementService.php](../backend/app/Services/MediaManagementService.php)
- [backend/app/Services/PageManagementService.php](../backend/app/Services/PageManagementService.php)
- [backend/app/Support/PageBlocks.php](../backend/app/Support/PageBlocks.php)
- [backend/database/migrations/2026_09_30_120000_add_page_content_snapshots.php](../backend/database/migrations/2026_09_30_120000_add_page_content_snapshots.php)
- [backend/routes/api/v1/admin.php](../backend/routes/api/v1/admin.php)
- [backend/routes/api/v1/public.php](../backend/routes/api/v1/public.php)
- [backend/tests/Feature/Api/HomePageManagementTest.php](../backend/tests/Feature/Api/HomePageManagementTest.php)
- [backend/tests/Feature/Api/PageBlockContentTest.php](../backend/tests/Feature/Api/PageBlockContentTest.php)
- [backend/tests/Feature/Api/PageManagementTest.php](../backend/tests/Feature/Api/PageManagementTest.php)
- [docs/API.md](../docs/API.md)
- [docs/CLIENT_UI_KIT.md](../docs/CLIENT_UI_KIT.md)
- [docs/CURRENT_STATE.md](../docs/CURRENT_STATE.md)
- [docs/OPENAPI_MIGRATION_PLAN.md](../docs/OPENAPI_MIGRATION_PLAN.md)
- [docs/TASK_C002_REPORT.md](../docs/TASK_C002_REPORT.md)
- [docs/openapi.json](../docs/openapi.json)
- [frontend/admin/e2e/homepage.spec.ts](../frontend/admin/e2e/homepage.spec.ts)
- [frontend/admin/e2e/pages.spec.ts](../frontend/admin/e2e/pages.spec.ts)
- [frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-content-chromium-linux.png](../frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-content-chromium-linux.png)
- [frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-content-chromium-win32.png](../frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-content-chromium-win32.png)
- [frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-content-empty-chromium-linux.png](../frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-content-empty-chromium-linux.png)
- [frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-content-empty-chromium-win32.png](../frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-content-empty-chromium-win32.png)
- [frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-content-error-chromium-linux.png](../frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-content-error-chromium-linux.png)
- [frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-content-error-chromium-win32.png](../frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-content-error-chromium-win32.png)
- [frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-content-loading-chromium-linux.png](../frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-content-loading-chromium-linux.png)
- [frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-content-loading-chromium-win32.png](../frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-content-loading-chromium-win32.png)
- [frontend/admin/src/features/homepage/components/HomePageWorkspace.vue](../frontend/admin/src/features/homepage/components/HomePageWorkspace.vue)
- [frontend/admin/src/features/homepage/composables/useHomePage.ts](../frontend/admin/src/features/homepage/composables/useHomePage.ts)
- [frontend/admin/src/features/homepage/services/homepage.ts](../frontend/admin/src/features/homepage/services/homepage.ts)
- [frontend/admin/src/features/homepage/types/homepage.types.ts](../frontend/admin/src/features/homepage/types/homepage.types.ts)
- [frontend/admin/src/features/pages/components/PageFormDialog.vue](../frontend/admin/src/features/pages/components/PageFormDialog.vue)
- [frontend/admin/src/features/pages/components/PagesWorkspace.vue](../frontend/admin/src/features/pages/components/PagesWorkspace.vue)
- [frontend/admin/src/features/pages/composables/usePages.ts](../frontend/admin/src/features/pages/composables/usePages.ts)
- [frontend/admin/src/features/pages/services/pages.ts](../frontend/admin/src/features/pages/services/pages.ts)
- [frontend/admin/src/features/pages/types/page.types.ts](../frontend/admin/src/features/pages/types/page.types.ts)
- [frontend/admin/src/features/pages/validation/page.ts](../frontend/admin/src/features/pages/validation/page.ts)
- [frontend/client/README.md](../frontend/client/README.md)
- [frontend/client/app/components/shared/PageState.vue](../frontend/client/app/components/shared/PageState.vue)
- [frontend/client/app/components/shared/SectionHeading.vue](../frontend/client/app/components/shared/SectionHeading.vue)
- [frontend/client/app/components/shared/SiteHeader.vue](../frontend/client/app/components/shared/SiteHeader.vue)
- [frontend/client/app/composables/useContentPage.ts](../frontend/client/app/composables/useContentPage.ts)
- [frontend/client/app/composables/useHomePageContent.ts](../frontend/client/app/composables/useHomePageContent.ts)
- [frontend/client/app/composables/usePublicApiConfig.ts](../frontend/client/app/composables/usePublicApiConfig.ts)
- [frontend/client/app/features/content/components/CatalogListing.vue](../frontend/client/app/features/content/components/CatalogListing.vue)
- [frontend/client/app/features/content/components/ContentPageView.vue](../frontend/client/app/features/content/components/ContentPageView.vue)
- [frontend/client/app/features/content/components/PageBlocks.vue](../frontend/client/app/features/content/components/PageBlocks.vue)
- [frontend/client/app/features/content/components/StoreContacts.vue](../frontend/client/app/features/content/components/StoreContacts.vue)
- [frontend/client/app/features/home/components/HomeCategories.vue](../frontend/client/app/features/home/components/HomeCategories.vue)
- [frontend/client/app/features/home/components/HomeCategoryCard.vue](../frontend/client/app/features/home/components/HomeCategoryCard.vue)
- [frontend/client/app/features/home/components/HomePage.vue](../frontend/client/app/features/home/components/HomePage.vue)
- [frontend/client/app/pages/about.vue](../frontend/client/app/pages/about.vue)
- [frontend/client/app/pages/catalog.vue](../frontend/client/app/pages/catalog.vue)
- [frontend/client/app/pages/contacts.vue](../frontend/client/app/pages/contacts.vue)
- [frontend/client/app/pages/index.vue](../frontend/client/app/pages/index.vue)
- [frontend/client/app/services/contentPages.ts](../frontend/client/app/services/contentPages.ts)
- [frontend/client/app/services/homePage.ts](../frontend/client/app/services/homePage.ts)
- [frontend/client/app/types/contentPage.ts](../frontend/client/app/types/contentPage.ts)
- [frontend/client/app/types/homePage.ts](../frontend/client/app/types/homePage.ts)
- [frontend/client/e2e/content-pages.spec.ts](../frontend/client/e2e/content-pages.spec.ts)
- [frontend/client/e2e/mock-api.mjs](../frontend/client/e2e/mock-api.mjs)
- [frontend/client/package-lock.json](../frontend/client/package-lock.json)
- [frontend/client/package.json](../frontend/client/package.json)
- [frontend/client/playwright.config.ts](../frontend/client/playwright.config.ts)
- [tasks/DONE.md](../tasks/DONE.md)
- [tasks/IN_PROGRESS.md](../tasks/IN_PROGRESS.md)
- [tasks/TODO.md](../tasks/TODO.md)
