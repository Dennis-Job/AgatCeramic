# Письма заказа — 2026-11-05

Новый checkout сохраняет две независимые доставки: магазину на текущий
`notification_email` и клиенту на указанный обязательный email. Старые заказы
без email остаются совместимыми: для них возможна только доставка магазину.
Адреса берутся из существующих
настроек, транспорт — из MAIL_*. Исторические заказы не рассылаются.

Клиентское письмо содержит уважительное обращение по имени, благодарность,
номер заказа, неизменяемый состав/цены/итог и все пять введённых полей, чтобы
покупатель мог заметить ошибку. Добавлены просьба проверить данные,
подпись команды и публичные продавец/телефон/email/адрес при наличии.
Reply-To — публичная почта магазина. Внутренние адреса и данные не выводятся.
Вёрстка повторяет утверждённый стиль писем сайта; HTML экранируется,
денежные строки не переводятся во float.

## Доставка

`order_mail_deliveries` создаётся в транзакции заказа; уникальность
`(order_id, recipient)` защищает от повторного планирования. SendOrderMail
передаёт только delivery ID. After-commit dispatch безопасен при сбое Redis.
Успешное письмо не повторяется при отказе другого; пропускаются обезличенные
заказы. Общая MailTransportSafety блокирует log и цепочки с log для заказов
и обращений. Ошибки транспорта очищены от PII и credentials.

`orders:retry-mail` каждые пять минут выбирает до 100 pending-доставок:
сначала не отправлявшиеся в очередь, затем наиболее давно отправлявшиеся.
Так незаполненный адрес магазина не блокирует движение большого backlog.
Лимит — 9 SMTP-попыток; `--retry-exhausted` сбрасывает только исчерпанные
pending-записи. Старый SendOrderConfirmation остаётся адаптером уже
поставленных customer jobs. Exactly-once при аварии между принятием SMTP
и локальным commit не гарантируется.

## Локальное окружение

Применена только новая additive migration
`2026_11_05_000000_create_order_mail_deliveries_table`; queue/scheduler
перезапущены. Проверено без раскрытия адресов: SMTP, notification_email,
публичные email и телефон настроены; публичный адрес не заполнен и потому
не выводится. Тестовые заказы в рабочей БД и реальные письма не создавались.
Обратимость миграции проверена на отдельной PostgreSQL тестового профиля.

## Проверки

- Полный backend suite: 735 passed, 8034 assertions, 7 PostgreSQL-only skips
  в SQLite-прогоне. Профильные PostgreSQL: 28 tests / 189 assertions,
  включая recovery backlog и rollback/up новой миграции. Реальный Redis worker:
  1 test / 159 assertions, две доставки after-commit и повтор без нового письма.
- Rendering tests: все поля, пустые значения, escaping, private contacts,
  exact money, переносы групп тысяч; browser-preview двух писем на пяти ширинах.
- Pint, PHPStan, architecture guard, Composer validate и OpenAPI compatibility.
- Независимый code review и UI Design Guard; блокирующие замечания исправлены.
- Реальные Gmail/Outlook и фактическое получение нового письма не проверялись.
- Первоначальный `composer audit --locked` выявил 6 advisories в Symfony.
  По следующему поручению владельца все шесть исправлены минимальным
  обновлением трёх пакетов до 7.4.23; повторный аудит пуст.
  Детали: [SYMFONY_SECURITY_2026-11-05.md](SYMFONY_SECURITY_2026-11-05.md).

Synthetic HTML/PNG для просмотра находятся в
`backend/storage/app/order-mail-review/` (gitignored).

## Изменённые файлы

- `backend/app/Services/OrderConfirmationService.php`,
  `backend/app/Jobs/SendOrderMail.php`, `backend/app/Jobs/SendOrderConfirmation.php`
  — планирование, независимая доставка и совместимость старых jobs.
- `backend/app/Models/OrderMailDelivery.php`,
  `backend/database/migrations/2026_11_05_000000_create_order_mail_deliveries_table.php`,
  `backend/app/Console/Commands/RetryOrderMail.php`, `backend/routes/console.php`
  — durable intent, ограничения и автоматическое восстановление.
- `backend/app/Mail/OrderConfirmationMail.php`,
  `backend/app/Mail/OrderNotificationMail.php`, `backend/app/Mail/OrderMailContent.php`,
  `backend/app/Models/OrderItem.php` — шаблоны и точные типы decimal snapshots.
- `backend/app/Support/MailTransportSafety.php`,
  `backend/app/Services/CallbackMailService.php` — общий прежний запрет log-транспорта.
- `backend/tests/Feature/Api/OrderMailContentTest.php`,
  `backend/tests/Feature/Api/OrderMailDeliveryTest.php`,
  `backend/tests/Feature/Api/OrderConfirmationTest.php`,
  `backend/tests/Integration/RedisQueueDeliveryTest.php` — проверки поведения.
- `docs/API.md`, `docs/openapi.json`, `docs/DATABASE.md`, `docs/QUEUE.md`,
  `docs/CLIENT_UI_KIT.md`, этот отчёт,
  `docs/superpowers/plans/2026-11-05-order-emails.md`, `tasks/DONE.md` — контракт,
  эксплуатация, UI-правило и результат задачи.
