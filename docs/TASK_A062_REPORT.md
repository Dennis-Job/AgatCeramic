# TASK-A062 — финальная приёмка Seller-редизайна

Дата: 2026-10-01. Исходный commit: `670406a`, ветка `codex/admin-seller-redesign`.
Статус: завершена; независимый UI Design Guard принял фактически проверенные Linux/macOS.

## Результат и границы

Проверена итоговая реализация TASK-A057–TASK-A061 по согласованным
[плану](ADMIN_SELLER_REDESIGN_PLAN.md), [UI-kit](../frontend/admin/src/components/shared/UI_KIT.md)
и [регламенту ревью](UI_DESIGN_REVIEW.md): белые поверхности, синий акцент,
компактные controls, верхняя навигация, широкие списки и редакторы,
ограниченные обзорные экраны и формы. Nunito/Poppins, размеры текста и
межстрочные интервалы сохранены; typography diff содержит только пустые строки.

Расширен существующий QA, без нового бизнес-функционала:

- общая матрица проверяет 20 маршрутов Admin/UI-kit на восьми ширинах
  320/640/768/1024/1280/1440/1920/2560 px;
- перед измерениями проверяются загрузка страницы и шрифты; для UI-kit
  постоянный демонстрационный loading не трактуется как загрузка данных;
- на каждой ширине проверяются отсутствие page-level overflow и все axe
  violations, без исключения правил или категорий; снимается viewport evidence;
- навигация дополнительно проверяется на 1440 px;
- `updateSnapshots: 'none'` запрещает автоматическую замену и создание baseline;
- soft snapshot assertions набора UI-kit crops собирают все различия,
  сохраняя failed-статус при любом несовпадении; tolerance 30 pixels и retries=0
  остаются прежними.

Axe обнаружил повторяющееся доступное имя двух примеров пагинации UI-kit.
Каждый пример теперь имеет собственный `aria-label`, передаваемый существующему
корневому `nav` через native attribute forwarding. Рабочие списки и API
`UiPagination` не изменены. Повторный полный axe UI-kit на восьми ширинах прошёл.

## Приёмочная матрица

| Область | Проверенный результат |
| --- | --- |
| Auth, dashboard, profile, settings | Центрирование, ограничение 1280/960 px, validation/feedback, все восемь ширин |
| Products и каталог | Подробные широкие таблицы, длинные строки, contain/fallback фото, цены/единицы/даты, фильтры/query, CRUD/import/export |
| Employees, roles, permissions, audit, media | Данные и состояния, responsive cards/локальный scroll, права и ограничения действий |
| Orders/contacts | Список и выбранные детали, локальная sticky-прокрутка, действия и workflow |
| Navigation | Все разрешённые ссылки, отдельное media.manage, active query, dropdown/compact menu, focus/Escape/backdrop, unsaved cancellation/acceptance |
| Content | Последовательные/две/три зоны по доступной ширине, поля/SEO/блоки, pending uploads, dirty guards, черновик и отдельная публикация |
| UI-kit | Default/hover/focus-visible/disabled/loading/error/empty/success, overlays, контраст, уникальные landmarks, keyboard/date-picker |
| Real Nuxt | Настоящий renderer в Admin iframe, 1280/375 px, keyboard scroll, приватность и отсутствие draft в SSR/public API, denied/retry/revocation/reduced motion |

Default viewport screenshots не подменяют проверку скрытых/прокручиваемых
действий. Sticky headers/edges, локальный scroll, mobile CRUD, selected details
и dialogs проверяются отдельными сценариями полного E2E и соответствующими
screenshot evidence предыдущих этапов.

## Фактические проверки

- Локальный Admin `npm run check` — lint/format/unit/build пройдены; 64 unit.
- Финальные полные Linux и macOS strict E2E — 223/223 на каждой OS,
  включая 83 baseline/UI-kit/responsive сценария и 70 platform snapshots.
  Linux lint/format/unit64/build также прошли в итоговом изолированном gate.
- UI-kit full axe 8 ширин — 1/1; внутри сценария все violations = 0.
- 160 responsive/axe captures: 20 маршрутов × восемь ширин.
- Повторная real Admin + production Nuxt интеграция — 3/3 на восьми ширинах.
- Client production preview — 6/6, development iframe/DevTools — 2/2.
- Nuxt production build — пройден.
- Независимый UI Design Guard принял архитектуру, UIKit fix и 65 Darwin
  baseline candidates до копирования; итоговый verdict — ACCEPTED.
  Блокирующих и actionable findings нет; оба strict full прошли.
- `git diff --check`, repository hygiene и Markdown links — пройдены.
- Diff Backend/Client/API/OpenAPI/миграций относительно pre-redesign `f09ad7a`
  пустой. Контракт и схема БД не менялись; миграционные операции не требовались.

GitHub [CI исходного commit 670406a](https://github.com/Dennis-Job/AgatCeramic/actions/runs/36913758053)
завершился успешно: Repository security, Backend checks, PostgreSQL feature
suite, Redis queue delivery, Admin full-stack smoke, Admin checks, Client checks,
Compose bootstrap. На момент сдачи реализации этот run проверял исходный commit;
A062 diff был проверен локальными gates и не выдавался за опубликованный CI run.

## Диагностика и baseline

Linux первые прогоны дали 222 passed/1 failed: сначала новый QA ожидал
исчезновения демонстрационного loading в UI-kit, затем расширенный axe выявил
повторяющиеся имена пагинации. Оба вопроса исправлены без ослабления axe.

Следующий strict full выявил race теста после viewport resize: Linux —
222 passed/1 failed (appearance), macOS — 221 passed/2 failed (home/about).
Диагностические 30 запусков сохранили исходный assertion и воспроизвели
8 failures: scrollWidth=591 при viewport=320, за границей оставались элементы
старого desktop-menu. После двух animation frame во всех этих случаях
scrollWidth=320. Trace фиксирует измерение через ~12 ms после resize;
`AdminNavigation` переключает compact ref по MediaQueryList change и Vue render.
Тест теперь ждёт фактического compact menu (или его отсутствия на desktop)
и два animation frame **до** измерений. Assertions ширины, overflow и геометрии
зон остаются строгими. Контрольные 30/30 macOS и 30/30 Linux прошли с retries=0.

macOS baseline до приёмки оставался историческим: первый full дал 161 passed,
61 visual failure и ошибку нового UIKit loading ожидания. Собраны все actuals,
включая пять UI-kit disabled crops; пять date-picker crops совпадали.
Независимый reviewer просмотрел и принял 65 изображений: 61 replacement,
четыре новых состояния media. Только затем обновлены Darwin snapshots.

В первом диагностическом запуске Playwright default `missing` сам создал
четыре отсутствующих Darwin media PNG. Они были сразу вынесены из каталога
эталонов как непринятые artifacts. Для последующих запусков установлен
`updateSnapshots: 'none'`; четыре PNG возвращены только после review.
Linux baseline при A062 не переснимались и не менялись.

Windows runner в текущей среде недоступен: Windows baseline не проверены и
не обновлены. Снимки Windows/результаты TASK-A058/A059 остаются историческими,
их нельзя считать актуальным успешным Windows-прогоном. Канонический CI gate
проекта выполняется в Linux Docker; актуальный macOS baseline проверен отдельно.
Axe и screenshot review не являются заявлением полной WCAG compliance.
Существующее предупреждение Vite о chunk >500 kB остаётся.

В Client отсутствовали установленные Playwright/axe dev dependencies.
Preview-проверки использовали временные symlinks на уже установленные версии
Admin (1.62.1/4.13.0), удалённые после прогонов; dependencies/lock не менялись.
Для чистого воспроизведения выполнить `npm ci` в обоих приложениях.

## Evidence и воспроизведение

Локальные ignored artifacts:

- `frontend/admin/.tmp/a062-visual/` — 160 viewport снимков;
- `frontend/admin/.tmp/a062-linux-visual/` — 160 Linux снимков;
- `frontend/admin/.tmp/a062-linux-content-visual/` — 56 Linux снимков с длинными данными контента/форм;
- `frontend/admin/.tmp/a062-review/` — независимые контактные листы/manifest;
- `frontend/admin/.tmp/a062-nuxt/` — 48 свежих real Admin/Nuxt PNG;
- `frontend/admin/.tmp/a062-{linux-final,darwin-final,local-final-check,real-nuxt,uikit-final,overflow-repro,overflow-confirm,overflow-linux-confirm}.log`;
- `/private/tmp/agat-a062-darwin-baseline/`, `/private/tmp/agat-a062-darwin-unreviewed/` — Darwin actuals до принятия;
- `/private/tmp/agat-a062-linux-failure/`, `/private/tmp/agat-a062-darwin-final/` — responsive failure screenshot/trace;
- `.tmp/a062-client-{build,preview,dev}.log`.

```sh
cd frontend/admin
npm ci
npm run check
npm run test:e2e -- --workers=2
# Для канонического Linux gate из корня:
# docker compose --env-file .env.example --profile test run --rm admin-e2e
cd ../client
npm ci
npm run build
npm run test:e2e -- preview.spec.ts
npm run test:e2e:dev
cd ../admin
npm run test:e2e:preview
```

Проверки, использующие mock API 8015, запускать последовательно.
Mac и Linux используют собственные platform snapshots. Не подменять Windows
изображениями другой OS; baseline candidates сначала передавать на review.

## Изменённые файлы

- `frontend/admin/e2e/adminBaseline.spec.ts` — все маршруты/восемь ширин/axe/evidence;
- `frontend/admin/e2e/navigation.spec.ts` — 1440 px;
- `frontend/admin/e2e/contentWorkspaces.spec.ts` — ожидание responsive меню после resize;
- `frontend/admin/playwright.config.ts` — запрет автоматического baseline update;
- `frontend/admin/src/components/shared/UiKitShowcase.vue`, `UI_KIT.md` — уникальные имена пагинации;
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/*-chromium-darwin.png` — 61 принятый replacement и четыре media additions;
- `docs/CI.md`, `ADMIN_SELLER_REDESIGN_PLAN.md`, `CURRENT_STATE.md`, этот отчёт;
- `tasks/TODO.md`, `IN_PROGRESS.md`, `DONE.md`.

На момент сдачи реализации commit/push A062 не создавались.
