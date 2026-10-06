# Ранняя проверка категории Excel-шаблона

Дата: 2026-10-06. Статус: выполнено.

Шаблоны уже содержат маркер `AGAT_CATEGORY_TEMPLATE_V1`, ID и название категории
в первой строке скрытого листа «Справочники». Раньше worker проверял их после
чтения листа товаров: пользователь ждал очередь и мог получить ошибку колонок
вместо сообщения о неверной категории.

Теперь `POST /api/v1/admin/products/import` при наличии `category_id` сравнивает
ID до сохранения исходного файла, создания `ProductImport` и dispatch-task.
Несовпадение возвращает HTTP 422 в существующем `error.details.file` с названием
и ID обеих категорий. Название берётся из текущего каталога; для удалённой
категории используется имя из шаблона. Имя файла и совпадение названий категорий
не влияют на проверку. Отсутствующие/некорректные метаданные также отклоняются.

Worker повторяет проверку перед разбором колонок и товаров. Шаблоны создания,
редактирования и повторной загрузки ошибочных строк совместимы. Прежние V1
шаблоны подходят без повторного скачивания. Импорт без `category_id`, импорт
групп, цен/статусов и изображений сохраняют свои сценарии.

Проверка не обходит строки товаров и оставшиеся строки справочников. OpenSpout
при открытии Excel может подготовить общую таблицу строк `sharedStrings`;
поэтому работа не обещает постоянное время относительно размера файла.
Файл сначала должен быть передан серверу, но ждать фоновую очередь при ошибке
категории больше не требуется. Временный кеш имеет отдельный каталог с правами
0700 и удаляется в `finally`, включая частичный сбой открытия XLSX.

## Изменённые файлы

- `backend/app/Services/ProductImportTemplateCategoryValidator.php` — проверка метаданных и очистка кеша.
- `backend/app/Services/ImportSubmissionService.php` — проверка до файловых/DB/queue операций.
- `backend/app/Services/ProductImportTemplateReader.php` — повторная проверка перед чтением товаров.
- `backend/tests/Feature/Api/CategoryProductImportTest.php` — пять регрессионных сценариев, включая create/edit, переименование и одинаковые колонки/названия при разных ID.
- `backend/tests/Unit/Services/ProductImportTemplateCategoryValidatorTest.php` — очистка кеша после повреждённого XLSX.
- `docs/API.md`, `docs/openapi.json` — контракт раннего отказа.
- `docs/PRODUCT_IMPORT_CATEGORY_PREFLIGHT.md`, `tasks/DONE.md` — результат и статус задачи.

## Проверки

- `composer test`: 362 passed, 5073 assertions.
- PHPUnit с `phpunit.postgres-feature.xml`, фильтр `CategoryProductImportTest|ProductImportTest|ProductPriceStatusImportTest|ProductGroupImportTest`: 40 passed, 392 assertions, отдельная база `agatceramic_feature_test` после проверки штатным safety guard.
- `composer validate --strict`: passed.
- `composer audit --locked`: no security vulnerability advisories found.
- `composer architecture`: no blocking violations; существующие advisory по размеру/сложности методов остаются, затронутые границы рассмотрены на независимом review.
- `composer analyse`: no errors.
- `vendor/bin/pint --test`: passed.
- OpenAPI проверен полным backend suite; `git diff --check`: passed.
- Независимый code review: замечание об очистке кеша исправлено, повторное review без actionable findings.

Регрессионные тесты сначала воспроизвели HTTP 202 вместо раннего 422 и ошибку
колонок вместо названий категорий. Тест повреждённого файла отдельно воспроизвёл
оставшийся sharedStrings-кеш и прошёл после исправления. Один промежуточный
PostgreSQL-прогон пересёкся с тестами ревьюера: общий `Storage::fake` каталог
был очищен другим процессом. Повторные последовательные прогоны прошли.

Миграции и схема БД не изменяются. Frontend-файлы не изменяются: существующий
service/composable выводит первую ошибку `error.details.file` через действующее
уведомление страницы. UI Design Guard не требуется для backend-only изменения
согласно `docs/UI_DESIGN_REVIEW.md`.
