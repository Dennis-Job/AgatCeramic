# Отдельные страницы массовых действий с товарами

Дата: 2026-10-02. Основание — три browser comments владельца и запрос
перенести массовые действия в меню «Товары», заменив модальные окна страницами.

## Результат

В группе «Работа с товарами» добавлены:

| Пункт меню              | Маршрут                  | Сценарий                                                                      |
| ----------------------- | ------------------------ | ----------------------------------------------------------------------------- |
| Добавить массово товары | `/products/import`       | Добавление/редактирование по шаблону категории и загрузка ZIP с изображениями |
| Цены и статусы          | `/products/price-status` | Изменение цен, активности и распродажи через Excel                            |
| Объединить товары       | `/products/combine`      | Управление группами вариантов через Excel                                     |

На странице списка оставлены экспорт Excel и добавление одного товара.
Новые страницы используют общий `AdminWorkspace`, `PageHeader` и существующие
`Ui*` controls; логика шаблонов, валидации, отправки, polling и отчётов сохранена.
Backend, API/OpenAPI и миграции не менялись.

## Навигация и жизненный цикл

- Desktop и компактное меню получают ссылки из единой конфигурации.
- Для меню и прямого открытия страниц нужны оба действующих права:
  `catalog.manage` и `imports.manage`.
- «Список товаров» использует точное совпадение маршрута: на новой странице
  активна только соответствующая ссылка; верхний раздел остаётся «Товары».
- `KeepAlive` сохраняет только три импортные страницы при SPA-переходах.
  Выбранные файлы, параметры и результат не теряются при переходе к списку
  и обратно; polling продолжает работать в фоне.
- Неактивные страницы не показывают уведомления и не переводят фокус.
  Cache сбрасывается при выходе, смене пользователя или его набора прав;
  scope disposal прекращает polling.
- Состояние сохраняется в памяти текущей SPA-сессии, без browser storage.
  Перезагрузка вкладки сбрасывает локальный результат; серверная обработка
  продолжается, как и до изменения.

## Проверка

- Production build и TypeScript: пройдены (с обычным предупреждением о размере bundle).
- `npm run lint`: пройден.
- `npm run format:check -- --ignore-path .prettierignore --ignore-path ../../.gitignore`:
  пройден; сгенерированные временные E2E-артефакты исключены из проверки.
- Unit: 69/69.
- Полный macOS Chromium E2E: 267/267, два workers, лимит 90 секунд.
  Первичный прогон выявил пять ожидаемых visual differences после удаления кнопок,
  восемь timeout длинных responsive-проверок, timeout appearance и неточный
  test locator обязательного поля пароля. Эталоны обновлены после независимого
  просмотра; selector исправлен; повторный полный прогон зелёный.
- Новые страницы: default/error/processing, downloads и retry; прямые URL и reload;
  widths 320/640/768/1024/1280 и axe без нарушений; nav widths 320–2560.
- Проверены оба permissions, единственная активная ссылка, сохранение файлов и
  результатов при переходах, отсутствие переноса фокуса/уведомлений на другую
  страницу, очистка cache при logout/login.
- UI Design Guard: accepted. Независимый lifecycle test: 1/1.
- Linux Compose: production build пройден; imports/navigation 37/37;
  product baseline и responsive 6/6. Пять Linux эталонов обновлены только
  после независимого просмотра UI Design Guard; macOS эталоны проверены отдельно.
- `git diff --check` и repository hygiene: пройдены.

E2E используют контролируемые mock API; данные реального каталога при проверке
не изменялись. Windows-прогон не выполнялся; его визуальные эталоны не заменяются снимками
другой платформы. Responsive/default/error/processing и downloads проверяются
в `e2e/productImport.spec.ts`; все ссылки и их права — в `e2e/navigation.spec.ts`.

## Изменённые файлы

- `frontend/admin/src/App.vue` — ограниченный cache импортных страниц.
- `frontend/admin/src/layouts/navigation.ts`, `src/router/index.ts` — меню,
  активные ссылки и защищённые маршруты.
- `frontend/admin/src/views/ProductImportView.vue`,
  `ProductPriceStatusImportView.vue`, `ProductGroupImportView.vue` — тонкие views.
- `frontend/admin/src/features/products/components/ProductImportWorkspace.vue`,
  `ProductPriceStatusImportWorkspace.vue`, `ProductGroupImportWorkspace.vue` —
  страницы вместо одноимённых `*Dialog.vue`.
- `frontend/admin/src/features/products/components/ProductEditor.vue`,
  `composables/useProductEditor.ts` — удалены старые импортные кнопки/диалоги/флаги.
- `frontend/admin/src/features/products/composables/useProductImport.ts` —
  переименован `useProductImportDialog.ts` в соответствии с новым назначением.
- `frontend/admin/e2e/productImport.spec.ts`, `e2e/navigation.spec.ts`,
  `tests/catalogComponents.test.ts` — актуальные сценарии и границы компонентов.
- Десять macOS/Linux `route-products*` visual baseline — намеренное удаление трёх кнопок,
  подтверждённое независимым UI Design Guard перед обновлением.
- Документация: этот отчёт, `ADMIN_SELLER_REDESIGN_PLAN.md`, `CURRENT_STATE.md`;
  task ledger: `tasks/IN_PROGRESS.md`, `TODO.md`, `DONE.md`.

Рабочее дерево уже содержало незакоммиченные изменения до начала задачи;
они сохранены. Commit не создавался.
