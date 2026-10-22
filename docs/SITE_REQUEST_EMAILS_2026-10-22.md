# Письма по заявкам сайта — 2026-10-22

## Результат

- В настройках сайта блок телефона и «Email для сотрудничества» поменяны местами.
  На desktop все три email находятся в правой колонке; телефонная маска,
  старые неоднозначные номера и сохранение формы не изменены.
- Новые partner/email-обращения атомарно создают две независимые доставки:
  сотруднику на актуальный `notification_email` и подтверждение отправителю.
  Callback по-прежнему уведомляет только сотрудника; email клиента эта форма
  не принимает. Исторические обращения автоматически не рассылаются.
- Подтверждение содержит AgatCeramic, номер обращения, публичное имя продавца,
  телефон, обе публичные почты и адрес, если они заполнены. Reply-To использует
  партнёрскую почту для партнёра (либо общую), общую — для email-обращения.
  Служебный email и ссылки админки не раскрываются. Содержимое экранируется.
- Письмо использует утверждённую палитру Client, AGAT/CERAMIC sans-serif 600/400,
  fluid table и inline CSS без Blade, внешних шрифтов и картинок.
- Сохранён существующий durable outbox `callback_mail_deliveries`, ID-only job,
  after-commit dispatch, лимиты повторов и recovery `callbacks:retry-mail`.
  У каждого получателя свой статус и счётчик. `log`, alias и вложенные
  failover/roundrobin с log блокируются: письма остаются pending без записи PII.

## Локальное окружение и SMTP

При диагностике обнаружены неприменённые почтовые миграции и `MAIL_MAILER=log`
без SMTP-логина/пароля. Применены только существующие миграции
`2026_10_21_000001` и `2026_10_21_000002`; исходная публичная почта сохранена.
При первоначальной настройке page indexing оставалась pending. По последующему
сообщению владельца о 500 при `POST /admin/pages` установлено отсутствие
`pages.is_indexable`; миграция `2026_10_20_000000_add_page_indexing` применена.
Теперь `migrate --pretend` возвращает `Nothing to migrate`. Создание страницы
проверено через PageManagementService с Policy в рабочей PostgreSQL с откатом
тестовой записи (остаток 0); профильные PostgreSQL PageManagement/PageIndexing:
8/8 тестов, 99 assertions PASS. Код миграций и страниц не менялся.
Queue и scheduler перезапущены для нового кода. Во время финальных проверок
владелец настроил `MAIL_MAILER=smtp`, логин и пароль уже подхватились.
По явно указанному владельцем адресу поле `notification_email` сохранено через
`SiteSettingManagementService` с проверкой SiteSettingPolicy и аудитом, без
изменения других настроек. Значение email в отчёт/логи не копируется.

Владелец сообщил, что настроит SMTP самостоятельно в `backend/.env`.
После настройки `MAIL_*` (включая `MAIL_MAILER=smtp`) необходимо выполнить:

```sh
docker compose exec backend php artisan config:clear
docker compose restart queue scheduler
```

«Email для уведомлений» уже заполнен по указанию владельца.
Ожидающие доставки автоматически подхватит scheduler; при необходимости:

```sh
docker compose exec backend php artisan callbacks:retry-mail
```

Подключение к реальному SMTP проверено без отправки письма: TCP timeout к
`smtp.yandex.ru` на 465 и 587 повторяется как из backend-контейнера, так и из
macOS (5 секунд на попытку). SMTP-сессия не дошла до авторизации. При повторной диагностике обычное HTTPS-соединение работало, прямой TCP к SMTP
оставался недоступен; владелец подтвердил включённый VPN/фильтр. После проверки
сетевого доступа владелец сообщил, что письма начали приходить. Пароль не
выводился и не менялся.
Итоговая проверка БД: все пять созданных доставок имеют `sent`, включая обе
доставки последних партнёрских заявок; последняя пара прошла с первой попытки.
Ранее ожидавшие письма также успешно отправлены автоматическими повторами.
Владелец подтвердил получение. Отдельные искусственные тестовые письма не
создавались; автоматические тесты используют синтетические данные и mailer array.
Отображение в конкретных приложениях Outlook/Gmail отдельно не проверялось.

## Проверки

- Полный backend suite: 560 tests, 6254 assertions, 1 существующий SQLite skip.
- PostgreSQL: 33 профильных теста, 231 assertions PASS, отдельная тестовая БД.
- Реальный Redis worker / PostgreSQL: 1 integration test, 142 assertions PASS,
  включая partner after-commit, ID-only payload, обе доставки и повтор jobs.
- Pint, PHPStan level 8, architecture guard, composer validate/audit PASS.
- OpenAPI backward compatibility PASS; payload и response не менялись,
  обновлено описание почтовых side effects.
- Admin: lint/format/build, 237 unit PASS; settings production E2E 3/3 PASS
  на macOS, включая пять ширин, axe, keyboard flow и сохранение после 422.
  macOS `End` не гарантирует конец input: тест устанавливает курсор через
  selection API перед Backspace; реализация телефонного primitive не менялась.
- Финальные production settings + baseline: macOS **4/4 PASS**, Linux **4/4 PASS**.
- UI Design Guard: accepted, включая пять ширин Admin и письма;
  исправлен wordmark и закрыт риск failover→log.
- Browser preview письма: `.tmp/contact-mail-review/confirmation.html`,
  PNG на 320/640/768/1024/1280; синтетические контакты.

## Визуальные эталоны

UI Design Guard отдельно просмотрел actual/diff/старые эталоны и принял
намеренное изменение только `/settings` на Darwin и Linux. Обновлены
`frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-settings-chromium-darwin.png`
и `route-settings-chromium-linux.png`. Darwin дополнительно заменяет устаревший
эталон до уже действующего email/phone UI.
Windows runner недоступен в текущем окружении: Windows эталон не подменяется
изображением с другой ОС; для него потребуется intentional update/review
на настоящем Windows runner. Полный межплатформенный E2E не запускался.

## Изменённые файлы

- `backend/app/Mail/ContactRequestMail.php`, `ContactConfirmationMail.php` —
  уведомление сотруднику и фирменное подтверждение клиенту.
- `backend/app/Mail/CallbackRequestMail.php`,
  `backend/app/Support/RussianPhoneNumber.php` — общий вывод телефона.
- `backend/app/Models/SiteSetting.php` — типизация существующего cast phones.
- `backend/app/Services/CallbackMailService.php`, `PartnerRequestService.php`,
  `EmailRequestService.php` — атомарный outbox и независимые доставки.
- `backend/tests/Feature/Api/ContactMailNotificationTest.php`,
  `backend/tests/Integration/RedisQueueDeliveryTest.php` — регрессии доставки.
- `frontend/admin/src/features/settings/components/SettingsWorkspace.vue`,
  `frontend/admin/e2e/settings.spec.ts` — перестановка и browser проверки.
- `docs/API.md`, `docs/DATABASE.md`, `docs/QUEUE.md`, `docs/openapi.json`,
  этот отчёт и `tasks/DONE.md` — текущий контракт и результаты.

Commit не создавался. Зависимости и новые migrations не добавлялись.
