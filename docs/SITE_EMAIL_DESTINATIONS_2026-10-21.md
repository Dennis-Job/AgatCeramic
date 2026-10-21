# Почта сайта и форма сотрудничества — 2026-10-21

## Адреса

В «Настройках сайта» разделены три назначения:

- `email` — публичная почта для клиентов, используемая в шапке, контактах и
  остальных существующих местах вывода общего адреса.
- `partner_email` — публичный контакт для сотрудничества на `/cooperation`.
- `notification_email` — внутренний получатель уведомлений о новых обратных
  звонках; доступен только через защищённые настройки `settings.manage`.

Старый API-ключ `email` сохранён. Добавлены два nullable поля. Все адреса
валидируются, могут быть очищены независимо, PATCH не меняет непереданные поля.
Публичный API возвращает клиентский и партнёрский адреса; внутреннего адреса в
нём нет. Изменения пишутся в audit trail как имена полей без значений.

Миграция `2026_10_21_000002` сохраняет текущую клиентскую почту и копирует её в
начальное поле уведомлений. Она уже применена в локальной БД. Перенос проверен
без вывода адресов: клиентская почта заполнена, адрес уведомлений совпадает с ней,
партнёрская почта пока не задана. Адреса из `.env` в настройки не копировались.

## Уведомления

Callback job читает актуальный `notification_email` при каждой попытке доставки,
поэтому настройка действует и на ожидающие задачи. `ADMIN_EMAIL`, клиентский и
партнёрский адреса не используются как fallback. При пустом внутреннем адресе
заявка остаётся сохранённой, delivery — pending, SMTP-попытки не расходуются.
Worker перезапущен после обновления кода.

Настройки SMTP (`MAIL_*`) продолжают определять транспорт и отправителя.
Изменение трёх контактных адресов не создаёт новых SMTP/IMAP-подключений и не меняет
учётные записи сотрудников. Клиенту по обратному звонку письмо не отправляется.

## Сотрудничество

По уточнению владельца источником партнёрских обращений является форма сайта.
Она отправляет обязательные имя, телефон, email и предложение в существующий
`POST /partner-requests`. Backend сам задаёт `type=partner`, `source=website`;
результат виден на странице «Обращения» под типом «Партнёр». Телефон использует
общую маску, сохраняется канонически. Произвольные `type`/`source` не принимаются.

Форма использует адрес `/cooperation`, общий renderer CMS и тот же компонент в
preview. Партнёрская почта выводится рядом с формой, если заполнена. Пустой адрес
не блокирует отправку формы. Общая почта шапки остаётся клиентской.
Публичный partner endpoint получил узкое CSRF-исключение аналогично callback;
admin CSRF, rate limits, honeypot и validation сохраняются. Ошибки не стирают ввод,
повторный submit во время отправки блокируется, автоматического retry нет.

Страница создана и опубликована в локальной CMS (`page_id=5`) через
PageManagementService с проверкой PagePolicy и аудитом create/publish. Другие
страницы/черновики не изменялись. Индексация остаётся выключенной по умолчанию
workflow CMS; SEO и настройка индексации доступны в редакторе страницы.

Синхронизации реального входящего ящика нет. Отдельная отправка SMTP-письма по
партнёрской форме в этой задаче не добавлялась: она создаёт запись обращения.

## Проверки

- Backend: **529 PASS, 1 SKIP, 6090 assertions**; пропуск — прежняя Unicode LOWER
  проверка для PostgreSQL, не поддерживаемая SQLite.
- PostgreSQL: **20 PASS, 135 assertions** для настроек и callback, включая
  миграцию down/up и сохранность исходной почты.
- Реальный Redis worker: **1 PASS, 111 assertions**, mailer `array`, отдельная
  PostgreSQL БД и Redis DB14. Внешние письма не отправлялись.
- Новый regression partner form: Origin сайта без CSRF → 201 → защищённый admin
  список `type=partner` содержит имя/email/предложение. Partner+callback: **14 PASS**.
- PHPStan, полный Pint (625 files), `composer validate --strict`, architecture guard,
  OpenAPI backward compatibility: PASS.
- Повторный `composer audit --locked` не завершился из-за timeout Packagist;
  зависимости задачи не менялись.
- Admin: три поля приняты UI Design Guard на пяти ширинах. Одобрены намеренные
  изменения только Windows/Linux эталона `/settings`; остальные snapshots не менялись.
  Windows settings+baseline **4/4 PASS**, Linux settings+baseline **4/4 PASS**.
  Полный `npm run check`: lint/format, **201 unit tests**, production build PASS.
  Darwin не запускался.
- Client: production build, typecheck, format PASS; полный E2E **45/45 PASS**,
  включая пять новых сценариев сотрудничества и регрессию keyboard submit на 720 px.
- UI Design Guard: ACCEPTED, открытых замечаний нет. Проверены пять ширин и
  короткое окно 720 px, keyboard submit, visible success после сворачивания формы,
  SSR/preview, loading/validation/429/network, единые поля и маска телефона.
  Артефакты: `.tmp/mail-ui-review/`.
- Live: `/cooperation` HTTP 200 с SSR-формой и без placeholder пустой страницы;
  публичный API содержит `partner_email`, не содержит `notification_email`.
  Пустой partner POST с Origin сайта возвращает validation 422 вместо CSRF 419.

## Изменённые файлы

Пути первых трёх групп относительны `backend/`, `frontend/admin/`, `frontend/client/`.

- Backend: `app/Models/SiteSetting.php`, `app/Http/Requests/Api/V1/Admin/UpdateSiteSettingRequest.php`,
  `app/Http/Resources/SiteSettingResource.php`, `AdminSiteSettingResource.php`,
  `app/Services/CallbackMailService.php`, `bootstrap/app.php`, `config/mail.php`, `.env.example`,
  `database/migrations/2026_10_21_000002_add_email_destinations_to_site_settings.php`,
  `tests/Feature/Api/SiteSettingManagementTest.php`, `CallbackNotificationTest.php`,
  `PartnerRequestTest.php`, `tests/Integration/RedisQueueDeliveryTest.php`.
- Admin: `src/features/settings/types/settings.types.ts`,
  `composables/useSettingsWorkspace.ts`, `components/SettingsWorkspace.vue`,
  `tests/settingsEmail.test.ts`, `e2e/settings.spec.ts`,
  `e2e/adminBaseline.spec.ts-snapshots/route-settings-chromium-{win32,linux}.png`.
- Client: `app/types/contentPage.ts`, `app/components/ui/UiTextField.vue`, `UiTextarea.vue`,
  `app/features/content/components/ContentPageBody.vue`,
  `app/features/partnership/components/PartnershipSection.vue`, `PartnerContact.vue`,
  `composables/usePartnerRequest.ts`, `services/partnerRequests.ts`,
  `e2e/mock-api.mjs`, `e2e/cooperation.spec.ts`.
- Документация: `docs/API.md`, `openapi.json`, `DATABASE.md`, `QUEUE.md`, `CLIENT_UI_KIT.md`,
  `CALLBACK_NOTIFICATIONS_2026-10-21.md`, этот отчёт, `tasks/DONE.md`.

Прежние результаты callback-задачи описаны отдельно в
[CALLBACK_NOTIFICATIONS_2026-10-21.md](CALLBACK_NOTIFICATIONS_2026-10-21.md).
Секреты `.env` и учётные записи не менялись. Commit в рамках задачи не создавался.
