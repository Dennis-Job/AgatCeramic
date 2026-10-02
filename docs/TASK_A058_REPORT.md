# TASK-A058 — верхняя глобальная навигация

Дата: 2026-10-01. Ветка: `codex/admin-seller-redesign`.
Статус: завершена; независимый UI Design Guard принял результат.

## Результат

По предоставленным референсам Ozon Seller Admin получил белую двухстрочную
шапку: бренд, существующий поиск, уведомления и профиль сверху; глобальные
разделы снизу. Ссылки меню текстовые, без прежних иконок. Боковая глобальная
панель и отступ 290 px удалены, доступная ширина рабочих областей освобождена.

- Прямые ссылки: Главная, Заказы, Обращения, Настройки.
- Товары: работа с товарами, справочники каталога, характеристики товаров.
- Контент: страницы, общее оформление, магазины; медиатека доступна только
  с собственным `media.manage`, независимо от `content.manage`.
- Управление: сотрудники и доступ, журнал аудита, служебный UI-kit.
- Desktop и компактное меню используют одну конфигурацию `navigation.ts`.
  Пустые группы скрываются, roles не хардкодятся. Верхний активный раздел
  учитывает вложенные пути, а ссылки контента — query `section`.
- Группы открываются кликом, Enter/Space или ArrowDown; desktop поддерживает
  Tab, стрелки, Home/End, Escape, закрытие вне меню и возврат фокуса.
- Ниже 1024 px навигация переходит в прокручиваемый `UiDialog` с общими
  ссылками, focus trap, Escape, безопасным backdrop close и возвратом фокуса.
  Фоновая шапка и main получают `inert` на время открытия.
- Максимум шапки 1280 px и высота 109 px (включая границу) заданы через
  shell tokens. Dropdown находится вне overflow рабочих таблиц;
  scroll-margin учитывает закреплённую шапку.

Маршруты, guards, бизнес-действия и unsaved guards сохранены. Новый глобальный
поиск не реализован; перенесено существующее поле. Режимы ширины страниц и
подробные таблицы относятся к следующим задачам. Backend, Client, API,
OpenAPI и миграции не изменялись.

## Проверки и независимое ревью

- `npm run lint` — пройдено.
- `npm run build` — пройдено; существующее предупреждение о JS chunk >500 kB.
- `npm run test:unit` — 64/64.
- Полная Windows production E2E-регрессия после обновления baseline — 196/196,
  включая mobile axe и безопасный backdrop drag; строгий прогон baseline
  без перезаписи — 77/77.
- Полная Linux production E2E-регрессия в изолированном Docker — 196/196,
  один worker, retries=0; strict baseline без перезаписи — 77/77.
- Responsive visual harness: 320/640/768/1024/1280/1440/1920/2560 px,
  page overflow отсутствует; длинное имя ограничено шириной в шапке.
- Независимый UI Design Guard проверил код, меню, все восемь ширин и 57
  изменившихся actual snapshots и ещё девять UI-kit crops каждой платформы
  Windows/Linux: всего 66 изменённых эталонов на платформу. Blocking findings устранены:
  компактное меню использует общий `UiDialog`, клик вне desktop меню
  корректно возвращает фокус. Намеренные visual изменения одобрены.
- Первый полный Windows E2E: 138 пройдено, 57 ожидаемых snapshot differences.
  Все ошибки — сравнение изображений. Они включают смену shell и
  восстановленную по указанию владельца типографику TASK-A057, чьи baseline
  ранее не обновлялись. Эталоны обновлены после просмотра.

Повторный unit-прогон с одним worker прошёл 64/64; промежуточный параллельный
прогон имел один 5s timeout в неизменённом тесте `UiDatePicker` при одновременной
нагрузке Windows/Linux E2E. Лимиты теста и реализация не менялись.

Первый Linux прогон дал 136 пройденных / 60 непройденных: 57 ожидаемых
visual differences и три 30s timeout при общей нагрузке в тестах employees,
axe и appearance. Все три прошли в полном прогоне с одним worker;
исходники тестов, лимиты и реализация этих features не менялись.
Linux исходники сверены с рабочей копией, отдельный container использует
изолированный build и только E2E dependency/browser volumes; работающие
Admin/Client/backend services не изменялись.
После проверки task container удалён, E2E/browser caches сохранены;
действующие семь services остались запущены.

macOS runner в этой среде отсутствует. `*-chromium-darwin.png` не заменены
эталонами других платформ; их обновление требует реального macOS прогона.

Полный `npm run format:check` обнаруживает прежние CRLF/LF расхождения,
временные `.tmp` файлы и неизменённые нарушения форматирования. Проверка
`src/e2e/tests` с `--end-of-line auto` оставляет два прежних нарушения:
`SlidersWorkspace.vue` и `tests/uiKitShowcase.test.ts`. Все изменённые source
файлы проходят Prettier; несвязанные features не переформатируются.

Evidence хранится локально в `frontend/admin/.tmp/a058-*`: production логи,
actual screenshots и responsive снимки. Windows full/strict logs:
`a058-update.log`, `a058-strict.log`; Linux evidence: `a058-linux-qa/`.
Repository hygiene и `git diff --check` пройдены. Изменения API и миграций
отсутствуют, diff backend/Client пустой.

## Изменённые файлы

Пути ниже относятся к `frontend/admin/`, если не указан другой корень:

- `src/layouts/AdminLayout.vue`, `components/AdminHeader.vue`,
  `AdminNavigation.vue`, `AdminNavigationLinks.vue`, `AdminUserMenu.vue`,
  `navigation.ts`; прежний `AdminSidebar.vue` удалён.
- `src/styles/navigation.css`, `tokens.css`, `utilities.css`, `index.css`.
- `src/components/shared/UiKitShowcase.vue`, `UI_KIT.md` — shell tokens и
  актуальный путь к витрине.
- `e2e/catalog.spec.ts`, `e2e/navigation.spec.ts`, просмотренные platform
  snapshots в `e2e/adminBaseline.spec.ts-snapshots/`.
- `docs/ADMIN_SELLER_REDESIGN_PLAN.md`, этот отчёт и task ledger.

Commit и push не создавались.

## Последующая доработка 2026-10-02

Уточнение владельца: единый контейнер меню и нетабличных блоков всех страниц,
широкие рабочие таблицы. Итог и актуальные проверки описаны в
[отчёте доработки](ADMIN_CONTAINER_REFINEMENT_REPORT.md).
Результаты выше относятся к первоначальной приёмке и сохранены как история.

## Доработка шапки от 2026-10-02

Уточнение по второму референсу владельца и текущие результаты проверок
описаны в [`ADMIN_HEADER_REFINEMENT_REPORT.md`](ADMIN_HEADER_REFINEMENT_REPORT.md).
Исходные результаты этапа выше сохранены как исторический срез.
