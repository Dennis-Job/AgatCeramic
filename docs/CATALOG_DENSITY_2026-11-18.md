# Компактные фильтры каталога — 2026-11-18

Замечания владельца: поля, checkbox-строки, бейджи применённых фильтров
и пустые интервалы слишком большие; контур checkbox должен быть серым.

## Решение

Использованы явные компактные варианты общих primitives: изменение всех
форм магазина было бы шире запроса, а локальные переопределения общих
компонентов нарушили бы UI-kit. Размеры одинаковы на `/catalog`,
`/brand/<slug>` и `/preview/catalog`, включая мобильную панель фильтров.

- Поля и select от 36 px вместо 48/44 px; label gap 6 px.
- Checkbox-строки от 28 px вместо 44 px; квадрат 16 px, gap 8 px.
  По повторному уточнению владельца контур светло-серый `--color-line-strong`;
  checked — bg-soft с серой галочкой muted, Tab — видимый focus. Native input/label и режим
  forced colors сохранены. Светлый контур выбран по прямому уточнению владельца: его контраст к фону около 1.46:1, ниже 3:1; контрастные галочка и клавиатурный focus сохранены.
- Между группами и над разделителями 16 px вместо 24 px; legend/range gap 8 px.
- Applied chips и сброс от 28 px с padding 5×10 px вместо 44 px / 14×22 px;
  gap 8 px, перенос длинных подписей, меньше отступы toolbar/applied row.
- При coarse pointer поля, select, checkbox-строки, chips и ссылка
  «Весь каталог» имеют цель от 44 px.

## Изменённые файлы

- `frontend/client/app/components/ui/UiTextField.vue`, `UiSelect.vue`,
  `UiCheckbox.vue`, `UiButton.vue` — варианты размера и общие состояния.
- `frontend/client/app/features/catalog/components/CatalogFilterForm.vue`,
  `CatalogAttributeFilter.vue`, `CatalogPageBody.vue` — использование
  компактных controls и плотность компоновки.
- `frontend/client/e2e/catalog.spec.ts` — keyboard/forced colors/touch;
  `ui-select.spec.ts` — новая высота компактного trigger.
- `docs/CLIENT_UI_KIT.md` — актуальное UI-034 и новое UI-035;
  `tasks/DONE.md` — статус задачи.

## Проверки

- `npm run typecheck`, `npm run format:check`, `npm run build` — PASS.
- 68 Client E2E — PASS: catalog, ui-select, catalog-mobile-draft,
  catalog-brand-scope, brand-pages, ui-kit, field-labels, checkout,
  content-pages, preview. После финального уточнения серого контура
  catalog/ui-select повторены: 16 PASS.
- Проверки включают 320/640/768/1024/1280 px, keyboard, dropdown/Escape,
  draft/Apply, SSR/hydration, normal/reduced motion, axe, forced colors
  и реальные touch-цели. Сохранена прежняя явная оговорка axe для
  утверждённого placeholder ProductCard (контраст 4.25:1).
- Независимый UI Design Guard: код, документация и screenshots пяти ширин —
  PASS; блокирующих замечаний нет.
- Повторное уточнение владельца: тёмно-серый визуально остался чёрным.
  Контур заменён на светлый line-strong, чёрная checked-заливка удалена.
  Повторная сборка и 13 catalog E2E — PASS; обе формы checkbox сохранены
  отдельными screenshot-артефактами теста. Независимое ревью кода и обоих
  итоговых screenshot — PASS. Проверка артефактов повторена: 1 PASS.
- Реальный localhost просмотрен с компактными полями и chips; после
  финального перезапуска IAB перестал отвечать на команды просмотра.
  Финальная геометрия и серый контур проверены по production E2E screenshots;
  фрагмент тестового desktop: `.tmp/catalog-density-final.png`.

Первый build в sandbox упёрся в Windows EPERM при создании служебного
каталога Nitro; финальная сборка с нужными правами прошла. Для обновления
устаревших dev-модулей перезапущен только локальный Docker client.
Просмотр IAB и первый запуск ревьюера временно прерывались лимитом сервиса,
после команды владельца «продолжи» доступ восстановился.

Backend/API/БД и миграции не менялись. Safari/Firefox отдельно не проверялись.
Коммит не создавался.
