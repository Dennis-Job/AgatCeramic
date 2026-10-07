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

## Исправление прокрутки форм, 2026-10-07

В создании и редактировании использовалась прокрутка всей формы со sticky footer
и отрицательным нижним отступом. Даже на максимальном scrollTop поле порядка
сортировки частично перекрывалось панелью действий. Регрессионный тест на
исходном компоненте подтвердил перекрытие на 6 px при viewport 1280 × 600.

В `CategoryFormDialog.vue` форма ограничена 90dvh и разделена flex-layout на
заголовок, отдельную прокручиваемую область полей и footer. `min-h-0` позволяет
области полей сжиматься; `overscroll-contain` удерживает прокрутку внутри неё.
Заголовок и действия остаются доступны, последнее поле полностью помещается
над footer. Существующие UiDialog, UiDialogFooter, controls и палитра сохранены.

Изменены компонент формы, `e2e/categoryOverview.spec.ts`, этот отчёт и
`tasks/DONE.md`. Backend, API-контракт и миграции не затронуты.

Проверки: ESLint, TypeScript/build, Prettier изменённых исходников и 4 unit
категорий прошли. Все 23 E2E обзора прошли, включая 10 новых сценариев создания
и редактирования на ширинах 320/640/768/1024/1280 px при высоте 600 px:
колесо прокручивает поля, последнее поле выше footer, кнопки внутри viewport,
Escape закрывает окно и возвращает фокус. После контрольного запуска исходной
формы исправленная сборка восстановлена и все 10 регрессионных сценариев
повторно прошли. Реальное редактирование в локальном браузере проверено без
сохранения данных. Сохраняется прежнее предупреждение Vite о размере bundle.

Независимый UI Design Guard `/root/ui_design_guard`: **accepted**, просмотрены
исходники и все 10 PNG `frontend/admin/.tmp/category-{create,edit}-scroll-{width}.png`.
Блокирующих замечаний нет. Ревью снимков использует mock media и короткие
заголовки; реальные данные дополнительно проверены исполнителем.

## Единый стиль формы и удаление документов, 2026-10-07

По уточнению владельца из создания и редактирования удалён блок документов.
Ширина окна увеличена с 672 до 768 px. Название/slug и родитель/порядок
сортировки используют одинаковые двухколоночные ряды и штатную высоту controls
36 px; на узких экранах поля идут последовательно. Выровнен отступ выбора
изображения, убран лишний верхний отступ флажков и конфликтующий размер
textarea. Описание сохраняет многострочный UI-kit control. Кнопки собраны в
один ряд и на мобильном экране.

При проверке имени из 255 русских символов на 320 × 600 прежний несжимаемый
заголовок уменьшал высоту полей до 0 px. Заголовок теперь «Редактирование
категории», полное имя сохраняется в поле. Меню родителей использует штатный
`teleport-menu`, чтобы варианты не обрезались скроллером. В реальном каталоге
подтверждена доступность последнего пункта «Сантехника».

Backend, контракт, миграции и общие primitives/tokens не изменены. Удалён
только UI выбора документов; прежние `document_ids` сохраняются при
редактировании, существующие связи автоматически не очищаются.

Изменены `CategoryFormDialog.vue`, `CategoryMainSection.vue`,
`composables/useCategoryForm.ts`, `e2e/categoryOverview.spec.ts`,
`e2e/categoryOverviewApi.ts`, этот отчёт и `tasks/DONE.md`.

Build/TypeScript, ESLint, scoped Prettier и 23 E2E прошли. Десять form-сценариев
проверяют отсутствие документов, общий ряд действий, геометрию полей,
прокрутку, полную видимость порядка сортировки, Escape/focus и последний
вариант меню на пяти ширинах; edit fixture содержит имя из 255 символов.
Modal axe не обнаружил serious/critical нарушений при прежнем адресном
исключении `color-contrast`. Полный unit-набор: 103 passed, те же 5 прежних
failures, перечисленные выше. Сохраняется предупреждение Vite о bundle.

UI Design Guard `/root/ui_design_guard`: **accepted**, просмотрены 20 PNG
форм и меню и реальная форма `.tmp/category-modal-style-real.png`.
Блокирующих замечаний нет. Неблокирующее ограничение fixture: edit menu
содержит только «Без родителя»; create проверяет два варианта, реальный
двухвариантный список дополнительно проверен в браузере.

## Загрузка изображения в форме категории, 2026-10-07

В создание и редактирование подключён существующий inline uploader
`MediaReferenceField`. Менеджер выбирает JPEG/PNG/WebP, указывает название
файла и описание изображения, нажимает «Загрузить и выбрать». Успешная
загрузка сразу выбирает изображение и показывает preview; связь с категорией
закрепляется кнопкой «Сохранить». Переход в медиатеку не требуется.

Загрузка требует прежнего permission `media.manage`. Пока выбранный файл
ожидает загрузки, Save и submit через Enter заблокированы; файл можно отменить.
Во время запроса блокируются закрытие, Escape и отмена окна. При ошибке файл
и метаданные сохраняются для повторной попытки. Upload ждёт первоначального
списка медиа, а retry списка недоступен во время upload: поздний GET не может
стереть preview нового файла. Сообщение успеха уточняет сохранение категории;
остальные потребители общего загрузчика сохраняют прежний текст.

Используется существующий `POST /api/v1/admin/media` и прежний `image_id` в
payload категории. API-контракт, backend и миграции не менялись. Рабочие
данные не создавались: upload/save проверены через API fixtures; реальное
окно открыто в браузере, снимок `.tmp/category-image-upload-real.png`.

Проверки: build/TypeScript, полный ESLint и scoped Prettier passed;
`categoryImages.spec.ts` — 10 passed, `categoryOverview.spec.ts` — 23 passed.
Проверены create/edit upload+save, pending/in-flight guards, валидация alt,
422/retry, отмена выбранного файла, права, deferred GET и responsive
320/640/768/1024/1280 px. Кнопки загрузки полностью видимы после прокрутки;
modal axe без serious/critical при прежнем исключении `color-contrast`.
Существующий appearance-сценарий pending/in-flight logo upload также passed.
Полный unit ранее в этой задаче: 103 passed и 5 прежних failures выше.
Content-blocks upload/retry дошёл до старого contrast-сбоя axe на неизменённых
цветах success (2.48:1) и danger (3.75:1); этот E2E не считается прошедшим.
UI Design Guard `/root/ui_design_guard`: **accepted**, обновлённые pending
PNG на пяти ширинах и create/edit success приняты, blocking findings нет.

Изменены `CategoriesWorkspace.vue`, `CategoryFormDialog.vue`,
`CategoryMainSection.vue`, `useCategoryForm.ts`, `MediaReferenceField.vue`,
`useInlineMediaUpload.ts`; добавлен `e2e/categoryImages.spec.ts`;
обновлены этот отчёт и `tasks/DONE.md`.

## Компактный блок изображения и полноэкранный просмотр, 2026-10-07

По новому запросу владельца постоянный select заменён чекбоксом
«Без изображения». Он убирает `image_id` и отменяет pending файл; снятие
отметки возвращает прежнее изображение в текущем окне. Выбор существующих
файлов сохранён за кнопкой «Выбрать из загруженных», список раскрывается по
требованию. После выбора фокус возвращается на эту кнопку.

`MediaImageEditor` использует прежние media composables и Seller UI-kit.
Квадратный preview 128 px сохраняет полное изображение через `contain`.
Рядом на ширинах от 640 px находится `UiFileDropzone`, как у фото товара;
на узком экране элементы идут столбцом. Выбор и drop принимают один
JPEG/PNG/WebP до 10 МБ; название и описание доступны после выбора файла.
Существующие права, обработка ошибок, автоподстановка и guards сохранены.

Клик на выбранном загруженном фото открывает оригинал на весь viewport
без обрезки. Родительский dialog временно suspended; Escape и кнопка закрытия
возвращают фокус на thumbnail. Успешное уведомление убирается при открытии
просмотра, сообщения ошибок формы скрываются до его закрытия; ошибка самого
изображения показана inline и не перекрывает закрытие. Только этот viewer
имеет точечное исключение из ограничения dialog 90dvh. Палитра не изменена.

Проверки: 35 E2E passed (12 image + 23 overview), build/TypeScript, полный
ESLint и scoped Prettier passed. Проверены реальный drop, неподдерживаемый
формат, checkbox с клавиатуры, отмена pending файла, восстановление выбора,
сохранение `image_id: null`, fullscreen 320/640/768/1024/1280 px, отсутствие
переполнения, Escape/focus и доступность close через `elementFromPoint` и
pointer click. Axe modal/viewer без serious/critical при прежнем исключении
`color-contrast`. Полный unit: 103 passed, 5 прежних failures из раздела
«Ограничения проверок»; Vite предупреждает о размере bundle. Полный E2E
всех разделов не заявляется пройденным.

UI Design Guard `/root/ui_design_guard`: **accepted**, блокирующих замечаний
нет. Проверены empty/pending/uploaded и пять fullscreen PNG; реальная форма
редактирования — `.tmp/category-image-compact-real.png`. Рабочие данные
не менялись; backend, API и миграции не затронуты.

В этой итерации добавлен `features/media/components/MediaImageEditor.vue`,
изменены `CategoryMainSection.vue`, `CategoryFormDialog.vue`,
`useInlineMediaUpload.ts`, `e2e/categoryImages.spec.ts`, этот отчёт и
`tasks/DONE.md`.

## Корзина на фото категории, 2026-10-07

Удалена подпись с названием и ID под выбранным фото. Отдельная кнопка
`UiButton danger-ghost` с `Trash2` появляется в верхнем углу preview при
hover или focus-within; на touch без hover видна постоянно. Она не вложена
в кнопку увеличения и не открывает viewer. Busy/uploading блокируют очистку.

Кнопка использует существующий сброс «Без изображения»: снимает `image_id`,
отменяет pending файл и возвращает фокус на checkbox. Изменение закрепляется
только Save; сам файл медиатеки не удаляется. API и миграции не менялись.

Изменены `MediaImageEditor.vue`, `e2e/categoryImages.spec.ts`, этот отчёт и
`tasks/DONE.md`. Build/TypeScript, полный ESLint, scoped Prettier и diff check
passed. Основной E2E прогон — 36 passed, дополнительный touch — 1 passed.
Проверены отсутствие подписи, hover, keyboard focus/Enter, отсутствие
побочного открытия viewer, возврат фокуса, сохранение `image_id: null` и tap.
Независимый UI Design Guard: **accepted**, hover/touch PNG просмотрены;
реальное окно `.tmp/category-image-remove-real.png` проверено без сохранения
рабочей категории. Палитра сохранена. Unit повторно не запускался для этой
небольшой правки; предыдущие 103 passed / 5 прежних failures описаны выше.

## Очистка файлов изображения категории, 2026-10-07

После сохранения категории без фото, замены фото или удаления самой категории
backend удаляет прежнюю запись медиатеки, если она больше нигде не используется
и у сотрудника есть `media.manage`. Оригинал и миниатюра получают durable
`StorageCleanupTask`; существующий `DeleteStoredFile` удаляет их из storage
после commit через очередь, с retry и восстановлением зависших задач.
Отмена формы и rollback не удаляют файл. Это заменяет прежнее поведение
корзины, при котором снималась только связь с категорией.

`MediaUsageQuery` проверяет категории, бренды, баннеры, документы, `page_media`
и главную страницу. Дополнительно учитываются ссылки на оригинал/миниатюру
в URL баннеров, JSON главной, черновиках, SEO, layout и опубликованных снимках
страниц. Относительные и абсолютные URL сопоставляются по декодированному пути,
без query/fragment; совпадение пути другого хоста консервативно сохраняет файл.
URL-проверки не создают запросов на каждый блок: страницы читаются cursor.
Сохранения баннеров и страниц используют существующую блокировку HomePage
перед записью ссылок, чтобы не попасть между проверкой и удалением медиа.
Управляемые ссылки также защищены FK/блокировками Media.

Старые orphan-файлы и загрузки из несохранённой формы автоматически не
просматриваются. Очистка требует работающего worker/scheduler; ошибки остаются
в durable-задачах для повторной попытки. Произвольный URL, введённый уже после
удаления файла, сохраняет прежний контракт: доступность URL не валидируется.
Новых миграций и изменений payload нет; OpenAPI описывает новый жизненный цикл.
Рабочие файлы каталога при проверках не удалялись.

Проверки: полный backend `composer test` — 399 tests / 5315 assertions;
PostgreSQL feature по cleanup и затронутому контенту — 64 / 549;
пять межпроцессных PostgreSQL concurrency-кейсов — 5 / 20.
Регрессия URL-ссылок сначала воспроизведена в 12 failing cases, после исправления
прошла вместе с очисткой оригинала/миниатюры, shared references, правами,
rollback, validation и идемпотентностью. Реальный Redis worker в отдельном
Compose project — 1 integration test / 84 assertions (after-commit, удаление
файла, retry/backoff, failed job, stale recovery, duplicate delivery).
Изолированные тестовые контейнеры и volume после проверки удалены.
Pint, PHPStan, architecture (без blocking violations), Composer validate/audit,
OpenAPI lint/tooling tests/compatibility и `git diff --check` passed.
Независимое backend review `/root/media_cleanup_review`: **accepted**;
обе найденные проблемы URL references/concurrency устранены. UI не менялся.

В этой итерации добавлены `backend/app/Queries/MediaUsageQuery.php` и
`backend/tests/Feature/Api/CategoryImageCleanupTest.php`; изменены
`CategoryManagementService.php`, `MediaManagementService.php`,
`BannerManagementService.php`, `PageManagementService.php`,
`MediaLibraryTest.php`, `PostgresCatalogConcurrencyTest.php`,
`tests/Support/catalog_concurrency_worker.php`, `docs/openapi.json`,
этот отчёт и `tasks/DONE.md`.

## Локальная ошибка 500 при очистке фото, 2026-10-07

В рабочем Docker backend запрос очистки фото возвращал 500: свежий лог
содержал `SQLSTATE[42P01]: relation "home_pages" does not exist` при
`SELECT ... FOR UPDATE`. Тестовые базы имели полную схему, а в рабочей базе
ожидали две существующие миграции: `2026_09_28_000000_create_home_page_content`
и `2026_09_30_120000_add_page_content_snapshots`.

Перед исправлением сохранён локальный PostgreSQL custom-format backup
`/private/tmp/agatceramic-before-category-cleanup-1791385857842.dump`
с доступом только владельцу (0600). Прочитаны обе миграции и выполнен
обычный `artisan migrate`: обе применены в batch 9, без сброса базы.
Новые миграции или изменения application-кода не требовались.

Проверен тот же PATCH категории 27 с `image_id: null` через настоящий
HTTP kernel и авторизацию текущего сотрудника: HTTP 200. Проверка выполнялась
в транзакции с обязательным rollback; после неё подтверждены исходные
`image_id`, запись Media, состояние файла и количество cleanup tasks.
Открытая форма и рабочее фото не сохранялись/не удалялись. `migrate:status`
подтвердил обе миграции как Ran. Изменены только этот отчёт и `tasks/DONE.md`.

## Центрирование пустого фото категории, 2026-10-07

В `MediaImageEditor.vue` локальный класс `.image-editor-preview` теперь
использует `display: flex` вместо `block`: это сохраняет вертикальное и
горизонтальное центрирование иконки и текста общего `UiImagePreview`.
Размер квадрата, палитра и поведение controls не менялись.

Build/TypeScript, ESLint, scoped Prettier и `git diff --check` passed.
Существующие E2E — 5 responsive cases (320/640/768/1024/1280 px) и
1 compact image/viewer case passed. Проверены empty screenshots и
сохранение поведения загруженного фото, fullscreen и focus return.
Независимый UI Design Guard `/root/ui_design_guard`: **accepted**,
все пять empty PNG просмотрены, замечаний нет. Новые тесты не добавлялись
для точечной CSS-правки. Изменены `MediaImageEditor.vue`, этот отчёт
и `tasks/DONE.md`.
