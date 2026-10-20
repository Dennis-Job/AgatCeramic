# Сохранение слайдера на странице «О нас»: исправление 422

Дата: 2026-10-19. Статус: исправлено локально, commit не создан.

## Причина и исправление

Редактор отправляет полный черновик страницы вместе с блоком слайдера.
У системной страницы «О нас» пустые `body`, `seo.description` и
`seo.og_description`. Laravel ConvertEmptyStringsToNull превращал их в `null`,
после чего Form Request возвращал 422 «Поле должно быть строкой».

В общем Form Request concern страниц добавлена нормализация перед проверкой:
переданные пустые необязательные тексты восстанавливаются в строки. Это также
покрывает `text.data.body`, `catalog.data.description` и `seo.og_title`.
Отсутствующие поля, обязательные заголовки, неверные типы, URL, media IDs и
permissions сохраняют прежние проверки. Миграции и изменение запросов БД не нужны.
Публичный контракт остаётся строковым; OpenAPI уточняет существующее разрешение
пустых строк. Предупреждение Nuxt/Vue `<Suspense>` не вызывает этот ответ 422.

## Проверка

- Оба новых regression-сценария сначала воспроизвели 422, затем прошли.
- Полный backend suite: **489 passed / 1 skipped, 5860 assertions**.
  Пропущен PostgreSQL-only тест поиска брендов с кириллицей на SQLite.
- Проверены полный GET → PATCH payload редактора, сохранение ссылки на слайдер,
  изоляция черновика до публикации и публичный результат после публикации.
- Проверены создание/очистка необязательных текстов, сохранение пустых SEO,
  отсутствие подстановки пропущенных полей и отклонение неверных типов.
- Composer validate, Pint, architecture guard и PHPStan: PASS.
- OpenAPI lint, project conventions, backward compatibility и git diff --check: PASS.
- Независимое read-only code review: ошибок исполнения и блокирующих замечаний
  к итоговой документации не найдено.
- Composer audit: не завершён, Packagist connection timeout при двух попытках.
- UI-код не менялся; браузерный E2E и PostgreSQL suite не запускались.

## Изменённые файлы

- `backend/app/Http/Requests/Api/V1/Admin/Concerns/ValidatesPageContent.php`
- `backend/tests/Feature/Api/PageBlockContentTest.php`
- `docs/API.md`
- `docs/openapi.json` — только уточнение описаний запросов страниц.
- `docs/PAGE_SLIDER_422_FIX_2026-10-19.md`
- `tasks/DONE.md`
