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

- Category badge word wrapping: local overflow-wrap normal preserves whole
  words and wraps multiword names at spaces. Eight viewport checks confirm
  the standard category remains on one line, including 1035 px. Build/lint/
  format, 159 unit and 21 targeted production E2E pass. Removing the category
  cell max-width lets long unbroken words expand the column within the local
  table scroll area; the stress check verifies no badge/cell/page overflow.
  Independent UI Design Guard accepted single/multiword names at 320/1035/
  1440 px. Whole long words can widen the category column; neighboring cells
  may wrap more, without intersections or lost content.

- Browser comment fix for the All option: both product segmented controls
  keep the first option unshrunk and on one line. Eight viewport checks now
  include 1035 px and measure the text height. Build/lint/format, 159 unit and
  20 targeted production E2E pass, including gaps and large-count stress.
  Independent UI Design Guard accepted: All text stays at one line on
  320/1035 px and large-count 1024 px, without page or label overflow.

- Browser comment spacing refinement: all adjacent filter gaps are 16 px;
  bounded content tracks for sale/activity give spare width to both selects.
  Geometry includes 1349 px, fonts/counts are awaited, and large counts at
  1024 px preserve select width. Build/lint/format, 159 unit and targeted E2E
  pass; the 1280 px geometry check repeats 3/3 successfully.
  Independent UI Design Guard accepted: all gaps are 16 px at 1349 px and
  at 1024 px with large counts; selects retain 128 px, with no page overflow.

- Latest owner refinement puts activity fourth in the same centered desktop
  row as category, brand and sale from 1024 px. Smaller screens wrap within
  the panel. Four-control geometry passes at 1024/1280/1440; all 17 targeted
  E2E, 159 unit and build/lint/format checks pass.
  UI Design Guard accepted after fixing auto tracks that squeezed selects at
  1024 px with large counts. Selects keep an 8rem minimum, segmented controls
  use bounded fractional tracks. Additional six-digit-count stress E2E passes;
  independent 1024/1440 captures confirm useful select text and no overflow.

- Owner pagination/filter refinement on 2026-10-19: product list requests
  50 items per page; activity lives in the collapsible filter panel, with
  removable selected conditions still visible outside. Production E2E covers
  51 items across two pages and filter reset to page one. All 17 targeted E2E,
  159 unit, build/lint/format pass.
  Independent UI Design Guard accepted fresh responsive captures, hidden
  panel accessibility and the existing API pagination contract.

- Owner table refinement on 2026-10-19: categories use the existing additional
  badge tone; category/status headers and badges are centered. Actions use
  intrinsic content width. Six viewport geometry/axe checks and all 11 targeted
  production E2E pass; build/lint/format and 159 unit tests pass.
  Independent UI Design Guard accepted long Russian values, two status badges
  and open action menus at narrow/wide sizes: actions stay 98 px, no clipping
  or page overflow; Escape returns focus to the trigger.

- Owner refinement on 2026-10-19 supersedes the source mock's filter layout:
  category, brand and sale share one centered row from 1024 px and have no
  visible labels. Accessible names and a hidden legend remain; narrow screens
  wrap intentionally. Fresh six-width captures and 16 production E2E checks
  verify the refinement, including row geometry and axe.

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

---

# Content header sections — design QA

Date: 2026-10-26. Route: `/content?section=appearance&panel=header`.

**final result: passed**

Independent UI Design Guard accepted the implementation with no blocking findings.

## Evidence

- Selected reference: first displayed concept, `exec-677f5fed-e940-4ee5-9340-2b51290e254a.png` in this chat's generated images.
- Live implementation: `.tmp/content-header-design/final-live-1440.png` and `final-live-full.png`; actual Nuxt preview, not the fixture iframe.
- Combined comparison: `.tmp/content-header-design/comparison.png` and `comparison-inspector.png`.
- Responsive pending-file evidence: appearance E2E captures at 320/640/768/1024/1280/1440 CSS px. API fixtures are used for these interaction/accessibility checks, not as proof of real Nuxt rendering.

## Findings and resolution

- Initial compact thumbnail clipped the empty-image caption. Reused `UiImagePreview`'s existing compact mode, which keeps the fallback accessible with visually hidden text. Fresh live capture confirms the fix.
- At the narrow desktop inspector, reduced only the opt-in thumbnail size to make room for the approved dropzone. Default image-editor consumers retain their existing size.
- Three clear sections, approved gray headers/white bodies and existing tokens match the selected direction. Real empty logo and six menu items replace illustrative mock content. The existing preview/inspector proportions, readable field labels, non-collapsing sections and ordinary page scrolling are intentional constraints, not defects.

## Fidelity and interaction checks

- Typography: existing Nunito/Poppins and UI-kit type scale retained.
- Spacing/layout rhythm: existing `UiCard`, shared control spacing and three-zone layout; no new shell or global styles.
- Colors/tokens: approved palette retained without contrast-driven replacement.
- Image quality: actual Nuxt assets preserved; selected-logo thumbnail uses contain and honest fallback. No fabricated logo.
- Copy/content: actual settings retained, contacts source clearly identified, descriptive names for icon actions.
- Accessibility: named sections, labels, keyboard controls, image fallback and restored dropzone focus on cancellation. Automated responsive/axe coverage includes the narrow desktop inspector.
- Permissions/guards: `media.manage` still gates select/remove/upload; content editors retain read-only preview. Existing pending/uploading and draft/publish boundaries remain.

No remaining actionable P0/P1/P2 findings.

## 2026-10-26 — тот же паттерн для подвала

**final result: passed**

По указанию владельца footer-редактор разделён на четыре секции `UiCard`:
слоган, ссылки «Исследовать», текстовый блок и нижняя строка. Использованы
те же approved tokens, typography, интервалы и кнопки списка, что в шапке.
Общие primitives/layout, медиа, API и guards не менялись.

Evidence: `.tmp/footer-sections/before.png`, `after-1440.png` (реальный Nuxt),
`e2e/**/appearance-footer-{320,640,768,1024,1280}.png` (responsive controls).
Независимый UI Design Guard просмотрел diff и снимки: accepted, blocking findings нет.
Appearance E2E/axe 16/16 и unit 245/245 PASS; live CRUD ссылки проверен с восстановлением
исходных значений без сохранения/публикации. Новых визуальных дефектов не выявлено.

## 2026-10-26 — автоматическая подпись слева в подвале

**final result: passed**

Вместо поля «Слева» — обычный текст и пояснение об автоматическом годе по
часовому поясу сайта. Значение формирует backend по APP_TIMEZONE; Nuxt и Admin
получают одну строку, часы посетителя не участвуют. Утверждённые стили сохранены.

Независимый UI Design Guard: accepted, блокирующих замечаний нет. Просмотрены
`.tmp/footer-copyright/admin-live.png`, `admin-full.png` и responsive screenshots
320/640/768/1024/1280 px в `e2e-final/`. Подпись и подсказка читаются и переносятся.
E2E 16/16 PASS, включая отсутствие редактируемого поля и readonly-свойства в PATCH;
публичный сайт и настоящий Nuxt-preview показывают «© 2026 AgatCeramic».
Backend и PostgreSQL проверки подтверждают смену года по настроенному часовому поясу
и сохранность raw данных; отдельная проверка редактора защищает локальные черновики.
