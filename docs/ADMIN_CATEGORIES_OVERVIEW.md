# Обзор категорий в Admin

Дата: 2026-10-07. Реализован выбранный владельцем вариант 3: раскрытая иерархия с характеристиками каждой категории на странице `/categories`.

## Поведение

- Каждая категория сразу показывает название, slug, порядок, префикс SKU, признак «Родительская»/«Обычная», видимость, родителя и названия непосредственных дочерних категорий.
- Назначенные группы раскрыты. В каждой видны назначенные характеристики, единицы измерения, отметки «Обязательная» и «В фильтрах». Обязательность берётся из назначения конкретной категории, участие в фильтрах — из определения характеристики.
- Пустая назначенная группа остаётся видимой. Характеристики без группы показываются отдельно; назначение характеристики из неназначенной группы также не теряется.
- По запросу владельца поиск заменён выпадающим списком созданных категорий, включая скрытые. «Все категории» возвращает полный обзор. Выбор по ID сохраняет предков и потомков, одноимённые категории не смешиваются. Названия детей в меню дополнены родителем. Кнопки родителя и детей сбрасывают выбор, прокручивают страницу и переводят фокус. Удаление выбранной категории возвращает фильтр к «Все категории». Поиск доступен внутри раскрытого меню.
- Существующие формы создания, редактирования и настройки, окно подробностей и подтверждение удаления сохранены. Ошибка удаления отображается внутри активного диалога и не скрывает обзор после отмены. После сохранения назначений обзор обновляется, в том числе после частичного успеха двух операций назначения.
- Ошибки загрузки не выдаются за отсутствие данных. Начальная загрузка и обновление назначений имеют повторную загрузку. Устаревшие ответы не заменяют новые сведения.
- Утверждённые цвета, шрифты, shared-компоненты и tokens сохранены. На узких экранах группы располагаются друг под другом, длинные названия переносятся.

## API и данные

Добавлен `GET /api/v1/admin/categories/overview`: вложенное дерево с `attributes` и `attribute_groups` у каждого узла, включая потомков. Пустые назначения представлены массивами `[]`. Авторизация — существующий `CategoryPolicy::viewAny` через Gate; роли на frontend не добавлялись.

Отдельные `CategoryOverviewResource`, `CategoryOverview` и `CategoryOverviewResponse` описывают проекцию обзора. Старый `/admin/categories/tree` и закрытая схема `Category` сохранены. Проверка совместимости с `HEAD:docs/openapi.json` проходит.

Вход на страницу требует одного запроса обзора; связи загружаются заранее для всех категорий. Нет двух запросов на каждую категорию и необходимости повышать API rate limit. Повторная загрузка назначений использует существующие endpoints только для изменённой категории.

Миграции и новые зависимости не требуются. Данные рабочего каталога при проверке не менялись.

## Проверки

| Проверка                                                   | Результат                                                 |
| ---------------------------------------------------------- | --------------------------------------------------------- |
| Admin production build / TypeScript                        | passed                                                    |
| Admin ESLint                                               | passed                                                    |
| Prettier изменённых 15 frontend-файлов                     | passed                                                    |
| Новые unit-тесты обзора                                    | 4 passed                                                  |
| Полный Admin unit                                          | 103 passed, 5 прежних failures                            |
| Production E2E обзора, macOS и Linux                       | по 13 passed                                              |
| Существующие catalog flows для категорий                   | 3 passed                                                  |
| Baseline состояний и адаптивности категорий, macOS и Linux | по 5 passed; 8 эталонов приняты независимым ревьюером     |
| Полный backend `composer test`                             | 374 passed, 5140 assertions                               |
| Категории/назначения на PostgreSQL                         | 16 passed, 112 assertions; safety gate тестовой БД passed |
| Pint изменённых backend-файлов                             | passed                                                    |
| PHPStan                                                    | passed, последовательный запуск с `--debug`               |
| Architecture guard                                         | passed, без блокирующих нарушений                         |
| Composer validate / audit                                  | passed, известных уязвимостей нет                         |
| OpenAPI lint / conventions / tooling                       | passed, 5 tooling tests                                   |
| OpenAPI backward compatibility                             | passed                                                    |
| Git diff whitespace                                        | passed                                                    |
| Независимый UI Design Guard                                | accepted, blocking findings отсутствуют                   |

Проверены реальные данные локального Admin, родитель «Сантехника» и пять детей, назначенные группы и фильтры; новые записи каталога не создавались. В браузерных тестах записи и сохранение моделируются контролируемым API fixture.

## Ограничения проверок

Пять unit-failures воспроизведены на исходном `HEAD` в отдельной копии: `authCard.test.ts` (старое ожидание `shadow-dialog`), `catalogComponents.test.ts` (старый selector `bg-error-600`), `uiBadge.test.ts` (старый blue-light цвет), два `uiKitShowcase.test.ts` (inventory компонента и tokens). Задача их не меняет.

Полный `format:check` сообщил о 197 несоответствиях, включая генерируемые файлы `.tmp`; итоговые изменённые исходники проверены отдельно. Vite предупреждает о размере общего bundle свыше 500 kB.

Контраст утверждённой палитры не изменён: например, active-badge `success-500`/`success-50` имеет 2.48:1 при требуемом axe 4.5:1. Для категорий axe исключает только `color-contrast`; остальные serious/critical проверки прошли. Это не утверждение о полном соответствии WCAG. Дополнительный старый E2E `category action icons` ожидает delete-hover `error-100`, тогда как неизменённый `UiButton` использует `error-50`; этот несвязанный тест остаётся с устаревшим ожиданием. Полный Admin E2E по всем разделам не заявляется пройденным.

## Изменённые файлы

- Backend: `app/Http/Controllers/Api/V1/Admin/CategoryController.php`, `app/Http/Resources/Catalog/CategoryOverviewResource.php`, `app/Services/CategoryManagementService.php`, `routes/api/v1/admin.php`, `tests/Feature/Api/CategoryManagementTest.php`.
- Контракт: `docs/openapi.json` — новый endpoint и две самостоятельные схемы.
- Admin feature: `src/features/categories/components/{CategoriesList,CategoriesWorkspace,CategoryAttributeOverview}.vue`, `composables/{useCategoriesWorkspace,useCategoryOverview}.ts`, `services/categories.ts`, `types/category.types.ts`.
- Unit: `frontend/admin/tests/categoryOverview.test.ts`.
- E2E: `frontend/admin/e2e/{categoryOverview.spec,categoryOverviewApi,adminBaseline.spec,adminBaselineApi,catalog.spec,catalogApi,sellerWorkspaces.spec}.ts`. Fixtures поддерживают новый endpoint; табличная проверка категорий заменена отдельным покрытием раскрытого обзора.
- Восемь `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-categories{,-loading,-empty,-error}-chromium-{darwin,linux}.png`.
- Отчёты: этот документ и [design-qa.md](../design-qa.md); статус задачи в `tasks/DONE.md`.

## Уточнение: список категорий

Изменены `CategoriesWorkspace.vue`, `CategoriesList.vue`, `categoryOverview.test.ts`, `categoryOverview.spec.ts` и восемь эталонов только `/categories`. Используется существующий `UiSelect` с меню в `body`, клавиатурой и Escape. Открытое меню проверено на 320/640/768/1024/1280 px с длинными названиями; serious/critical axe без ранее зафиксированного color-contrast не обнаружены. Backend, API, tokens и shared UI в этом уточнении не изменялись. Повторены build, ESLint, scoped Prettier, 4 unit, 13 E2E обзора и 5 baseline на macOS/Linux. UI Design Guard accepted.
