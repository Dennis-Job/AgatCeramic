# Редактируемые ссылки подвала

Запрос владельца 2026-10-28: независимая навигация подвала, колонка «Мы в
соцсетях» по exampleSite.html, текст со ссылкой справа в нижней строке.

Вход: «Контент → Общее оформление → Подвал». Все три списка позволяют
добавлять, удалять и переставлять ссылки. Навигация подвала не меняется вслед
за шапкой после сохранения. Для старого JSON без navigation_links сохраняется
прежняя плоская проекция шапки; явный [] скрывает колонку. Соцсети не заполнены
демонстрационными адресами. Справа в нижней строке — текст и необязательный URL.
Без URL остаётся текст. Изменения сохраняются в черновик, затем публикуются
отдельной кнопкой. На публичном сайте и в Nuxt preview один SiteFooter.

Уточнение владельца: hover нижней ссылки совпадает со ссылками колонок —
общие transition color/transform 0.25 s, ink и translateX(4px), без подчёркивания.
На узком экране кликабельная ширина следует за текстом, общий focus-visible
и reduced motion сохранены.
После уточнения: Client build/typecheck/Prettier, профильные E2E 2/2 и
независимый UI Design Guard PASS; evidence `client-hover-build.log`,
`client-hover-e2e.log`, `hover/**` внутри `.tmp/footer-links/`.

Основные файлы:

- Backend: `Support/SiteFooter.php`, `UpdateHomePageRequest.php`,
  `HomePageManagementService.php`, queries `SiteAppearanceQuery`,
  `HomePageContentQuery`, `PublishedPageQuery`; `FooterLinksTest.php`.
- Admin: `FooterLinksEditor.vue`, `SiteChromeEditor.vue`, `useAppearance.ts`,
  `services/appearance.ts`, `appearance.types.ts`, `validation/footer.ts`,
  общий `linkError` в `validation/navigation.ts`; `footerLinks.test.ts`,
  `e2e/appearance.spec.ts`.
- Client: `SiteFooter.vue`, `services/homePage.ts`, `types/homePage.ts`,
  `layouts/default.vue`, `pages/preview/[slug].vue`, E2E fixture и
  `footer-links.spec.ts`.
- Контракт/правила: `API.md`, `openapi.json` v11.0,
  `OPENAPI_MIGRATION_PLAN.md`, `CLIENT_UI_KIT.md`, `CONTENT_WORKSPACE_UX.md`.

Данные остаются в существующем JSON; миграций БД нет. Старые writers сохраняют
новые поля при их отсутствии в запросе. Backend самостоятельно проверяет
адреса, frontend фильтрует их через safeLink. Пустой explore_links приведён к
существующему контракту minItems=0. Максимум навигации 256 сохраняет прежние
16×16 подпунктов без обрезки; остальные списки — до 12 ссылок.

Проверки:

- Backend: 656 passed, 1 существующий PostgreSQL-only skip под SQLite,
  7264 assertions; профильные footer tests 39/214 assertions.
- Pint, PHPStan, architecture guard, Composer strict validate, OpenAPI lint,
  conventions, 5 tooling tests, compatibility v11.0, 180 AJV fixtures PASS.
- Admin: 271 unit tests PASS, ESLint, Prettier, build PASS;
  production E2E оформления 27/27 PASS, включая добавление/перестановку/удаление,
  ошибку адреса без потери ввода, reload, отдельную публикацию, axe и пять ширин.
- Client: typecheck, Prettier и production build PASS. В sandbox build упирался
  в EPERM выходных каталогов; обычная сборка вне sandbox успешно завершилась.
- Client профильные footer/navigation E2E 9/9 PASS; полный прогон 81/81 PASS,
  записан в `.tmp/footer-links/client-e2e-full.log`.
- Независимый UI Design Guard: blocking findings отсутствуют; просмотрены
  Admin и Client на 320/640/768/1024/1280 px, включая состояния и референс.
- Живой Docker Admin и сохранённый Nuxt preview открыты; dev-серверы
  Admin/Client перезапущены для сброса устаревшего Vite cache. Данные сайта не
  публиковались и не заменялись тестовыми.

Evidence: `.tmp/footer-links/admin-e2e-verified.log`, `admin-verified/**`,
`client-e2e.log`, `client/**`, `client-build-native.log`, `admin-format.log`.
Composer audit не подтвердился: Packagist security feed завершился timeout;
зависимости этой задачей не менялись. Существующее перекрытие sticky-действий
уведомлением и hover-меню не менялось; E2E закрывает уведомление и убирает
указатель с шапки перед responsive-проверкой.
