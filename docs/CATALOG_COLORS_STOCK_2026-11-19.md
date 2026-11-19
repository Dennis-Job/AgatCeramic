# Цвета controls и наличие в карточках

По новым референсам владельца выбранный квадратный checkbox и включённый
switch имеют ink-фон и белый индикатор. У range выбранная часть ink,
остальные участки line. Общая ProductCard показывает под ценой фактическое
«В наличии» с зелёной точкой либо «Нет в наличии» с серой точкой из in_stock API.
Оттенки точек подобраны по референсу и вынесены в tokens.

Изменены UiCheckbox.vue, UiRange.vue, ProductCard.vue, tokens.css,
catalog.spec.ts, catalog-filter-controls.spec.ts, catalog-card-standard.spec.ts,
CLIENT_UI_KIT.md и tasks/DONE.md. Это дополнение к предыдущим правкам текущего чата.

Typecheck, production build, format:check и git diff --check PASS.
37 E2E PASS по catalog, auto-filters, filter-controls, catalog-card-standard
и product-pages. Проверены обе доступности, цвета selected controls, range,
SSR/hydration, axe, keyboard, forced colors, touch и пять ширин
320/640/768/1024/1280 px. Независимый UI Design Guard — PASS.
Реальный каталог осмотрен на localhost, снимок
`.tmp/catalog-warm/catalog-stock-final.jpg`.
