# Выпадающие категории каталога — 2026-11-18

По запросу владельца родительские категории в горизонтальном меню раскрывают список потомков. Дочерние категории больше не дублируются в верхнем ряду. Данные берутся из существующего публичного API по `parent_id`; после создания активной подкатегории и следующей загрузки каталога она появляется автоматически. Для страниц брендов сохраняется существующее ограничение категориями с товарами этого бренда.

Кнопка родителя со стрелкой открывает dropdown. Внутри есть «Все товары категории» и ссылки на все уровни потомков с отступами. Категории без детей остаются ссылками. Общее дерево и порядок API сохранены. На телефонах категории по-прежнему находятся в фильтрах и применяются кнопкой «Применить».

## Изменения

- `frontend/client/app/components/ui/UiNavigationDropdown.vue` — общий недоменный dropdown: native popover поверх scroll-контейнеров, существующие tokens и motion, ограничение высоты, прокрутка, keyboard, outside/route/resize cleanup.
- `frontend/client/app/features/catalog/components/CatalogCategories.vue` — корни в верхнем ряду, родительские dropdown и активная ветка выбранного потомка; мобильная ветка сохранена.
- `frontend/client/app/features/catalog/components/CatalogCategoryLinks.vue` — рекурсивные доменные ссылки, счётчики бренда и aria-current.
- `frontend/client/app/features/catalog/utils/catalogLocation.ts` — единый построитель ссылок категорий, сохраняющий прежний сброс page/attributes/collection.
- `frontend/client/e2e/catalog-category-dropdowns.spec.ts`, fixture `catalog-api.mjs` — раскрытие, произвольные новые дети, вложенность, длинный список, SSR, keyboard, axe, motion, sticky/resize и пять ширин.
- `frontend/client/e2e/brand-pages.spec.ts`, `catalog-brand-menu.spec.ts`, `catalog-sticky-categories.spec.ts` — адаптация к раскрытию родителя и проверка выхода фокуса из последнего control верхнего ряда.
- `docs/CLIENT_UI_KIT.md` — актуализировано UI-031; `tasks/DONE.md` — статус.

## Проверки

- RED: новый тест не находил кнопку «Сантехника» в прежнем плоском меню. GREEN: раскрытие и переходы проходят.
- `npm run typecheck` — PASS.
- Scoped Prettier и `git diff --check` — PASS.
- `npm run build` — PASS. В Windows-песочнице копирование public assets получало EPERM; тот же build вне песочницы завершился успешно.
- `npm run test:e2e -- catalog-category-dropdowns.spec.ts catalog.spec.ts catalog-sticky-categories.spec.ts brand-pages.spec.ts preview.spec.ts catalog-brand-menu.spec.ts` — **44/44 PASS** (44.3 s, Chromium/Windows).
- Независимый UI Design Guard — **PASS**, actionable findings нет. Проверены code, desktop, короткий sticky viewport, пять ширин и рекурсивные/длинные пункты. Первоначальные мобильные PNG при уходе drawer заменены снимками открытого дерева; открытый drawer при 640 px дополнительно просмотрен исполнителем.
- При проверке выявлена гонка тестовой прокрутки: мгновенный `window.scrollTo` сразу после SSR сбрасывался в 0 начальным router restoration. Прокрутка к реальной карточке через `scrollIntoViewIfNeeded` с ожиданием закрепления устранила сбой; код приложения для этого не менялся.
- Реальный `http://localhost:3000/catalog` проверен через браузер на данных разработки: «Сантехника» раскрывает пять дочерних категорий и ссылку родителя; список прокручивается в оставшейся высоте. Перезапущен только локальный Docker-сервис client, поскольку dev watcher сохранял старый модуль.

Миграций, изменения API-контракта, зависимостей и коммита нет. Ранее существовавшие изменения шапки/алфавита сохранены. Backend/Admin suites не запускались: их код не менялся.
