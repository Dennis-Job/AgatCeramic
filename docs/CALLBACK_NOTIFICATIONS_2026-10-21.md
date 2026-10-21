# Заявки на обратный звонок и уведомления — 2026-10-21

Последующее изменение владельца перенесло получателя с `ADMIN_EMAIL` в настройки
сайта. Актуальное назначение трёх адресов и формы сотрудничества описано в
[SITE_EMAIL_DESTINATIONS_2026-10-21.md](SITE_EMAIL_DESTINATIONS_2026-10-21.md).
Ниже сохранён результат первоначального этапа.

## Итоговый сценарий

По окончательному указанию владельца форма запрашивает только обязательный
телефон и необязательное имя. Email клиента не собирается; клиенту письмо по
этой заявке не отправляется. Client-код возвращён к исходной форме.

Новая заявка сохраняется в «Обращениях» и создаёт одну почтовую доставку на
`backend/.env: ADMIN_EMAIL`. Это адрес уведомлений, а не команда изменения email
учётной записи. Сейчас настроенный адрес не совпадает с email существующих
сотрудников; учётные записи не менялись. В письме есть номер заявки, имя, телефон
в утверждённом формате и ссылка на нужное обращение через `ADMIN_APP_URL`.

Колокольчик показывает пять последних callback-заявок со статусом `new` и
общее количество из `meta.total`; список обновляется при открытии и каждые
30 секунд. Уведомление остаётся до смены статуса обращения, открытие не меняет
статус. Ссылка `/contacts?contact={id}` открывает конкретную карточку. Доступ
определяется `contacts.view`, роли и email не хардкодятся во frontend.

## Исправленная причина ошибки

Запрос с `Origin: http://localhost:3000` возвращал HTTP 419: storefront включён
в stateful-домены Sanctum, а публичная форма отправляет запрос без CSRF-токена.
Добавлено узкое исключение только для `api/v1/callback-requests`. Admin login
без CSRF по-прежнему получает 419. Rate limit и validation сохраняются.
На работающем API повторный запрос с Origin и пустым JSON теперь возвращает
ожидаемый 422, а не 419.

## Надёжность почты

Обращение и `callback_mail_deliveries` создаются одной транзакцией. В Redis
передаётся только ID доставки после commit. Сбой очереди/SMTP не отменяет заявку;
scheduler восстанавливает pending-задачи. Успех отмечается под row lock, повторная
обработка уже успешной задачи не отправляет письмо заново. SMTP имеет обычное
ограничение at-least-once при аварии между приёмом письма сервером и записью
успеха в БД. Лимит девять попыток проверяется и в job, и в recovery; явное
восстановление описано в [QUEUE.md](QUEUE.md).

Клиентские доставки, если они остались от промежуточной версии, пропускаются
без отправки. После удаления/обезличивания обращения письмо не отправляется.
Контакты и SMTP-ошибки не копируются в job payload или обычные логи.

Миграция применена в локальном окружении без изменения существующих обращений.
После обнаружения остановленных сервисов выполнен `docker compose up -d`;
все семь рабочих сервисов запущены, PostgreSQL/Redis healthy. Queue/scheduler
перезапущены с актуальным кодом. Причина остановки и связь с сообщённой
блокировкой Касперского инструментами не подтверждены; настройки антивируса не менялись.

## Проверки

- Backend: **524 PASS, 1 SKIP, 6042 assertions**. Пропуск — существующая проверка
  Unicode LOWER на SQLite; она предназначена для PostgreSQL.
- PostgreSQL callback feature: **12 PASS, 62 assertions**, отдельная тестовая БД.
- Реальный Redis worker с PostgreSQL: **1 PASS, 111 assertions**: after-commit,
  только admin-доставка даже при историческом email клиента, ID-only payload,
  успешная отправка через тестовый mailer и пропуск повторной job.
- Laravel Pint, PHPStan, architecture guard, `composer validate --strict` и
  `composer audit --locked`: PASS.
- OpenAPI backward compatibility с HEAD: PASS, payload callback не меняется.
- SMTP: TLS и AUTH успешны. Реальные письма на внешние адреса при проверках
  не отправлялись; worker-проверка использовала mailer `array`.
- Client после удаления email: production build, typecheck и callback/phone E2E
  **10/10 PASS**, включая пять ширин, валидацию, SSR, focus и состояния отправки.
- Admin: lint, format, production build и **200 unit tests PASS**; новые сценарии
  уведомлений **4/4 E2E PASS**. Полный production E2E: **636 PASS, 3 FAILED**, 0 flaky,
  0 skipped, 5.5 минуты. Все три failures — исходные snapshot-расхождения,
  перечисленные ниже и воспроизведённые на HEAD без изменений задачи.
- Независимый UI Design Guard: PASS, blocking findings отсутствуют. Проверены
  320/640/768/1024/1280 px, loading/error, keyboard/Escape, переход к обращению,
  возврат к списку, сохранение focus при polling и retry. Палитра сохранена.
- В полном Admin-прогоне обнаружены отсутствовавшие mock-ответы нового shell-запроса
  в navigation fixture; добавлен пустой ответ только для запроса уведомлений.
  Три исходных snapshot-расхождения отдельно воспроизведены на копии HEAD `ba87cf1`:
  `Admin baseline: /products` — 244 pixels, `/roles` и `/permissions` — по 313 pixels.
  Отличается только моноширинный SKU/код permission; UI этой задачи не затрагивает
  эти элементы. Три теста на исходном HEAD также FAILED; PNG текущего рендера
  совпадают с PNG рендера HEAD побайтно для всех трёх маршрутов. Эталоны не обновлялись.
- Первоначальный timeout Packagist устранён после восстановления Docker-сети;
  повторный audit завершился без security advisories. Зависимости задачи не менялись.

## Изменённые файлы

- Backend: `app/Services/CallbackRequestService.php`, новый `CallbackMailService.php`,
  `app/Jobs/SendCallbackMail.php`, `app/Mail/CallbackRequestMail.php`,
  `app/Models/CallbackMailDelivery.php`, `app/Console/Commands/RetryCallbackMail.php`,
  migration `2026_10_21_000001_create_callback_mail_deliveries_table.php`,
  `bootstrap/app.php`, `config/mail.php`, `routes/console.php`, `.env.example`.
- Backend tests: `tests/Feature/Api/CallbackNotificationTest.php`,
  `tests/Integration/RedisQueueDeliveryTest.php`.
- Admin: `src/layouts/components/AdminNotifications.vue`,
  `src/features/contacts/composables/useContactNotifications.ts`,
  `useContactsWorkspace.ts`, `components/ContactsWorkspace.vue`, `services/contacts.ts`;
  unit/E2E regressions для уведомлений, deep link, focus и baseline API fixture.
  Тестовые fixtures: `e2e/adminBaselineApi.ts`, `e2e/navigation.spec.ts`,
  `e2e/headerPopovers.spec.ts`, новый `e2e/contactNotifications.spec.ts`,
  `tests/contactNotifications.test.ts`, `tests/contactDeepLink.test.ts`.
- Документация: `API.md`, `openapi.json`, `DATABASE.md`, `QUEUE.md`, `CLIENT_UI_KIT.md`,
  этот отчёт и статус задачи.

Пути Backend/Admin выше относительны соответствующим приложениям. `backend/.env`
и учётные записи не изменялись. Commit не создавался.
