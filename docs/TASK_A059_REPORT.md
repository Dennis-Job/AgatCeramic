# TASK-A059 — режимы ширины и подробная таблица товаров

Дата: 2026-10-01. Ветка: `codex/admin-seller-redesign`.
Статус: завершена; независимый UI Design Guard принял результат Windows/Linux.

## Результат

Общий `AdminWorkspace` задаёт явные режимы `overview`, `list`, `editor`, `form`.
Обзор ограничен 1280 px, простая форма — 960 px; список и редактор используют
всю доступную ширину. Slot `intro` отдельно ограничивает вводную часть 1280 px.
Размеры вынесены в workspace tokens; shell gutters — 16 px до 640 px и 24 px
от 640 px. Сохранён `min-width: 0` для рабочих областей, grids и их детей.
Первым на компонент перенесён список товаров. Остальные списки и экраны
подключаются в TASK-A060/A061; legacy `.admin-page` пока сохранён для них.

Таблица товаров размещена без внешней карточки. На широких экранах название
получает свободную ширину после колонок с деталями; название, бренд, SKU и
артикул допускают перенос. Ограничение названия в 50 символов удалено.
Цены и остатки выровнены вправо, под ними показаны единицы продажи из
существующего `product.unit`. Даты сохраняют semantic `time`/`datetime`,
дата и время переносятся при недостатке места.

`UiTable` получил опциональные `stickyHeader`/`stickyEdges`. Включённый режим
создаёт ограниченную по высоте локальную прокрутку, закрепляет заголовки,
а от 1280 px — первую и последнюю колонки. На узких экранах закрепления колонок
нет, данные и CRUD-действия доступны горизонтальной прокруткой внутри таблицы.
Существующий именованный focusable region, containment и inset focus сохранены.
Остальные таблицы сохраняют прежнее поведение по умолчанию.

Для фото переиспользован `UiImagePreview compact`: квадрат 48 px, `contain`
без обрезки плитки/мозаики, fallback для отсутствующего или повреждённого фото.
Полная подпись fallback доступна скринридеру. Обычный preview не изменён.
Тестовые изображения каталога теперь возвращают настоящее изображение,
чтобы сценарии загрузки/смены primary photo проверяли успешный preview.

Сохранены фильтры, поиск, query, сортировка, пагинация, импорт/экспорт,
создание, клонирование, редактирование, удаление, группы вариантов, права доступа
и loading/empty/error/denied состояния. Backend, Client, API/OpenAPI и миграции
не изменялись. Commit и push не создавались.

## Проверки и независимое ревью

- `npm run lint` — пройдено.
- `npm run build` — пройдено; существующее предупреждение о JS chunk >500 kB.
- `npm run test:unit -- --maxWorkers=1` — 64/64.
- Полная строгая production E2E-регрессия — 198/198 Windows и 198/198 Linux.
  Baseline-прогон — 77/77 на каждой OS. Последняя локальная правка переносов
  больших чисел дополнительно проверена Windows targeted E2E — 2/2; итоговый
  Linux полный прогон включает эту правку.
- Все изменённые исходники проходят Prettier. Полная проверка `src/e2e/tests`
  с `--end-of-line auto` проходит. Глобальный `npm run format:check` обнаруживает
  прежние CRLF/LF различия и игнорируемые временные `.tmp` artifacts;
  несвязанные файлы не переформатированы.
- Production responsive-проверка: 320, 640, 768, 1024, 1280, 1440, 1920,
  2560 px. Проверены полные названия, использование ширины, отсутствие page
  и cell overflow, включая большие цены/остатки, закрепление headers/edges,
  keyboard scroll, открытие editor после
  прокрутки, axe и fallback изображения. Отдельно сняты таблица и CRUD на 320 px.
- UI Design Guard независимо проверил архитектуру, код, восемь ширин и
  намеренные visual изменения. Выход дат из узких ячеек найден и устранён;
  свежие снимки 1920/2560 подтверждают исправление. Blocking findings отсутствуют.

После изменения общего desktop gutter первые полные прогоны обеих OS дали
145 passed и 53 ожидаемых screenshot differences. Все функциональные проверки
прошли. UI Design Guard поштучно просмотрел affected populated/empty/loading/error
маршруты, products denied и UI-kit disabled на 1024/1280 px. После приёмки
обновлены 54 baseline каждой OS (дополнительный crop на 1280 px появлялся после
успешного сравнения 1024 px). Повторные полные прогоны прошли без перезаписи.

Начальные catalog failures были устаревшими ожиданиями: действия теперь видны
на 1280 px без прокрутки, остаток включает единицу продажи, fake JPEG URL без
изображения корректно показывает новый fallback. Assertions и image fixtures
обновлены без ослабления сценарных проверок.

Linux проверен в отдельном временном контейнере с копией исходников и
существующими изолированными E2E/browser caches. Работающие сервисы проекта
не менялись; временный контейнер после сохранения evidence удалён.
Repository hygiene, Markdown links и `git diff --check` пройдены;
diff backend/Client пустой.

Evidence находится локально в `frontend/admin/.tmp/a059-*`.
macOS runner отсутствует; Darwin baseline не заменяются снимками другой OS.

## Изменённые файлы

Пути относительно `frontend/admin/`, если не указан другой корень:

- `src/components/shared/AdminWorkspace.vue`, `UI_KIT.md`, `UiKitShowcase.vue`.
- `src/components/ui/UiTable.vue`, `UiImagePreview.vue`.
- `src/layouts/AdminLayout.vue`, `src/styles/tokens.css`.
- `src/features/products/components/ProductEditor.vue`,
  `composables/useProductEditor.ts`, `productPresentation.ts`.
- `e2e/catalog.spec.ts`, `catalogApi.ts`, `productWorkspace.spec.ts` и
  просмотренные platform snapshots `e2e/adminBaseline.spec.ts-snapshots/`.
- `docs/ADMIN_SELLER_REDESIGN_PLAN.md`, `docs/CURRENT_STATE.md`,
  этот отчёт и task ledger.
