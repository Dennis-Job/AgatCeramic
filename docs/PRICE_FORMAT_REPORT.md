# TASK-A057 — единый формат цен

Завершено 2026-10-02. Все существующие отображаемые цены Admin и Client используют
`ru-RU`: `17 926,00 ₽`, неразрывные пробелы для тысяч и валюты, две цифры копеек.
Формат применяется к списку и сводке товара, суммам заказов/строк заказа, выручке
и публичному каталогу. DTO, API и база не менялись; миграции
и обновление OpenAPI не требуются. Публичный каталог сохраняет «Цена по запросу».

Приложения содержат собственные чистые утилиты: Compose монтирует каждое
приложение отдельно в `/app`, поэтому зависимости на соседнее приложение нет.

Изменённые файлы:

- `frontend/admin/src/utils/formatMoney.ts` и `frontend/client/app/utils/formatMoney.ts`.
- `frontend/admin/src/features/products/components/ProductEditor.vue` и `ProductReviewSection.vue`.
- `frontend/admin/src/features/orders/composables/useOrdersWorkspace.ts`.
- `frontend/admin/src/views/DashboardView.vue`.
- `frontend/client/app/features/content/components/CatalogListing.vue`.
- `frontend/admin/e2e/catalog.spec.ts`, `orders.spec.ts`; `frontend/client/e2e/content-pages.spec.ts`.
- Четыре эталона `route-products`/`route-dashboard` для macOS и Linux в
  `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/`.
- `frontend/admin/src/components/shared/UI_KIT.md`, `docs/CLIENT_UI_KIT.md`,
  этот отчёт и `tasks/DONE.md`.

Проверки:

- Admin: lint, format:check, TypeScript/Vite build, 64 unit-теста — успешно.
- Admin: catalog/orders E2E — 55/55; проверены price display, исходный numeric
  input, состояния, permissions, клавиатура и axe.
- Client: Nuxt typecheck, SSR build, format:check и формат изменённого E2E — успешно.
- Client: content-pages E2E — 9/9; цена проверена в SSR HTML и после hydration.
- Визуально просмотрены Admin/Client на 320/640/768/1024/1280 px, включая
  колонку цены после локальной прокрутки Admin-таблицы.
- Визуальные baseline `/` и `/products` — 2/2 на macOS и 2/2 в Linux Docker.
  Перед сохранением эталонов просмотрены diff: изменены денежный текст и
  распределение ширины табличных колонок.
- Независимый UI Design Guard принял результат, blocking findings отсутствуют.
- `git diff --check` — успешно.

Ограничения: полный E2E всего проекта и Windows-прогон не выполнялись;
Windows-эталоны этих двух экранов требуют переснятия на Windows. Vite сохраняет
имеющееся предупреждение о chunk больше 500 kB. Открытый `localhost:5173`
использует `/Users/dennis.a.k/Downloads/AgatCeramic/frontend/admin`; изменения
сделаны в worktree `f614` и проверены отдельными preview/SSR-серверами.
Commit не создавался.

## Исправление реально запускаемой копии, 2026-10-03

После сообщения владельца исправление точечно применено к
`/Users/dennis.a.k/Downloads/AgatCeramic`, которую Docker монтирует в Admin/Client.
Её Seller-разметка сохранена; dashboard форматируется в `DashboardWorkspace.vue`.
В открытом `localhost:5173/products` подтверждены все 11 цен, включая
`17 926,00 ₽`, `36 304,00 ₽` и `5 957,00 ₽`. Перезапуск контейнеров не нужен:
Vite HMR получил изменения; страница обновлена и проверена в браузере.

В запускаемой копии прошли Admin lint/build, 73 unit, 55 catalog/orders E2E,
Client typecheck/SSR build и 9 content-pages E2E. Снимки desktop и локально
прокрученной таблицы на узком экране сохранены в `.tmp/price-format/` worktree.
Полный platform baseline этой Seller-копии повторно не запускался.


## Денежные поля ввода, 2026-10-03

По уточнению владельца формат включён и во всех существующих денежных полях:
цена и старая цена товара (создание, редактирование, создание похожего товара),
сумма оплаты в рабочем месте заказа. Инвентаризация Admin/Client подтвердила:
других денежных inputs сейчас нет; таблицы, сводки, выручка и публичный каталог
уже используют форматирование вывода.

Общий `UiInput` получил opt-in `money`, реализованный через чистые helpers в
`src/utils/moneyInput.ts`. Поле показывает неразрывные пробелы между тысячами
и запятую; v-model хранит точную decimal string с точкой без пробелов. Вставка
суммы с пробелами, точкой/запятой и символом рубля допустима. После blur/Enter
копейки дополняются до двух цифр. Промежуточные дробные значения сохраняются
без добавления ведущего нуля до blur: `,5` → `0,50`. Это сохраняет корректную
позицию курсора при вводе и удалении цифр. Required/min/max, disabled/readonly
и очистка необязательной старой цены сохранены. Количества, SKU и артикулы
не изменены. API-контракт и миграции не менялись.

Дополнительные изменённые файлы:

- `frontend/admin/src/components/ui/UiInput.vue`, `src/utils/moneyInput.ts`.
- `frontend/admin/src/features/products/components/ProductMainSection.vue`.
- `frontend/admin/src/features/orders/components/OrderDetails.vue`.
- `frontend/admin/tests/moneyInput.test.ts`, `e2e/money-input.spec.ts`;
  `e2e/catalog.spec.ts`, `e2e/orders.spec.ts`.
- UI-kit документация Admin/Client и статус в `tasks/DONE.md`.
- В запускаемой Seller-копии также актуализирован денежный текст в
  `e2e/productWorkspace.spec.ts`.

Проверки последней реализации:

- Worktree: lint, Prettier для src и изменённых тестов, build и 76 unit — успешно.
- Worktree: money-input/orders E2E — 13/13. Проверены raw PATCH price/old_price,
  old_price=null после clear, payment_amount, посимвольный ввод копеек,
  Backspace у разделителя, 320/640/768/1024/1280 и отсутствие серьёзных axe findings.
- Запускаемая Seller-копия: lint/build, 85 unit — успешно.
- Полный Seller Admin E2E — 273/279: все функциональные сценарии, включая новые
  денежные тесты, прошли. Шесть расхождений только в visual baseline: пять
  известных несовпадений Seller-экрана products и намеренное изменение формата
  выручки dashboard с `0 ₽` на `0,00 ₽`. Dashboard macOS snapshot после
  независимого просмотра принят; повторный точечный baseline — 1/1. Остались
  пять прежних products baseline mismatches; их эталоны не перезаписывались.
- Полный worktree Admin E2E первоначально дал 175/183. Два новых теста затем
  исправлены и прошли в прогоне 13/13; ещё шесть failures относятся к Content
  (четыре устаревших снимка, контраст кнопки предпросмотра, публикация текста).
  Эти несвязанные элементы в рамках задачи не изменялись.
- В реальной открытой модалке `http://localhost:5173/products` после HMR
  подтверждены `17 926,00` и `24 459,00`; бизнес-данные не сохранялись.
  Снимок: worktree `.tmp/price-format/money-modal-live.jpg`.
- Независимый UI Design Guard осмотрел исходники и все пять responsive-снимков,
  включая live Seller-модалку; найденный edge case курсора исправлен и покрыт E2E.

Ограничения: полный Admin suite не является зелёным из-за перечисленных
несвязанных baseline/Content проверок. Windows не запускался. Client исходники
не изменялись этой дополняющей правкой; проверки отображаемых цен приведены выше.
Commit не создавался.

Запускаемая копия также прошла Prettier для `src` и изменённых тестов;
`git diff --check` успешен в обеих копиях. Полный `format:check` запускаемой
копии имеет ранее созданные неформатированные `.tmp`-артефакты, поэтому
source-only проверка указана отдельно. Linux/Windows для новой правки ввода
не запускались. UI Design Guard рекомендовал принять результат после исправления
курсора; описание `UiInput money` внесено в актуальный UI-kit обеих копий.
