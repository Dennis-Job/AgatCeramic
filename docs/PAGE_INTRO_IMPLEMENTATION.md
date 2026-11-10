# Вступительные блоки страниц — 2026-11-10

Реализован первый выбранный референс: название и подпись слева, реалистичная
композиция керамических материалов справа. Изменения локальные, без commit.

## Использование

В Admin открыть «Контент → Страницы → [нужная страница] → Настройки →
Вступительный блок страницы». Можно загрузить фото или выбрать его из
медиатеки, добавить alt и вернуть стандартную иллюстрацию. Рекомендация:
1200×800 px, светлый фон. Фото показывается целиком.

Сначала сохранить черновик, проверить Nuxt-предпросмотр и опубликовать страницу.
Незагруженный выбранный файл блокирует сохранение/публикацию; переходы защищены
общим guard. Фото опубликованной версии не меняется при правках черновика.

Общий компонент работает на всех контентных страницах без включённого hero:
каталог, о нас, контакты, сотрудничество и новые страницы. Подготовлены также
иллюстрации услуг rezka/rospis; их доступность определяется публикацией страниц,
новые страницы автоматически не создаются. У новых slug используется нейтральная
композиция до выбора своего фото.

## Изменённые файлы

- Client: `app/components/shared/PageIntro.vue`, `app/utils/pageIntro.ts`,
  `app/types/contentPage.ts`, services `contentPages.ts`/`homePage.ts`,
  `ContentPageBody.vue`/`ContentPageView.vue`, preview types/service.
- Admin: `PageIntroEditor.vue`, `PageBlocksEditor.vue`, `usePageBlocks.ts`,
  `page.types.ts`, совместимый prop подписи у `MediaImageEditor.vue`.
- Backend: Page, ValidatesPageContent, PageResource, PageBlocks,
  PageManagementService, PublishedPageQuery, ContentPreviewQuery,
  MediaUsageQuery. Nullable JSONB migration
  `2026_11_10_000001_add_intro_to_pages.php` применена к локальной базе.
- Тесты: Backend `PageIntroTest.php`; Admin `pageIntro.test.ts`,
  `pageBlocks.test.ts`, `e2e/page-intro.spec.ts`; Client
  `e2e/page-intro.spec.ts` и сценарии `mock-api.mjs`.
- Контракт и документация: API.md, openapi.json, OPENAPI_MIGRATION_PLAN.md,
  CLIENT_UI_KIT.md (UI-030), CONTENT_WORKSPACE_UX.md, design-qa.md и план.

## Проверки

| Проверка | Результат |
| --- | --- |
| Backend regression | 71 тест / 704 assertions PASS; PageIntro отдельно 3 / 43 PASS |
| Backend Pint / PHPStan / architecture | PASS, blocking violations отсутствуют |
| OpenAPI lint / conventions / compatibility | PASS, документированная редакция v20 |
| Client typecheck / форматирование | PASS |
| Client production build | PASS в штатном Docker Linux-контейнере |
| Client content-pages + preview + intro | 19 PASS |
| Client итоговый intro после последней правки | 4 PASS |
| Admin lint / format / build | PASS |
| Admin unit | 319 PASS |
| Admin intro upload/publish/reset/focus и responsive/axe | 2 PASS, пять ширин |
| Admin полный E2E | 724 PASS, 4 screenshot failures на других экранах |
| Независимый UI Design Guard | PASS, P0/P1/P2 не осталось |

Четыре сравнения Admin /products, /roles, /permissions, /settings отличаются
на 211–313 пикселей. В проверенных diff видна растеризация мелкого текста;
функциональные и axe проверки проходят. Эталонные изображения не изменялись.
Результат общего прогона не обозначается полностью зелёным.

Windows Node периодически выдавал EPERM при копировании `.output/public`;
production-сборка выполнена в текущем Linux-контейнере. Windows Playwright
завершал worker, но оставлял mock/preview процессы; после проверки командных
строк остановлены только собственные серверы тестов, runner вернул PASS/exit0.

Реальный каталог проверен в IAB на desktop и 320 px. Реальный Admin требует
сессии сотрудника, поэтому загрузка и публикация проверены production E2E с
изолированными фикстурами; реальные страницы ради теста не публиковались.

## Иллюстрации

Папка: `frontend/client/public/images/page-intro/`. Шесть отдельных WebP
1200×800, quality 84, всего 407 КБ. Сгенерированы встроенным ImageGen,
оптимизированы Sharp без изменения зависимостей приложений.

Общее направление промптов: фотореалистичный натюрморт, тёплый нейтральный
фон #F2F1EE, мягкий естественный свет, керамика и камень, без текста/логотипов/UI.

| Файл | Сюжет промпта | Размер |
| --- | --- | --- |
| catalog.webp | Перекрывающиеся образцы травертина, серого камня и рельефной белой керамики | 68 548 Б |
| about.webp | Керамические образцы с архитектурным чертежом | 72 460 Б |
| contacts.webp | Выставочная стойка с образцами, без выдуманного адреса или здания | 60 394 Б |
| cooperation.webp | Согласованные палитры керамических материалов | 82 508 Б |
| rezka.webp | Точные срезы, обработанные края и узкие полоски керамики | 65 512 Б |
| rospis.webp | Плитка с ботанической росписью, кисть и пигмент | 57 214 Б |

Это тематические иллюстрации, а не фотографии реальных объектов компании.

## Возможное развитие

Такой блок уместен в начале страниц «Доставка», «Оплата», «Дизайнерам» и
брендов/коллекций: короткое введение и связанный с темой визуал перед содержимым.
Для категорий каталога полезны изображения соответствующих материалов.
Подключение к отдельным доменным страницам брендов/коллекций потребует их
собственного источника данных, а не создания контентных дублей.
