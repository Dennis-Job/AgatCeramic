# Исправления безопасности Symfony — 2026-11-05

Поле email клиента не относится к шести предупреждениям Composer. Все шесть
предупреждений были опубликованными advisory для установленных компонентов
Symfony. Они описывают уязвимые сценарии библиотеки; наличие advisory в audit
само по себе не доказывает эксплуатацию или применимость каждого сценария
к текущей конфигурации Laravel API.

## Шесть предупреждений и исправление

| № | Компонент и advisory | Суть | Исправление |
| --- | --- | --- | --- |
| 1 | http-foundation — [CVE-2026-118514](https://symfony.com/cve-2026-118514), PKSA-24ph-xkrh-y79j | Запрос HEAD мог обходить ограниченные HTTP-методом правила доступа Symfony, применявшиеся к GET. | v7.4.23 |
| 2 | http-foundation — [GHSA-3h27-hjv7-fqjx](https://github.com/symfony/symfony/security/advisories/GHSA-3h27-hjv7-fqjx), PKSA-pr6q-2w5s-kzzf | Обработка имени PHP-скрипта могла давать другой путь, чем видели правила веб-сервера, CDN или WAF перед PHP. | v7.4.23 |
| 3 | http-foundation — [GHSA-xg2q-4rrq-657q](https://github.com/symfony/symfony/security/advisories/GHSA-xg2q-4rrq-657q), PKSA-nxjs-s6hn-vb6m | Сокращение private_ranges для доверенных прокси включало специальные IPv6-диапазоны, из-за чего внешний клиент мог считаться доверенным прокси. | v7.4.23 |
| 4 | http-foundation — [GHSA-5mx8-mwrp-5693](https://github.com/symfony/symfony/security/advisories/GHSA-5mx8-mwrp-5693), PKSA-b2gg-vk1g-6d5q | При определённых настройках front controller сформированные ссылки могли вести на хост, выбранный клиентом через путь запроса. | v7.4.23 |
| 5 | http-kernel — [GHSA-wg5q-m2ww-f3c9](https://github.com/symfony/symfony/security/advisories/GHSA-wg5q-m2ww-f3c9), PKSA-3gxv-7xhq-mgmz | После inline-фрагмента подзапроса недоверенные forwarded-заголовки могли менять хост, протокол и порт абсолютных URL основного запроса. | v7.4.23 |
| 6 | routing — [CVE-2026-118516](https://symfony.com/cve-2026-118516), PKSA-y8c5-1b7f-r1gj | В скомпилированных маршрутах параметр хоста мог захватывать начало пути и обходить правила, основанные на реальном пути. | v7.4.23 |

У каждой официальной страницы выше версия 7.4.23 указана как исправленная.
Для routing официальное описание также требует очистить compiled route cache
после обновления; локально выполнен `php artisan route:clear`.

## Минимальное обновление зависимостей

| Пакет | Было | Стало |
| --- | --- | --- |
| symfony/http-foundation | v7.4.21 | v7.4.23 |
| symfony/http-kernel | v7.4.18 | v7.4.23 |
| symfony/routing | v7.4.18 | v7.4.23 |

Изменён только `backend/composer.lock`: три package-записи, без добавления или
удаления пакетов. Ограничения `composer.json`, остальные версии, настройки
SMTP, данные БД и реализация писем сохранены. Широкое обновление
зависимостей не выполнялось. Команды запускались в backend Docker-контейнере
с PHP 8.3.35.

Сначала проверен dry-run, затем применена та же операция:

```sh
composer update symfony/http-foundation symfony/http-kernel symfony/routing \
  --with-all-dependencies --minimal-changes --dry-run --no-interaction
composer update symfony/http-foundation symfony/http-kernel symfony/routing \
  --with-all-dependencies --minimal-changes --no-interaction
composer audit --locked --format=json
composer validate --strict
php artisan route:clear
```

## Результат проверки

- До обновления: 6 advisory в 3 пакетах, abandoned-пакетов нет.
- После обновления: `composer audit --locked --format=json` вернул
  `{"advisories":[],"abandoned":[]}` и exit code 0.
- `composer validate --strict`: valid, exit code 0.
- `git diff --check` для composer-файлов: без ошибок.
- Установленная в общем vendor-томе версия всех трёх компонентов — v7.4.23.
- Коммиты, изменения рабочей БД и реальные почтовые отправки не выполнялись.

Изменение обязательности email и регрессионные проверки checkout выполняются
отдельно в рамках той же пользовательской задачи; они не являются частью
этих шести исправлений зависимостей.

## Обязательный email и итоговые проверки

В новом checkout поле Email обязательно в прежнем UiTextField; type=email,
required, autocomplete и maxlength=255 сохранены. API требует непустую строку
email:rfc и отклоняет missing/null/empty/whitespace/invalid до изменения
корзины, записи заказа и почтовых доставок. Историческая nullable БД не меняется.
Ограничение задокументировано в OpenAPI v16.0 с major migration plan.

- Backend: 736 passed, 8064 assertions, 7 PostgreSQL-only skips в SQLite.
- Отдельный PostgreSQL: 21 tests / 162 assertions, включая проверки numeric,
  валидации email, идемпотентности и почтовых доставок.
- Client: typecheck, build, format; 18 браузерных тестов корзины/оформления/
  документов PASS. Новый email-сценарий проверен на пяти ширинах.
- UI Design Guard принял изменение без замечаний.
- PHPStan, architecture guard, Pint, Composer validate PASS;
  composer audit --locked: 0 advisories, 0 abandoned.
- OpenAPI compatibility guard подтверждает согласованный breaking major v16.0;
  новых миграций нет (`Nothing to migrate`).

Дополнительные изменённые файлы текущего поручения: StoreOrderRequest,
CheckoutPage.vue, checkout/services/orders.ts, OrderCreationTest,
OrderConfirmationTest, checkout.spec.ts; docs/API.md, CLIENT_UI_KIT.md,
REQUIREMENTS.md, QUEUE.md, ORDER_EMAILS_2026-11-05.md, openapi.json,
OPENAPI_MIGRATION_PLAN.md, план писем и tasks/DONE.md.
