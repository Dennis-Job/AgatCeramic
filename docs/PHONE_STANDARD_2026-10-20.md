# Единый формат телефона и верхняя строка сайта

Дата: 2026-10-20. Изменения согласованы владельцем в чате.

## Контракт

- Client и Admin показывают номер как `+7 (495) 120-45-67`.
- Телефонные поля автоматически ставят неудаляемый `+7`; вводятся десять
  национальных цифр. Поддерживаются полная вставка, выделение, замена,
  Backspace/Delete и редактирование внутри номера.
- Пустое optional-поле передаёт пустое/null значение, обязательное и неполное
  поле блокирует отправку. Backend проверяет ввод независимо от маски.
- БД/API используют `+74951204567`. `tel:` получает это значение без маски.
- В настройках сайта один номер: совместимый `phones` содержит 0–1 элементов.
  Исторические несколько номеров требуют явного выбора, а не скрытой потери.
- Общие primitive каждого отдельно собираемого приложения владеют маской;
  доменные формы не копируют обработчики. Небольшие pure utilities в Client
  и Admin имеют один контракт, как существующие formatter денег, без связи
  независимых Docker/build-контекстов. В backend нормализатор общий для всех
  шести Form Requests; SQL и бизнес-логика не перемещаются в UI/контроллеры.

## Шапка

Управляемый текст слева, справа — «Заказать звонок», телефон, email. Используются
40 px высоты, 11 px, tracking .08em, muted-цвет, тонкий разделитель и gap 28 px
из `exampleSite.html`. До 760 px левый текст скрывается как в референсе,
контакты переносятся без overflow. Палитра и шрифты не изменяются.
Последующая правка владельца добавила `·` с отступами 12 px вместо gap 28 px,
убрала подчёркивание и начальное выделение controls; актуальное поведение —
[в отчёте UI-правки](CALLBACK_UI_REFINEMENTS_2026-10-20.md).
Редактор подписан «Верхняя строка слева»; исторический API-ключ
`header.topbar_right` сохранён, существующий текст не перезаписывается.

## Существующие данные

`php artisan phones:normalize` — dry-run; `--apply` изменяет только однозначно
распознанные записи. Вывод содержит только счётчики, таблицы и технические IDs,
без ПДн. Несколько телефонов, невалидные значения и обезличенные null
не обрезаются/не подменяются. Схема БД не меняется.

Проверка текущей локальной БД: `checked=1 eligible=0 changed=0 unchanged=1
empty=0 invalid=0 multiple=0`. Существующий номер уже канонический; запуск
`--apply` не понадобился. Новые записи нормализуются в Form Requests.

## Проверка

- Client: typecheck, build, format — PASS; полный production E2E **29/29 PASS**,
  development preview/iframe **2/2 PASS**. Проверены ввод, удаление и вставка,
  канонический payload, `tel:`, SSR/hydration и пять контрольных ширин.
- Admin: build, lint, format — PASS, полный unit **188/188 PASS**. Полный Windows
  E2E: **631 PASS / 4 FAIL из 635**. Сетевой `ERR_NO_BUFFER_SPACE` в
  `productImport.spec.ts` исчез при отдельном повторе; оставшиеся три
  `adminBaseline` для `/products`, `/roles`, `/permissions` воспроизведены на
  отдельной копии неизменённого HEAD с теми же отличиями моноширинного текста.
  Их golden-файлы сохранены. Новые телефонные проверки, настройки, магазины,
  заказы, обращения и responsive/axe прошли. Отдельный повтор legacy-настроек
  и импорта: **2/2 PASS**.
  После финального усиления legacy-guard заново собран Admin и пройдены
  настройки, магазины и заказы: **11/11 E2E PASS**, включая явное удаление номера.
- Обновлены только проверенные Windows/Linux snapshots `/settings`, `/orders`,
  `/contacts`: одиночный input и формат номера — намеренные изменения владельца.
  Linux build и три адресные visual/axe проверки прошли; macOS golden-файлы
  не пересоздавались без macOS runtime и требуют проверки соответствующим runner.
- Backend: полный SQLite **515 PASS / 1 PostgreSQL-only SKIP**, 6002 assertions.
  Полный PostgreSQL feature: **420 PASS / 1 FAIL**. Падает несвязанный
  `StandaloneProductManagementTest::test_product_groups_validate_axes_members_and_return_axis_values`
  при random seed 48048: `data.products.0.primary_image.id` равен null вместо
  ожидаемого ID. По отдельности он проходит (**1/1 PASS**); товарные группы
  этой задачей не менялись. Все затронутые телефонные сценарии на PostgreSQL
  прошли отдельным прогоном: **28/28 PASS**, 275 assertions.
- PostgreSQL запускался только в отдельном ephemeral-контейнере на loopback
  55433, после проверки штатного allowlist guard. Рабочая БД не очищалась.
  Тестовый контейнер остановлен после проверки.
- Pint (18 PHP-файлов), PHPStan, architecture guard, Composer manifest — PASS.
  OpenAPI tooling **5/5 PASS**, semantic lint и compatibility gate — PASS;
  контракт поднят до `v6.0`, wire routes остаются `/api/v1`, добавлен migration plan.
- `composer audit --locked` не завершился: Packagist security endpoint вернул
  сетевой timeout (curl 28). Composer/npm зависимости не менялись.
- Независимый UI Design Guard проверил код, повторное использование primitive,
  референс и screenshots. Найденный P2 (пустое prefix-удаление снимало guard
  старого списка телефонов) устранён: no-op больше не испускает изменение.
  Набранный и затем стёртый неполный новый номер также не считается явным
  удалением старых номеров: guard сохраняется до полного номера или отдельного
  действия «Убрать телефон». Проверка исправления — unit и browser regression
  с сохранением legacy-guard и явным удалением.
  Финальные screenshots и результаты проверены исполнителем.
- Live `localhost:3000`: после завершения hydration проверены реальные контакты,
  автоматический `+7`, ввод десяти цифр в маску, modal open/close и 320 px без
  overflow. Тестовая заявка не отправлялась; Client/Admin dev-серверы обновлены.

Это результаты адресной реализации; полный проектный прогон не объявляется
полностью зелёным из-за перечисленных несвязанных проверок. Палитра сохранена,
данные не удалялись, commit не создавался.

## Изменённые файлы

Текущий diff включает предыдущий этап формы обратного звонка; commit не создавался.

```text
AGENTS.md
backend/app/Console/Commands/NormalizePhoneNumbersCommand.php
backend/app/Http/Requests/Api/V1/Admin/StoreStoreRequest.php
backend/app/Http/Requests/Api/V1/Admin/UpdateSiteSettingRequest.php
backend/app/Http/Requests/Api/V1/Admin/UpdateStoreRequest.php
backend/app/Http/Requests/Api/V1/StoreCallbackRequest.php
backend/app/Http/Requests/Api/V1/StoreOrderRequest.php
backend/app/Http/Requests/Api/V1/StorePartnerRequest.php
backend/app/Queries/ContactRequestQuery.php
backend/app/Queries/OrderQuery.php
backend/app/Services/PhoneNumberNormalizationService.php
backend/app/Support/RussianPhoneNumber.php
backend/database/factories/ContactRequestFactory.php
backend/tests/Feature/Api/CallbackRequestTest.php
backend/tests/Feature/Api/OrderCreationTest.php
backend/tests/Feature/Api/PartnerRequestTest.php
backend/tests/Feature/Api/PhoneNumberContractTest.php
backend/tests/Feature/PhoneNumberNormalizationTest.php
backend/tests/Unit/RussianPhoneNumberTest.php
docs/API.md
docs/CALLBACK_TOPBAR_2026-10-20.md
docs/CLIENT_UI_KIT.md
docs/CONTENT_WORKSPACE_UX.md
docs/DATABASE.md
docs/OPENAPI_MIGRATION_PLAN.md
docs/PHONE_NUMBER_NORMALIZATION.md
docs/PHONE_STANDARD_2026-10-20.md
docs/openapi.json
frontend/admin/AGENTS.md
frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-contacts-chromium-linux.png
frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-contacts-chromium-win32.png
frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-orders-chromium-linux.png
frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-orders-chromium-win32.png
frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-settings-chromium-linux.png
frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-settings-chromium-win32.png
frontend/admin/e2e/appearance.spec.ts
frontend/admin/e2e/orders.spec.ts
frontend/admin/e2e/settings.spec.ts
frontend/admin/e2e/stores.spec.ts
frontend/admin/src/components/shared/UI_KIT.md
frontend/admin/src/components/ui/UiInput.vue
frontend/admin/src/features/appearance/components/SiteChromeEditor.vue
frontend/admin/src/features/contacts/components/ContactDetails.vue
frontend/admin/src/features/contacts/components/ContactsList.vue
frontend/admin/src/features/orders/components/OrderDetails.vue
frontend/admin/src/features/orders/components/OrdersList.vue
frontend/admin/src/features/orders/types/order.types.ts
frontend/admin/src/features/settings/components/SettingsWorkspace.vue
frontend/admin/src/features/settings/composables/useSettingsWorkspace.ts
frontend/admin/src/features/stores/components/StoresWorkspace.vue
frontend/admin/src/utils/phone.ts
frontend/admin/tests/phone.test.ts
frontend/admin/tests/phoneInput.test.ts
frontend/admin/tests/settingsPhone.test.ts
frontend/client/AGENTS.md
frontend/client/app/components/shared/SiteHeader.vue
frontend/client/app/components/ui/UiDialog.vue
frontend/client/app/components/ui/UiTextField.vue
frontend/client/app/features/callback/components/CallbackRequestDialog.vue
frontend/client/app/features/callback/components/StorefrontHeader.vue
frontend/client/app/features/callback/composables/useCallbackRequest.ts
frontend/client/app/features/callback/services/callbackRequests.ts
frontend/client/app/features/content/components/StoreContacts.vue
frontend/client/app/layouts/default.vue
frontend/client/app/pages/preview/[slug].vue
frontend/client/app/utils/phone.ts
frontend/client/e2e/callback.spec.ts
frontend/client/e2e/mock-api.mjs
frontend/client/e2e/phone-mask.spec.ts
tasks/DONE.md
```
