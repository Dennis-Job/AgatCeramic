# Служебные файлы macOS в ZIP-импорте

Дата: 2026-10-06.

## Причина и изменение

Finder добавил в архив пользователя `__MACOSX/SKU/._SKU_1.webp`.
Импортер проверял эти записи как содержимое галереи и отклонял весь ZIP
из-за вложенного пути до подсчёта папок SKU.

`ProductImageImportService` теперь пропускает записи внутри `__MACOSX/`
и файлы `.DS_Store` / `._*` в любом безопасном каталоге. Проверки размера
записи, общего объёма, числа записей, небезопасных путей и символических
ссылок выполняются до пропуска. Метаданные не создают SKU-группы и ошибки
галерей. Архив только с метаданными по-прежнему отклоняется.

Маршруты, поля API, permissions, DB/query/schema и транзакции не менялись.
Поведение задокументировано в API, OpenAPI и Queue. TASK-A071 по разделению
сервиса остаётся отдельной задачей; рефакторинг галерей сюда не включён.

## Проверки

- Regression test сначала падал на метаданных Finder; после изменения прошёл.
- `ProductImageImportTest` и `OpenApiRouteCoverageTest`: 14/14 passed.
- Проверены успешная загрузка/применение галереи, счётчики, metadata-only ZIP,
  traversal, абсолютный путь, backslash, symlink, превышение размера и обычный
  вложенный файл.
- Исходный `Архив.zip` проверен текущим методом инспекции: 4 SKU-папки,
  5 изображений; метаданные больше не вызывают отказ. Реальные товары этим
  диагностическим вызовом не изменялись.
- Pint для изменённых PHP-файлов, Composer validate/audit, architecture guard
  и PHPStan прошли. PHPStan выполнен без parallel TCP через `--debug`.
- Feature tests использовали SQLite; отдельный PostgreSQL-прогон и полный
  backend suite не запускались. DB/schema/query behavior не менялся.
- Локальному Docker worker отправлен `queue:restart`, чтобы новый код вступил
  в силу после текущего задания.

## Файлы

- `backend/app/Services/ProductImageImportService.php`
- `backend/tests/Feature/Api/ProductImageImportTest.php`
- `docs/API.md`, `docs/openapi.json`, `docs/QUEUE.md`
- Этот отчёт и списки задач.
