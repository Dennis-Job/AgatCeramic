# Каталог: выравнивание карточек и компактные списки — 2026-11-18

Выполнены замечания владельца к `/catalog`: категория и цена находятся
на одинаковых уровнях у соседних карточек; списки выбора меньше, без
нативной стрелки и внешней выделяющей рамки при клике. Панель вариантов
светлее страницы, имеет тонкую границу, мягкую тень и плавный вход/выход.

## Изменённые файлы

- `frontend/client/app/features/product/components/ProductCard.vue` и
  `ProductCardList.vue`: общие строки CSS subgrid для фото, бренда, полного
  названия, категории и цены. Нет обрезки названий/JS-измерений; `row` сохранён.
- `frontend/client/app/components/ui/UiSelect.vue`: общий select-only combobox
  вместо системного меню. Цель от 44 px, текст 13 px, `bg-soft` для панели,
  общий motion `ui-submenu-popover` 0.35 s; без новых цветов/зависимостей.
  Стрелки/Home/End/PageUp/PageDown, поиск по буквам, Enter/Space/Tab,
  отмена Escape/кликом/фокусом вне, inert/ARIA/disabled/reduced motion.
  Native popover работает внутри мобильного dialog; собственная прокрутка
  вариантов не перемещает документ, позиция учитывает sticky header.
- `frontend/client/app/features/catalog/components/CatalogPageBody.vue`:
  ширина сортировки на desktop уменьшена с 260 до 230 px.
- `frontend/client/e2e/ui-select.spec.ts`, `helpers/selectChoice.ts`,
  `catalog-card-standard.spec.ts`: новые проверки клавиатуры/геометрии/motion,
  пяти ширин, mobile drawer, disabled и axe.
- `catalog.spec.ts`, `catalog-brand-scope.spec.ts`, `brand-pages.spec.ts`,
  `catalog-category-dropdowns.spec.ts`: действия через видимые варианты
  combobox. Два старых сценария brand scope актуализированы для уже существующего
  раскрываемого родителя «Материалы».
- `product-pages.spec.ts`, `product-collections.spec.ts`,
  `fixtures/brand-api.mjs`: старые ожидания `/catalog?brand=` заменены на
  уже действующий `/brand/<slug>`, fixture предоставляет тестовый профиль.
- `docs/CLIENT_UI_KIT.md`: уточнено UI-022, добавлено UI-034;
  `tasks/DONE.md`: статус; короткий план в `docs/superpowers/plans/2026-11-18-catalog-controls.md`.

## Проверки

- `npm run typecheck`, `npm run build`, `npm run format:check`, scoped E2E
  Prettier и `git diff --check` — PASS.
- 80 Client E2E — PASS (Chromium/Windows): `ui-select`, `catalog-card-standard`,
  `catalog`, `catalog-brand-scope`, `brand-pages`, `catalog-category-dropdowns`,
  `product-pages`, `product-collections`, `content-pages`, `preview`.
- Проверены 320/640/768/1024/1280 px, keyboard/touch, normal/reduced motion,
  SSR/hydration, добавление в корзину и сохранение draft/Apply.
- Реальный localhost:3000: 11 товаров, ровные category/price, открытая светлая
  сортировка на 1059×882; JavaScript page errors отсутствуют. Скриншот:
  `.tmp/catalog-controls-real-1059.png`.
- Независимый UI Design Guard просмотрел code и screenshots, включая финальную
  дельту keyboard/scroll/header; блокирующих замечаний нет.

Windows sandbox блокировал копирование public assets при build (EPERM);
та же сборка вне sandbox прошла. Итоговые E2E запущены вне sandbox для
штатного завершения дочерних процессов Windows. Локальный Docker client
перезапущен для обновления старых dev-модулей. IAB после перезапуска не отвечал
на команды CDP; visual QA реального localhost выполнена отдельным headless
Chromium с сохранением и просмотром скриншота.

Существующее утверждённое сочетание muted/beige в заглушке фото даёт
контраст 4.25:1; прежняя явная axe-проверка сохранена. Палитра не менялась.
Subgrid/native popover проверены в современном Chromium; отдельная проверка
Safari/Firefox не выполнялась. Backend/Admin suites не запускались:
их код, API-контракт и БД не менялись. Миграций и коммита нет.
