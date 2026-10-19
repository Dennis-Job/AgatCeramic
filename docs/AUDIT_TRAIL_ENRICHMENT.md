# Содержательный журнал аудита — этап 1

Согласовано владельцем 2026-10-16: реализовать первый этап предложения — полноту
и содержание событий. Редизайн страницы является отдельным вторым этапом.

## Контракт и границы

- Сохраняются существующие action-коды, permissions, immutable audit_logs и retention.
- Снимок объекта хранит понятное имя, SKU товара / номер заказа и безопасные идентификаторы.
- `metadata.changes` содержит только фактически изменённые разрешённые бизнес-поля:
  `{field: {before: value, after: value}}`. `title` обозначает название каталожного объекта.
- Для создания/удаления `metadata.values` содержит разрешённое состояние объекта.
- Имена и email сотрудников сохраняются только в уже существующем identity snapshot;
  изменения персональных полей описываются именами полей, без прежних/новых значений.
- Пароли, токены, клиентские контакты, содержимое комментариев и сырой HTTP payload
  не записываются. Общая очистка metadata сохраняется.
- Запись изменений происходит в транзакции владельца операции. В перечисленных
  ниже обогащённых сценариях повторное сохранение неизменённых данных не создаёт
  ложного события изменения.
- Структура API сохранена: существующие `details: [{label, value}]` содержат
  описание действия, SKU, номер заказа, человекочитаемые сведения и сравнения.
  Проверка контракта запрещает новые response properties при прежнем
  `additionalProperties: false`; отдельные summary/sku/order_number не добавляются.
- Старые записи не переписываются; выводятся только реально сохранённые подробности.

## План реализации

1. Общие снимки/сравнение состояния и представление событий в API; regression tests
   на сохранение идентичности после удаления, статусы заказов и маскирование.
2. Товары, фото, состав объединений, характеристики и связанные товары: реальные
   изменения, SKU, автоматические последствия и rollback/no-op проверки.
3. Сотрудники, роли, категории, бренды и определения характеристик: состояния и diff.
4. Импорты и экспорт: инициатор, безопасный контекст, результат; связь изменений
   с импортом, отсутствие дублирования terminal-событий при retry.
5. OpenAPI/документация, PHPStan/Pint/architecture, backend suite и PostgreSQL;
   независимое review и проверка итогового diff. Commit не создаётся.

## Проверяемые риски

- Журнал не должен содержать изменения откатившейся транзакции.
- Снимки не зависят от последующих переименований/удалений справочников.
- Переключение главного фото и синхронизация характеристик отражают последствия.
- Массовая смена состава группы сравнивает состав до detach со всем итоговым составом.
- Очередь сохраняет инициатора; retry не выдаётся за новую пользовательскую операцию.

## Результат и проверки

### Реализованное покрытие

- Товары: создание/удаление и изменения разрешённых полей, характеристик и связей;
  SKU и название сохраняются на момент события.
- Фото: загрузка, изменение, удаление, автоматическая смена главного изображения;
  ZIP-импорт сохраняет идентификаторы, генерируемые имена файлов и состояние галереи.
- Объединения: состав по SKU и оси до/после, в том числе массовая замена состава;
  удаление товара отражает уменьшение группы либо её расформирование.
- Общие характеристики объединения: отдельное событие на каждый реально изменённый
  товар с контекстом исходного товара и импортом, если он был причиной операции.
- Категории, бренды, определения характеристик и их группы: безопасные поля,
  документы/назначения/варианты значений до/после.
- Сотрудники, роли и собственный профиль: реальные изменения статуса/назначений;
  персональные поля и пароль отмечаются только именами полей. Сохранённые названия
  ролей/прав не меняются при последующем редактировании справочника.
- Заказы: вывод уже сохранённых переходов статусов; новые события сохраняют
  человекочитаемые названия статусов. Статусы оплаты выводятся без потери enum-значений.
- Импорты: отправка, завершение/ошибка и безопасные итоги, исходный сотрудник,
  связь с изменениями по `import_id`, дедупликация terminal-событий под блокировкой.
  Одна строка XLSX даёт итоговое изменение вместо временных переключений активности.
- Экспорт товаров: инициатор и фактическое количество записей после создания файла;
  ошибка не создаёт событие успешного экспорта, временный файл удаляется.

Остальные существующие события входа/выхода, контента, настроек, магазинов,
обращений и согласований сохранены. API получает русские описания action-кодов;
полное сравнение содержимого контентных блоков и других не перечисленных здесь
объектов не добавлялось. Текущая страница использует расширенный `details`;
следующий этап изменит представление этих сведений в интерфейсе.

### Файлы

- Общий механизм: `backend/app/Services/AuditLogService.php`, новый
  `AuditSnapshotService.php`, `backend/app/Support/AuditEventPresenter.php`,
  `AuditLabels.php`, `backend/app/Http/Resources/AuditLogResource.php`.
- Каталог: `ProductManagementService`, `ProductAttributeValueManagementService`,
  `ProductRelationManagementService`, `ProductImageManagementService`,
  `ProductGroupManagementService` в `backend/app/Services/`.
- Команда/справочники: `AdminAuthenticationService`, `AdminUserManagementService`,
  `RoleManagementService`, `BrandManagementService`, `CategoryManagementService`,
  `CategoryAttributeManagementService`, `AttributeGroupManagementService`,
  `AttributeManagementService`; `OrderStatusManagementService` — названия статусов.
- Импорт/экспорт: `ImportSubmissionService`, `ImportLifecycleService`,
  `ProductImportExecutor`, `ProductImportService`, `GenericProductImportService`,
  `CategoryProductImportService`, `CategoryProductRowImporter`,
  `ProductPriceStatusImportService`, `ProductGroupImportService`,
  `ProductImageImportService`, `ProductExportService`,
  `backend/app/Http/Controllers/Api/V1/Admin/ProductExportController.php`.
- Тесты: пять новых файлов в `backend/tests/Feature/Audit/`;
  `backend/tests/Feature/Api/AttributeManagementTest.php` (no-op),
  `ProductImportTest.php` (явный инициатор экспорта).
- Контракт/документация: `docs/openapi.json`, `docs/DATABASE.md`, этот файл,
  `docs/CURRENT_STATE.md`, `tasks/DONE.md`.

### Проверки

- Native Windows / PHP 8.3.1: полный `php vendor/bin/phpunit --colors=never` —
  488 тестов, 5817 assertions, 1 PostgreSQL-only skip; failures/errors отсутствуют.
- PostgreSQL 17.9 / PHP 8.3.35: полный `phpunit.postgres-feature.xml` —
  407/407 тестов, 5501 assertions, без пропусков.
- PostgreSQL integration: `PostgresAuditLogImmutabilityTest` и
  `PostgresCatalogConcurrencyTest` — 14/14, 63 assertions. Полная схема развёрнута
  с нуля только в allowlisted временной `agatceramic_test`.
- Audit presentation/API/OpenAPI subset — 55/55 тестов, 2820 assertions.
- Полный PHPStan level 8, Pint, architecture blocking guard и `git diff --check` — PASS.
  Architecture report сохраняет информационные сигналы сложности, включая
  разрешённую сериализацию и существующие крупные application workflows;
  несвязанный рефакторинг не выполнялся.
- `composer validate --strict` — PASS. `composer audit --locked` — не прошёл:
  Packagist curl 28, использован локальный кэш, сообщён один advisory для
  `symfony/http-foundation`, CVE-2026-108082. Зависимости и lock-файлы в этой задаче
  не менялись; результат не объявляется чистым security gate.
- `assert-openapi-compatible.php` против `HEAD:docs/openapi.json` — PASS.
  Новые top-level/entity поля были исключены после обнаружения ограничения
  `additionalProperties: false`; описания передаются в прежнем `details`.
- Независимый backend review выявил DB-default снимки, live dereference названий
  ролей/прав, внутренние переключения активности импорта, недостаточный контекст
  замены фото, no-op профиля и потерю инициатора после удаления сотрудника.
  Замечания исправлены с RED→GREEN regression tests. Дополнительно проверены
  числовые SKU, enum статусы оплаты и исторические названия статусов заказа.

Первый PostgreSQL-прогон выполнялся одновременно с Windows-тестами на общем
`storage` и дал один сбой `CategoryImageCleanupTest::test_an_image_still_used_elsewhere_is_preserved`
(`category-document`, отсутствовал `media/shared.png`). Финальный полный прогон
на отдельном tmpfs-storage прошёл 407/407. Рабочая БД не использовалась; временный
PostgreSQL-контейнер этой задачи удалён после проверки. Локальным queue workers
отправлен штатный `queue:restart`, чтобы последующие импорты загрузили новый код.

Миграций, новых permissions и изменений frontend-кода нет. UI Design Guard не
запускался: второй этап изменения интерфейса не выполнялся. Commit не создавался.
