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

# Products Admin design QA — 2026-10-16

Final verification: 2026-10-19. Scope: the products workspace and its shared
popover extension.

## Comparison target and evidence

- Source visual truth:
  `C:/Users/dkargopolov/.codex/generated_images/01a144cf-d558-7392-8252-a2f2fbda04e5/exec-7220bbcc-fcf6-4493-aefe-0f4fe177dc15.png`.
- Implementation: `http://localhost:5173/products`, real authenticated API;
  production interaction checks: `http://127.0.0.1:4183/products`.
- Screenshot:
  `frontend/admin/.tmp/products-redesign/real-open-1440.png`.
- CSS viewport: 1440 × 1024; source raster 1487 × 1058; browser export
  1425 × 1013. Both aspect-preserving normalized to fit 1440 × 1024 for
  comparison; no pixel-exact raster assertion is made across export sizes.
- State: filters expanded, category «Керамогранит», sale=false, activity=all,
  sort=created_at/desc, sorting menu open. Source data are illustrative;
  implementation uses actual rows and facet totals.
- Full-view combined evidence:
  `frontend/admin/.tmp/products-redesign/comparison-1440.png`.
- Focused controls/menu comparison:
  `frontend/admin/.tmp/products-redesign/comparison-controls.png`.
- Responsive/axe evidence: `.tmp/products-redesign/open-{320,640,768,1024,1280,1440}.png`.

## Findings and required fidelity surfaces

- Fonts/typography: existing Nunito/Poppins, weights and text tokens preserved.
  Generated mock's darker text was not substituted for approved tokens.
  Full real Russian product names wrap without truncation.
- Spacing/layout: same search → expanded inline filters → removable conditions
  → activity/results/sort → table hierarchy. Controls retain approved sizes;
  seven table columns, full-bleed table, centered 1280 px controls. No page
  overflow at checked widths; table scroll stays local below its minimum.
- Colors/tokens: white, existing blue accent, gray surfaces and semantic
  statuses preserved. No palette or global typography change.
- Image quality: actual API product media and the existing upload/fallback
  component are used. Demo marble photos from the generated mock are not
  invented as catalogue data. Missing-photo controls remain meaningful.
- Copy/content: mock annotation is omitted from production. Real facet
  counts replace illustrative totals. SKU/article and created/updated dates
  remain available. Three-state sale filtering and four supported sort fields
  retain the current server contract.

## Comparison and fix history

1. Real desktop and 320–1440 px captures compared with the source. Narrow-cell
   validation found status minimum width and actions too wide for their cells;
   removed unnecessary status minimum and redistributed columns. Fresh wide
   workspace regression passed at 320–2560 px.
2. Independent UI Design Guard accepted visual hierarchy and reported P2
   keyboard focus loss on native-disabled sorting options/trigger, reset and
   portal Tab exit. Options now use guarded aria-disabled; trigger stays
   focusable with aria-busy, reset focuses search, portal leaves after the
   original trigger or returns to it. Red/green E2E reproductions and the
   independent review of the final production build confirm each fix.
3. Delayed sorting followed by newer search reproduced a rollback mismatch.
   Request ownership now guards rollback; regression passed on the final build.
4. Portal row-menu padding was corrected using portal-safe UI utilities;
   reviewer confirmed computed padding 8 px.

No remaining actionable P0/P1/P2. Independent UI Design Guard: accepted.
Expected differences are real product data/media/counts, approved token
typography, page-size pagination and omission of the mock annotation.

## Implementation checklist and limits

- Filters/chips/reset, both sort directions, API failure rollback, stale
  request handling, CRUD/export/photo workflows checked by production E2E.
- Keyboard Escape during busy sort, focus return and portal Tab/Shift+Tab
  independently verified. Responsive checks include axe and console/runtime
  guards. Real local filters were also exercised through the in-app browser.
- Full Admin regression results and platform limits are recorded in
  `docs/ADMIN_PRODUCTS_REDESIGN.md`.
- No full manual screen-reader audit or Linux/macOS raster baseline update.
- Final build/lint/format and 159 unit tests passed. Product baseline/actions
  recheck: 6/6. Full Windows Admin E2E: 622/626; the four existing Content
  snapshot mismatches were independently traced to commit `58a155c` and its
  unchanged Windows baselines. They are outside this products design QA and
  remain documented in `docs/ADMIN_PRODUCTS_REDESIGN.md`.

final result: passed
