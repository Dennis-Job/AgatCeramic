# Подробный список брендов в Admin

Дата: 2026-10-14. Страница `/brands` оформлена по образцу подробного списка категорий.

## Результат

- Вместо таблицы отображаются разделённые строки с логотипом, названием, slug,
  статусом, страной, полным описанием и ссылками на документы. Длинные значения
  переносятся; на узком экране данные и действия располагаются последовательно.
- Логотип вписан целиком через существующий `UiImagePreview`; отсутствие или ошибка
  изображения используют общий fallback. Пустые страна, описание и документы
  обозначены явно. Сохранены пагинация и подтверждение удаления.
- Создание и редактирование используют оформление категорий: ширина 768 px,
  ограничение высоты `90dvh`, закреплённые header/footer, прокручиваемое содержимое,
  общий `UiDialogFooter` с пояснением и действиями.
- Общий `MediaImageEditor` позволяет загрузить логотип, выбрать существующий,
  убрать/восстановить выбор и открыть полноэкранный просмотр. Для брендов добавлены
  собственные доступные подписи preview/remove без изменения подписей категорий.
- Незагруженный выбранный файл блокирует сохранение; загрузка и сохранение
  блокируют закрытие. После Escape из preview фокус возвращается к изображению.
  Страна выбирается через портальное меню: оно не обрезается scroll-контейнером,
  Escape закрывает меню, сохраняя форму открытой.
- Права `catalog.manage` и `media.manage`, API и бизнес-данные сохранены.
  Страна может оставаться неуказанной. Документы бренда по-прежнему выбираются
  из медиатеки. Новые migrations и backend-изменения не требуются.

## Изменённые файлы

- `frontend/admin/src/features/brands/components/BrandsList.vue`
- `frontend/admin/src/features/brands/components/BrandsWorkspace.vue`
- `frontend/admin/src/features/brands/components/BrandFormDialog.vue`
- `frontend/admin/src/features/brands/composables/useBrandForm.ts`
- `frontend/admin/src/features/media/components/MediaImageEditor.vue`
- `frontend/admin/e2e/brandOverview.spec.ts`
- `frontend/admin/e2e/adminBaselineApi.ts` — fixture дополнен существующими полями
  `logo_id`, `logo`, `documents` из контракта Brand.
- `frontend/admin/e2e/catalog.spec.ts` — поиск и выбор страны учитывают портал.
- `frontend/admin/e2e/sellerWorkspaces.spec.ts` — проверка старой таблицы заменена
  проверкой списка из 25 записей, переноса длинных значений и клавиатурных действий
  на девяти ширинах.
- Визуальные эталоны страницы брендов и запись в `tasks/DONE.md`.

## Проверка

- `npm run lint`, `npm run format:check`, `npm run build` — PASS.
- `npm run test:unit` — 125/125 PASS.
- Production E2E брендов и регрессия изображений/деталей категорий — 25/25 PASS.
- Полный Windows production E2E — 533 PASS, одна устаревшая проверка ожидала
  табличный region брендов. После её замены новым list/keyboard-сценарием
  повтор всех новых проверок брендов и заменённой проверки — 6/6 PASS.
  Production-код после полного прогона не менялся; второй полный прогон не выполнялся.
- Windows и Linux PNG страницы `/brands` просмотрены и приняты независимым
  UI Design Guard. Windows и Linux visual regression после принятия — PASS.
- Независимый UI Design Guard — accepted. Замечание об обрезании меню страны и
  закрытии формы по Escape исправлено и повторно проверено в production-сборке.
- Проверены список и формы на 320/640/768/1024/1280 px, список дополнительно
  на 1440/1920/2560 px; axe с действующими исключениями утверждённой палитры.
  Цвета и typography не менялись.

Unit и браузерные проверки запускались вне Windows-песочницы: изоляция
перенаправляет путь браузеров и вызывает EPERM при записи временного кэша Vitest.
Сборка сохраняет прежнее предупреждение Vite о JS chunk больше 500 kB.
API проверен по существующему `BrandResource`; контракт и миграции не менялись.

Визуальное ревью выполнялось по контролируемым API fixtures, включая логотип,
отсутствующее изображение и длинный контент. Рабочие записи каталога не менялись.
Полный ручной screen-reader audit и проверка на macOS не выполнялись.
Darwin-эталон старой таблицы не заменялся PNG другой ОС: его следует пересоздать
и проверить на macOS при следующем платформенном прогоне.

## Исправление обрезания фокуса, 2026-10-14

В формах создания и редактирования брендов и категорий scroll-body обрезал
внешний halo активного поля: его край выступал на 4 px за область прокрутки.
В `BrandFormDialog.vue` и `CategoryFormDialog.vue` добавлены padding и
компенсирующий отрицательный margin, равные `--admin-focus-halo-width`.
Положение полей и утверждённые цвета/ширина подсветки сохранены; прокрутка
остаётся внутри формы, header/footer закреплены.

Добавлен `frontend/admin/e2e/catalogDialogFocus.spec.ts`: четыре сценария
создания/редактирования обоих справочников на 320/640/768/1024/1280 px,
с проверкой свободного места для halo имени, slug, описания и checkbox.
Проверки: lint, format, build — PASS, unit 125/125, production E2E 46/46.
Независимый UI Design Guard — accepted после просмотра шести PNG и проверки
геометрии controls; blocking findings нет.
API и migrations не менялись. Скриншоты проверки находятся в
`.tmp/catalog-dialog-focus/` и не входят в Git.

## Поиск брендов, 2026-10-14

На `/brands` добавлен поиск по названию или slug по всему справочнику.
Поле переиспользует `UiInput searchable`, имеет доступное имя «Поиск брендов»
и находится в `form role="search"`, как поиск товаров. Запрос отправляется
автоматически через 350 ms после изменения текста, пробелы по краям удаляются.
Изменение или очистка запроса возвращает первую страницу. Пагинация,
перезагрузка после сохранения и удаления сохраняют текущий запрос.

Пустой результат отличается от пустого справочника. При ошибке загрузки
показаны уведомление и повторная загрузка, результаты не выдаются за
отсутствие совпадений. Запоздавшие ответы не заменяют новый результат;
отложенный запрос отменяется при уходе со страницы.

`GET /api/v1/admin/brands` поддерживает необязательный `search` (до 255 символов)
и валидированный `page`. Поиск регистронезависимый по `name` или `slug`, включая
кириллицу на PostgreSQL. `%`, `_` и `!` ищутся как обычный текст; SQL использует
связанные параметры. Пагинация сохраняет размер 25 и поиск в ссылках.
`ListBrandsRequest` проверяет доступ до валидации, `BrandQuery` выполняет выборку
с загрузкой логотипа/документов. OpenAPI обновлён, миграции не требуются.

Изменены `BrandsWorkspace.vue`, `BrandsList.vue`, `useBrandCatalog.ts`,
`features/brands/services/brands.ts`, `BrandController.php`; добавлены
`ListBrandsRequest.php`, `BrandQuery.php`, `brandSearch.test.ts` и
`brandSearch.spec.ts`. Расширен `BrandManagementTest.php`, обновлены OpenAPI
и четыре визуальных состояния брендов на Windows/Linux.

Проверки backend: полный SQLite — 410 тестов/5392 assertions, один ожидаемый
skip PostgreSQL-only проверки кириллицы; PostgreSQL brands — 9/85,
полный PostgreSQL feature — 329/5081. Pint, PHPStan, architecture,
Composer validate/audit, OpenAPI lint, compatibility с HEAD и 5 tooling tests — PASS.
Тестовые PostgreSQL-контейнеры изолированы; рабочая база не менялась.
Первый PostgreSQL-прогон выявил недостающие storage-каталоги временного runner;
исправлена подготовка окружения, повтор полного прогона прошёл.

Frontend unit — 129/129 PASS. Проверены поиск/очистка/пагинация, пустой результат,
ошибка/retry, запоздавшие ответы и адаптивность на 320/640/768/1024/1280 px.
Связанные production E2E Windows — 30/30 PASS; visual regression Linux — 4/4 PASS.
Сборка, lint и форматирование — PASS. Независимый UI Design Guard принял
поле поиска и четыре состояния Windows/Linux; blocking findings нет.
Утверждённая палитра и shared UI не менялись. macOS не проверялась;
Darwin-эталоны требуют отдельного платформенного обновления.

## Поиск рядом с добавлением бренда, 2026-10-14

Форма поиска перемещена в существующий слот `PageHeader #actions`, слева от
кнопки «Добавить бренд». От 640 px поле имеет ширину 288 px и располагается
рядом с кнопкой; на узком экране controls переносятся последовательно.
Доступное имя, search landmark, очистка и логика фильтрации сохранены.
Shared UI, backend, API и migrations в этом уточнении не менялись.

Изменён `BrandsWorkspace.vue` и четыре визуальных состояния страницы брендов
для Windows/Linux. Проверены ширины 320/640/768/1024/1280 px и дополнительно
1440/1920/2560 px. Build/lint/format PASS, unit 129/129 PASS.
Production E2E Windows 21/21 PASS, Linux visual regression 4/4 PASS.
Независимый UI Design Guard принял расположение и все восемь обновлённых PNG.
Linux Chromium проверял ту же собранную папку `dist`; macOS не проверялась.

## Автоматическая очистка логотипа, 2026-10-14

`BrandManagementService` использует существующий `MediaManagementService::deleteIfUnused`,
как категории. После сохранения `logo_id: null`, замены логотипа или удаления бренда
прежняя запись медиа удаляется, если больше не используется и сотрудник имеет право
удаления медиа. Оригинал и миниатюра удаляются через durable `DeleteStoredFile`
после успешного commit. Снятие фото в несохранённой форме серверный файл не удаляет.

Общие ссылки по ID и URL сохраняют изображение: другие бренды, категории,
баннеры, документы, страницы и контент. Без `media.manage` связь у бренда снимается,
но файл остаётся в медиатеке, как при очистке изображения категории.
Новый логотип при замене сохраняется. Старые orphan-файлы и загрузки из отменённых
форм этой доработкой не удаляются массово.

Изменение бренда, audit, удаление записи медиа и задания очистки находятся
в одной транзакции. Свежий бренд блокируется перед чтением прежнего `logo_id`,
чтобы устаревшая route-bound модель не привела к очистке другого изображения.
Откат или ошибка валидации не удаляет фото; transient transaction failures
используют три попытки, как в сервисе категорий.

Изменённые файлы:

- `backend/app/Services/BrandManagementService.php`.
- `backend/tests/Feature/Api/BrandLogoCleanupTest.php` — 22 новых regression cases.
- `backend/tests/Feature/Api/MediaLibraryTest.php` — lifecycle учитывает автоочистку
  после снятия последней привязки.
- `docs/API.md`, `docs/openapi.json`, этот отчёт и `tasks/DONE.md`.

Проверки:

- Полный native `composer test`: 431 PASS, один ожидаемый PostgreSQL-only skip,
  5543 assertions. Связанные brand/category/media tests: 59 cases,
  447 assertions, тот же один skip.
- PostgreSQL связанные tests: 59/450 PASS, включая кириллицу.
- Полный PostgreSQL feature: 351/5232 PASS. Первый прогон выявил конфликт
  queue DB/backoff временного runner с конфигурационным тестом; настройки
  тестового окружения исправлены, повтор полного прогона прошёл.
- Реальный Redis worker: снятие/замена/удаление бренда, 6 cleanup tasks,
  12 deliveries с повторной доставкой, 0 failed jobs. До commit очередь пуста;
  после worker оригиналы и миниатюры удалены, новое фото сохранено.
- RedisQueueDeliveryTest: 1/84 PASS, штатные retry/backoff и recovery сохранены.
- Pint, PHPStan, architecture, Composer validate и OpenAPI lint/tooling — PASS.
  OpenAPI compatibility с HEAD — PASS; payload, routes и migrations не менялись.
- Независимое backend review: blocking findings нет.

`composer audit --locked` не завершился: Packagist недоступен (curl 28/timeout)
как из native Windows, так и из отдельного Linux-контейнера. Dependencies/lock
не менялись; этот сетевой сбой не выдаётся за успешный audit.

Проверки выполнялись на отдельных тестовых PostgreSQL/Redis и временном storage.
Рабочие изображения и записи каталога не менялись. Для штатной физической очистки
необходим queue worker, для повторного запуска после сбоев — scheduler;
оба сервиса рабочего Compose на момент проверки запущены. UI не менялся,
поэтому дополнительное UI Design Guard-ревью не требуется.

## Загрузка PDF в форме бренда, 2026-10-14

В создании и редактировании бренда блок «Документы бренда» использует существующий
inline upload медиатеки. Сотрудник выбирает PDF до 20 МБ, при необходимости меняет
название и нажимает «Загрузить и выбрать». Загруженный документ автоматически
отмечается; после «Сохранить» его ID включается в `document_ids`. Несколько документов
и выбор существующих PDF поддерживаются прежним механизмом.

Загрузка требует `media.manage`; сотрудник с `catalog.manage` может выбирать
существующие документы. Формат и размер проверяются до запроса, backend продолжает
проверять MIME/размер/права. Ошибка сохраняет выбранный файл для повторной попытки.
Отмена выбора снимает блокировку сохранения. Загрузка логотипа и PDF имеют независимые
состояния: завершение одного файла не снимает блокировку, пока второй остаётся выбранным.
Во время запроса закрытие формы и остальные поля блокируются.

Успешная загрузка создаёт файл в медиатеке; привязка к бренду происходит при сохранении.
Если закрыть форму после загрузки, файл останется в медиатеке. Очистка PDF автоматически
не добавлялась. Backend API/OpenAPI и migrations в этой доработке не менялись.

Изменённые файлы:

- `frontend/admin/src/features/brands/components/BrandFormDialog.vue`.
- `frontend/admin/src/features/media/components/MediaReferenceField.vue`.
- `frontend/admin/src/features/media/composables/useInlineMediaUpload.ts`.
- `frontend/admin/tests/brandDocumentUpload.test.ts` — проверка формата/размера/названия
  и совместных блокировок логотипа/PDF.
- `frontend/admin/e2e/brandDocuments.spec.ts` — создание/редактирование, multipart,
  привязки, ошибка/retry, отмена/reopen, права и адаптивность.
- `frontend/admin/e2e/brandOverview.spec.ts` — уточнён locator загрузки логотипа,
  поскольку в форме теперь два файловых поля.
- `frontend/admin/e2e/containerLayout.spec.ts` — поиск в header проверяется
  на размещение внутри контейнера, а отдельные панели — на всю ширину.
  Устранено устаревшее ожидание полноширинного поиска брендов после переноса к кнопке.
- `docs/API.md`, этот отчёт и `tasks/DONE.md`.

Проверены ширины 320/640/768/1024/1280 px, длинное русское название PDF и доступность
axe на 320/1280 px. Снимки состояний pending/error/success находятся в локальной
`.tmp/brand-documents/`.

Build, lint/format и 133 unit tests — PASS; backend `MediaLibraryTest` — 3/37 PASS.
Полный Windows production E2E: 547 PASS и одна устаревшая проверка полноширинного
поиска в `containerLayout.spec.ts`. После исправления повтор всех container-layout
маршрутов и PDF-сценариев — 25/25 PASS; незакрытых падений нет. Visual baselines
не обновлялись. Linux/macOS в этой доработке отдельно не проверялись.
Независимый UI Design Guard — accepted, blocking findings нет. Ревью включает
реальные screenshots и клавиатурную отмену; screen-reader audit не проводился.

## Оформление загрузки PDF, 2026-10-14

По запросу владельца native file control в документах бренда заменён на тот же
`UiFileDropzone`, который используется для фото. PDF имеет красный `FileText`
и плашку формата; остальные состояния, размеры и focus используют общий UI-kit.
Добавлен вариант `iconTone="red"` с существующими `error-500`/`error-50` и
`error-100`/`error-700`; утверждённые tokens не менялись.

Поддерживаются нажатие, клавиатурный выбор и перетаскивание файла. Выбранное имя
показывается целиком с переносом. После отмены фокус возвращается на dropzone.
Лимит PDF 20 МБ, permissions, проверка формата, retry и logo/PDF guards сохранены.
Загрузка документа оформлена в существующем `MediaReferenceField`; image branch
продолжает использовать прежний файловый control. Backend/API/migrations не менялись.

Изменены `UiFileDropzone.vue`, `MediaReferenceField.vue`, `UI_KIT.md`,
`e2e/brandDocuments.spec.ts`, этот отчёт и `tasks/DONE.md`.
Связанные браузерные проверки: 7/7 PASS, включая создание/редактирование,
keyboard picker/drop/cancel-focus, loading/disabled, retry, права и все пять ширин.
Снимки `.tmp/brand-documents/dropzone-{320,1280}.png` и pending/error/success
просмотрены исполнителем и независимым UI Design Guard — accepted.
Build, lint/format, unit 133/133 и полный Windows production E2E 549/549 — PASS.
Visual baselines сохранены без обновления. Проверены все Admin routes и UI-kit;
Linux/macOS и реальный screen-reader audit отдельно не выполнялись.
