# Обращения: выбранный вариант 2

Владелец выбрал второй визуальный вариант: очередь обращений с фильтрами и
боковая карточка обработки. Существующие переходы статусов сохранены.

## Результат

- Статусные вкладки, поиск по поддерживаемым API полям, тип и ответственный.
  Таблица содержит номер, клиента, тип, статус, ответственного и дату;
  до 768 px используются мобильные карточки. По уточнению владельца таблица
  ограничена контейнером 1280 px, отдельный бордер над пагинацией убран.
- Инспектор справа шириной `min(544px, 100%)` под общей шапкой. Фон читаемый.
  Панель открывается поверх страницы; размеры и положение списка/шапки
  сохраняются, ширина scrollbar компенсируется при scroll lock.
  Появление и уход используют slide/fade 150 ms; reduced motion отключает движение.
- У имени, телефона, email и сообщения добавлены декоративные Lucide-иконки;
  сообщение размещено на серой подложке. Статусные badges всегда в одну строку:
  новое — зелёный, в обработке — голубой, ожидает клиента — жёлтый,
  завершено — серый, отклонено — красный.
- Для нового обращения основное действие — «Начать обработку». Ожидание
  клиента и завершение заблокированы с объяснением причины. Из обработки
  можно сразу завершить обращение; ожидание клиента необязательно.
- Завершение и отклонение имеют встроенное подтверждение необратимости.
  Конечные статусы не предлагают дальнейших переходов. Кнопки строятся из
  серверных метаданных; при их отсутствии действия остаются недоступными.
- Сохраняются назначение ответственного, история, комментарии, permissions,
  прямые ссылки из уведомлений и немедленное обновление счётчиков.
- Escape/клик вне панели закрывают её, черновик комментария защищён
  подтверждением. После закрытия фокус возвращается к строке, после сохранения
  и при подтверждении остаётся в карточке; во время сохранения закрытие запрещено.

Три рабочие вкладки показывают реальные глобальные итоги по всем типам обращений,
независимо от фильтров списка. Цвета счётчиков соответствуют статусам. Нули
отображаются; при ошибке загрузки неизвестные значения не подменяются нулями,
доступна повторная загрузка. Успешное сохранение статуса сразу обновляет итоги.
Реальные счётчики в шапке сохранены.

## API

Добавлен `GET /admin/contact-workflow` с `contacts.view`: список
`code/name/is_terminal/allowed_transitions`, полученный из существующего enum.
Старый `/admin/contact-statuses` и его строгая схема сохранены. Backend продолжает
проверять каждый переход независимо от интерфейса. API.md/OpenAPI обновлены;
проверка совместимости с HEAD пройдена. Миграций и изменений данных нет.

Для счётчиков добавлен `GET /admin/contact-request-counts` с `contacts.view`:
`data: {new, processing, awaiting_client}`. Агрегация находится в Query-слое,
без кеша и зависимости от фильтров таблицы; поколения запросов защищают
frontend от устаревшего ответа.

## Файлы

- `frontend/admin/src/features/contacts/components/`: ContactsWorkspace,
  ContactsList, ContactDetails, новый ContactStatusActions.
- `frontend/admin/src/features/contacts/composables/useContactsWorkspace.ts`,
  `services/contacts.ts`, `types/contact.types.ts`, `utils/contactStatusTone.ts`.
- `backend/app/Http/Controllers/Api/V1/Admin/ContactRequestController.php`,
  `backend/routes/api/v1/admin.php`, `backend/tests/Feature/Api/ContactWorkflowTest.php`.
- Для счётчиков: `backend/app/Queries/ContactRequestQuery.php`,
  `backend/tests/Feature/Api/ContactRequestCountsTest.php`.
- Общие primitives: `UiTabs.vue` (необязательный item slot), `UiDialog.vue`
  (необязательный transitionName и защита от повторной обработки Escape).
  Один Escape больше не вызывает два события закрытия; дефект воспроизведён
  отдельным unit-тестом перед исправлением.
- Admin tests: contactWorkflow, contactWorkflowApi, contactStatusActions;
  E2E: contactsRedesign, contactStatusFixtures и адаптация accessSalesContacts,
  adminBaselineApi, contactNotifications, notificationCounters, sellerWorkspaces.
- Уточнения проверяют также adminBaseline/navigation E2E, catalogComponents
  (однократное закрытие), contactDeepLink (обновлённый mock сервиса).
- Платформенные snapshots страницы contacts; API.md, openapi.json,
  ADMIN_SELLER_REDESIGN_PLAN.md, UI_KIT.md, tasks/DONE.md.

## Проверки первоначального варианта

- Admin build, ESLint, Prettier — PASS. Сохраняется существующее предупреждение
  Vite о размере общего bundle.
- Полный Admin unit — 226 tests / 43 files PASS.
- Backend workflow и API contract — 13 tests / 2789 assertions PASS;
  Pint, OpenAPI lint/conventions и compatibility gate — PASS.
- Финальный профильный E2E — 34/34 PASS: обращения, уведомления, счётчики,
  соседние Seller workspaces, все контактные baseline-состояния и адаптивность.
- Полный Windows E2E — 644 PASS из 648. Три известные pixel-разницы на
  `/products`, `/roles`, `/permissions` сохранены без изменения эталонов;
  четвёртый failure выявил потерю фокуса при отключении кнопки на время PATCH.
  Исправление проверено финальным профильным прогоном, включая управляемый
  длительный PATCH. Полный suite после этого локального исправления не повторялся.
- Linux contact baseline — 12/12 PASS; дополнительно новые workflow/drawer
  сценарии — 3/3 PASS. Четыре намеренно изменённых изображения
  default/loading/empty/error независимо приняты и обновлены для Linux и Windows.
  Darwin-эталоны не обновлялись: macOS runner недоступен.
- UI Guard независимо проверил выбранный референс, состояния default/loading/
  empty/error и адаптивные PNG. Замечания по blur и потере фокуса исправлены;
  сценарий длительного PATCH проверяет перенос фокуса на постоянный заголовок
  при отключении кнопки сохранения.
- Финальный независимый UI Guard: PASS по четырём Linux actual и desktop
  drawer 1280/1440; P1/P2 замечаний нет. Предыдущий Guard проверил все шесть
  адаптивных ширин и разрешил обновление четырёх Windows contact snapshots.

Локальные артефакты: `.tmp/contacts-redesign-final.log`,
`.tmp/contacts-redesign-full-e2e.log`, `.tmp/contacts-linux-final.log`,
`.tmp/contacts-linux-baseline-accepted.log`; финальные drawer PNG —
`.tmp/contacts-redesign-final/contactsRedesign-right-ins-ad32f-and-protects-comment-drafts-chromium/`.
Коммиты не создавались.

## Проверки после восьми уточнений владельца

- Build, ESLint и Prettier PASS; полный unit — 230 tests / 43 files PASS.
- Counts/workflow/catalog backend — 12 tests / 224 assertions PASS;
  реальный агрегат PostgreSQL проверен в read-only transaction без изменений
  таблиц/данных. Pint, targeted PHPStan, architecture guard, OpenAPI
  lint/conventions/tooling и compatibility gate PASS.
- Независимый UI Guard принял контейнер, overlay без сдвига, иконки/серый блок,
  пять цветов, реальные счётчики и nowrap на 320 px; три изменённых
  Windows/Linux baseline normal/loading/error приняты до копирования.
  Empty сохранил прежний эталон.
- Динамический motion-тест проверяет реальные CSS transition start/end,
  длительность нахождения уходящего слоя в DOM и reduced motion. Требование
  двух root transitionend заменено проверкой начала обеих фаз и длительности:
  штатный fallback Vue может удалить слой до доставки последнего события.
  Это не меняет длительность анимации продукта.
- Linux-профильный прогон — 18/18 PASS, включая принятые snapshots, все восемь
  уточнений, реальные счётчики, error/retry/zero и анимацию с reduced motion.
- Полный Windows E2E — 647 PASS / 651. Три прежние pixel-разницы
  `/products`, `/roles`, `/permissions` сохранены без обновления чужих эталонов.
  Четвёртый failure — устаревшее ожидание full-bleed ширины в sellerWorkspaces;
  оно заменено проверкой контейнера по прямому запросу владельца.
  Перепроверка длинных данных и всех ширин после обновления — PASS.
- Финальный Windows-профильный прогон после обновления ожиданий контейнера —
  37/37 PASS; продуктовый код после полной регрессии не менялся.
- Финальный узкий UI/code Guard PASS: optional motion не меняет остальные
  диалоги, Escape обрабатывается один раз, полный цикл движения и возврат
  фокуса подтверждены в Windows/Linux. Новых P1/P2 замечаний нет.

Артефакты уточнений: `.tmp/contacts-refinements-e2e.log`,
`.tmp/contacts-refinements-full.log`, `.tmp/contacts-refinements-linux-accepted.log`,
`.tmp/contacts-refinements-final.log`.

Общая задача TASK-A067 (устаревшие ответы заказов/обращений) остаётся открытой:
в этом изменении защищены выбор/закрытие карточки и запоздавшие mutation-ответы
обращения, но полный конкурентный сценарий списков и заказов не входил в редизайн.
