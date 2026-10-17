# TASK-C006 — визуальная проверка «Контента» и общего оформления

Дата: 2026-10-17. Источник — выбранный владельцем третий макет «Инспектор».

## Сравнение

Исходный макет и итоговый screenshot настоящего Nuxt-preview просмотрены совместно.
Итоговые evidence: `frontend/admin/.tmp/content-redesign/home-pages-1920.png`,
`about-pages-1920.png`, `home-appearance-1920.png` (1920×1080).
Адаптивные evidence: `frontend/admin/.tmp/a061-visual/` и `.tmp/a061-nuxt/`.
Данные/сессия в этих captures тестовые; iframe рендерит настоящий Nuxt, а не HTML-заглушку.

- Типографика: сохранены Nunito/Poppins и действующие размеры/gray-500 из UI-kit.
- Компоновка: рабочая область без max-width; список 240 px, инспектор по центру,
  preview около 50% на широких экранах. Вкладки и общая строка действий соответствуют макету.
- Цвета/tokens: сохранена утверждённая палитра, синий акцент и белые поверхности.
- Изображения: реальные изображения и компоненты клиентского сайта внутри iframe;
  иллюстративные фото и storefront из макета не подменяют существующий Nuxt-дизайн.
- Контент: типизированные блоки, title/slug/body, SEO главной, разделы оформления.
  Поля соответствуют существующему API. Публикация отделена от сохранения.
- Состояния: pending-файлы сохраняются между вкладками, загрузки блокируют небезопасные переходы;
  проверены loading/error/retry/empty, disabled, клавиатура и focus.
- Адаптивность: 320/640/768/1024/1280/1440/1920/2560 px; нет page overflow.

Уточнение шапки по Browser annotations: nav/title/status/actions объединены в один
ряд в общем контейнере 1280 px. Нижняя сетка остаётся full-width. Дополнительный live
capture — `frontend/admin/.tmp/content-redesign/toolbar-container-live.png`.

## Ожидаемые отличия от эскиза

Макет определяет структуру редактора. Действующая верхняя глобальная шапка,
зафиксированные оттенки текста, реальные поля выбранного блока и публичный сайт сохранены.
SEO остальных страниц пока недоступно и отмечено явно. Дополнительные функции из изображения
не добавлялись. Самостоятельный `UiTabs` обоснован семантикой tablist/tabpanel;
существующий `UiSegmentedControl` остаётся native radio.

## Независимое ревью

UI Design Guard проверил diff, выбранный макет и новые captures.
Блокирующих P1/P2 не найдено. Четыре изменённых macOS baseline `/content`
(default/loading/empty/error) визуально утверждены.

## Проверки

Build/typecheck, lint, форматирование, 158 unit-тестов, функциональные проверки редактора,
адаптивные проверки общих контейнеров и 24 real-Nuxt preview проверки пройдены.
Общий E2E-прогон и сведения о существующих расхождениях visual baseline описаны в
`docs/ADMIN_CONTENT_REDESIGN.md`. API/backend/client-код, миграции и зависимости не менялись.
Linux/Windows baseline этой задачей не обновлялись.

final result: passed

---

## Историческая приёмка категорий (сохранена без изменений)

# Design QA — категории, вариант 3

Дата: 2026-10-07. Route: `http://localhost:5173/categories`. Final result: **passed**.

Это историческая приёмка от 7 октября. Локальные файлы исходного ревью не
входили в Git; их пути ниже сохранены как идентификаторы, а не переносимые
ссылки. На Windows отсутствуют исходный macOS ImageGen PNG и два real-data
JPEG, поэтому повторно проверить именно эти исторические изображения здесь
нельзя. Актуальные переносимые эталоны — [Windows](frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-categories-chromium-win32.png)
и [Linux](frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-categories-chromium-linux.png);
их отдельная приёмка описана в [Windows palette report](docs/ADMIN_WINDOWS_PALETTE_REPORT.md).

## Источник и проверяемый результат

Владелец выбрал вариант 3 из трёх ImageGen-концептов: раскрытая иерархия, отношения и группы характеристик сразу под каждой категорией.

- Точный источник: вариант 3, `/Users/dennis.a.k/.codex/generated_images/01a1161c-cea9-7640-b7ee-d3db770b1ce0/exec-e0998579-7139-48fd-8560-3dcdd2ed2f90.png`, PNG 1487×1058.
- Реализация с сопоставимым содержимым: `frontend/admin/.tmp/category-overview-desktop.png`, viewport 1440×1024, full-page PNG 1440×1045.
- Реальные данные: «Сантехника и дети», `frontend/admin/.tmp/category-overview-real.jpg`, нативный screenshot JPEG 1243×1139.
- Длинные русские строки и непрерывные слова: `frontend/admin/.tmp/category-overview-{320,640,768,1024,1280}.png`.

Источник и реализация представлены вместе в одном сравнительном input; композиция сопоставлена по ширине (1487 и 1440 px) с сохранением пропорций. Данные fixture отражают ту же структуру: корень, две дочерние категории и две группы. Основные области отдельно проверены в исходном масштабе: названия групп, строки характеристик, отметки обязательности/фильтра, parent/child links и действия.

## Оценка соответствия

| Область          | Итог                                                                                             |
| ---------------- | ------------------------------------------------------------------------------------------------ |
| Общая композиция | Раскрытая иерархия, непрерывный список, белая рабочая область, поиск и создание сверху сохранены |
| Связи            | Тип категории, видимость, родитель и реальные имена детей видны; переход сохраняет фокус         |
| Плотные данные   | Группы открыты, имена/единицы/оба флага читаемы; пустые назначения не скрываются                 |
| Действия         | Existing forms/dialogs сохранены, доступ по прежнему permission, tooltip и focus UI-kit          |
| Responsive       | 320/640/768/1024/1280 px без горизонтального overflow, длинные строки переносятся                |
| Состояния        | Loading, empty, error/retry, success после сохранения, readonly и keyboard/Escape проверены      |
| API              | Один overview-запрос с полной рекурсивной проекцией; старый tree контракт сохранён               |

## Исправленные замечания

- Пустые группы и неназначенные характеристики имеют `role=status`; ошибка сведений не изображается пустым массивом.
- Длинные имена группы, характеристики, категории и slug переносятся без утраты информации.
- Начальная ошибка загрузки показывает in-place `UiAlert` и «Повторить загрузку», без ложного «Категорий пока нет».
- По результатам проверки реального API обзор загружается одним запросом, без серии per-category запросов и HTTP 429.
- Отдельная recursive overview-схема не расширяет закрытый Category DTO в товарах и других endpoints.

## Обоснованные отличия от растрового концепта

Сохранены зафиксированные Seller UI-kit цвета, Nunito/Poppins и существующая ширина контейнера. Поэтому текст и размеры заголовков следуют работающему приложению, а не случайным значениям генерации. Дочерние категории выделены отступом и вертикальной линией; раскрытие не требует клика. Дополнительно видны реальные порядок и SKU-префикс. Растровые assets в интерфейс не вставлялись: папка и действия используют установленный Lucide.

Контраст утверждённых status-цветов — известное ограничение. Axe `color-contrast` исключён адресно для категорий; цвета не изменены без указания владельца. Остальные structural serious/critical проверки пройдены. Не заявляется полная accessibility compliance.

## Доказательства и независимое ревью

- Production build и TypeScript, ESLint и форматирование изменённых исходников passed.
- Новые E2E: 13/13 на macOS и Linux; unit обзора: 4/4; существующие catalog flows: 3/3.
- Category baseline: 5/5 на обеих платформах. Default/loading/empty/error PNG просмотрены до adoption; обновлены ровно восемь эталонов этого маршрута.
- UI Design Guard `/root/ui_design_guard`: **accepted**, без blocking findings. Независимо проверены source, desktop, пять обязательных ширин, длинные строки, API boundaries и четыре состояния на обеих платформах.
- Backend: 374 tests; PostgreSQL категории/назначения: 16 tests. OpenAPI schema lint и backward compatibility passed.
- Общий unit-набор Admin имеет 5 подтверждённых прежних failures; детали и полный список изменений — [в техническом отчёте](docs/ADMIN_CATEGORIES_OVERVIEW.md).

Неразрешённых блокирующих несоответствий выбранной композиции нет.

## Уточнение владельца: список категорий

После приёмки варианта 3 владелец попросил заменить верхний поиск выпадающим списком созданных категорий. `UiSelect` показывает «Все категории» и каждый существующий ID, включая скрытые; названия детей дополнены родителем. Выбор сохраняет предков и потомков, переход к связанным категориям и удаление сбрасывают выбор. Это явное уточнение исходного концепта, без изменения утверждённой палитры.

Открытое меню: `frontend/admin/.tmp/category-select-menu-{320,640,768,1024,1280}.png`; реальный каталог: `frontend/admin/.tmp/category-select-real.jpg` (исторический dropdown JPEG). Все восемь state-эталонов просмотрены независимым UI Design Guard перед adoption. Проверены длинные строки, границы viewport, возврат фокуса и Escape, disabled loading/empty/error. 13 сценариев обзора и 5 baseline прошли на обеих платформах. Итог независимого ревью: accepted, blocking findings отсутствуют.

---

# Design QA — единая рабочая область характеристик

final result: passed

## Evidence

- Approved source: exec-cda9ed3c-0654-45bd-864b-bc7a8b7c5453.png.
- Rendered production build: `.tmp/attributes-workspace-selected-group-1440.png`.
- Combined comparison: `.tmp/attributes-design-comparison.png` (source left,
  implementation right; source is a generated concept, production retains
  established UI-kit typography/dimensions and actual API/fixture records).
- Widths: 320, 640, 768, 1024, 1280, 1440. Both dialogs captured at320/1440.
- Independent UI Design Guard reviewed all11 page/form captures and code.

## Findings and resolutions

- [P2] Async display rollback/refresh ordering: fixed by field-only ID and
  revision reconciliation; regressions cover both GET/PATCH completion orders
  and group removal while PATCH is pending.
- Apparent old selected-group highlight in initial screenshot was captured
  during CSS transition. Stable DOM/aria/current/background assertions and
  animations-disabled recapture confirm correct active group.
- No outstanding P0/P1/P2 findings. Palette remains owner-approved; known
  contrast exceptions are unchanged and documented by existing axe wrapper.

## Verification

Production E2E workspace/catalog/navigation/actions: 99/99; unit147/147;
build/lint/format PASS. Load failure/retry and legacy active navigation were
reviewed separately, including the final five updated baseline captures.
Full app regression recorded separately in
`docs/ADMIN_ATTRIBUTES_WORKSPACE_REPORT.md`.
