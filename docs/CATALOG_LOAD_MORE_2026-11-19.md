# Подгрузка товаров в каталоге

Владелец выбрал счётчик показанного количества, тонкую полосу прогресса
и контурную кнопку «Показать ещё K» с тёмным hover по референсам.
Следующие страницы добавляются к текущему списку. После полной загрузки
кнопка скрывается, счётчик сохраняется. Ошибка позволяет повторить запрос.
Смена фильтров, сортировки или категории очищает добавленные страницы.
SSR-ссылки на следующие страницы, прямые page-URL и API сохраняются.

## Изменённые файлы

- `frontend/client/app/features/catalog/composables/useCatalogLoadMore.ts` — накопление, scope/reset, отмена, retry и дедупликация.
- `frontend/client/app/features/catalog/components/CatalogPageBody.vue` — счётчик, progress, ссылка-кнопка и focus.
- `frontend/client/e2e/catalog-load-more.spec.ts` — append, SSR, reset, ошибки, повторы, stale responses, keyboard и mobile.
- `frontend/client/e2e/catalog.spec.ts`, `brand-pages.spec.ts`, `content-pages.spec.ts` — актуальные ожидания добавления страниц.
- `docs/CLIENT_UI_KIT.md`, `tasks/DONE.md` — правило UI-042 и статус.

Подгрузка работает в общем каталоге, брендах и сохранённом preview.
Обычные встроенные каталоги контентных страниц используют свой renderer.
Количество берётся из ответа API. Размер начальной страницы выводится из её
данных; для прямых ссылок на поздние страницы используется действующий
фиксированный API page size 24. Бизнес-данные и API-контракт не менялись.

## Проверки

Nuxt typecheck, production build, format:check и git diff --check PASS.
46 E2E PASS по catalog-load-more, catalog, brand-pages, catalog-auto-filters,
content-pages и preview. После добавления точной проверки scrollY и тёмного
hover повторный профильный прогон 4/4 PASS. Проверены SSR href, сохранение
первых карточек, dedupe, повтор нажатия, stale response после смены фильтра,
retry после503, sorting reset, прямой page URL, mobile, focus и отсутствие
прокрутки при завершении. UI Design Guard — PASS.

Реальный localhost с выбранными фильтрами показывает «Показано 2 из 2».
Снимок `.tmp/catalog-warm/catalog-more-final.jpg`. Для демонстрации состояния
с оставшимися товарами использована синтетическая E2E-выдача 24 из 27;
снимки load-more-ready.png и load-more-hover.png находятся в
`.tmp/client-e2e/catalog-load-more-SSR-next-2b951-gress-and-resets-on-sorting/`.
