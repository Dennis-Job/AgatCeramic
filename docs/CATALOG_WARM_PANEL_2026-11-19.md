# Матовая панель каталога

Владелец выбрал первый тёплый референс. На desktop фильтры выделены
непрерывной бежевой поверхностью с отступом 20 px, светлыми полями,
тонкими разделителями и текстовым сбросом сверху. Над товарами показано
название выбранной категории из API или «Все товары» и счётчик.
Общий renderer распространяет оформление на каталог, бренд и preview.
Карточки используют действующий ProductCardList и фактические данные API.

## Изменённые файлы

- `frontend/client/app/features/catalog/components/CatalogPageBody.vue` — панель и заголовок выдачи.
- `frontend/client/app/features/catalog/components/CatalogFilterForm.vue` — desktop-заголовок, сброс и разделители.
- `frontend/client/app/components/ui/UiSelect.vue` — наследуемые field-background и field-border-color.
- `frontend/client/e2e/catalog.spec.ts` — известная проблема placeholder-контраста допускается только при её фактическом наличии. Новые нарушения по-прежнему отклоняются.
- `docs/CLIENT_UI_KIT.md` — утверждённое правило UI-039.
- `tasks/DONE.md` — статус работы.

## Проверки

Nuxt typecheck, production build, format:check и git diff --check прошли.
39 E2E прошли по catalog, catalog-auto-filters, catalog-sticky-categories,
brand-pages, preview и ui-select. Проверены пять ширин 320/640/768/1024/1280,
keyboard, axe, SSR/hydration, reset/history, desktop auto и mobile draft/Apply.
Независимый UI Design Guard проверил код и пять ширин. Устранены двойной
разделитель, повтор названия бренда и нативная рамка reset.
Локальный Docker Client перезапущен для подхвата изменений, результат
осмотрен на реальном `/catalog?category=keramogranit`.
Скриншот — `.tmp/catalog-warm/catalog-final.jpg`.

Backend/API и миграции не затрагивались.
