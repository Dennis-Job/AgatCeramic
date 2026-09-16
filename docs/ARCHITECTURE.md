# Архитектура AgatCeramic

## 1. Общая схема

```text
                    Internet
                       |
                 Nuxt Client
                       |
                       | REST API
                       v
                Laravel API
                       |
        +--------------+--------------+
        |              |              |
    PostgreSQL       Redis         Storage
        |              |
        |           Queues
        |
     Business Data

Admin Vue
    |
    | REST API
    v
Laravel API
```

## 2. Backend modules

Предварительные bounded modules:
- Auth;
- Admin Users;
- Catalog;
- Categories;
- Attributes;
- Brands;
- Media;
- Cart;
- Orders;
- Contacts;
- Content;
- SEO;
- Analytics;
- Settings;
- Audit;
- Notifications;
- Import/Export.

Не превращать каждый модуль автоматически в отдельный микросервис.

На старте использовать модульный монолит Laravel.

## 3. Backend layers

```text
HTTP
  ↓
Requests
  ↓
Controllers
  ↓
Application Services / Use Cases
  ↓
Domain logic
  ↓
Repositories / Query objects
  ↓
Eloquent / DB
```

Для простых CRUD операций допустимо не создавать искусственную сложность.

### Автоматические границы

`cd backend && composer architecture` разбирает `app/**/*.php` через PHP Parser и является
блокирующим шагом CI. Guard закрепляет следующие направления:

| Исходный слой | Разрешённое направление | Запрещённое направление |
| --- | --- | --- |
| HTTP Controllers/Requests/Middleware | Application services, Queries, Models, API Resources | Controllers: DB facade, прямые Eloquent mutations, `app()`/`resolve()`, глобальные `request()`/`auth()` |
| Application (`app/Services`) | Models, Queries и явные integration entry points | `App\Http\*`, HTTP Request/Response types |
| Data models (`app/Models`) | Enums, local model/support primitives | HTTP, Services, Queries, Jobs, Console, Mail |
| Read queries (`app/Queries`) | Models и query primitives | HTTP, Services, Jobs, Console, Mail |
| API presentation (`Http/Resources`, `Http/Responses`) | Models, enums и другие presentation components | Services, Queries, Jobs, Console, Mail |
| Integration entry points (`Jobs`, `Console`, `Mail`, `Logging`) | Application/Data | Controllers, Requests, Resources, Responses |

Guard отдельно печатает review-сигналы из [`backend/AGENTS.md`](../backend/AGENTS.md): класс
длиннее 250 строк и метод длиннее 40 строк. Дополнительный проектный сигнал — cyclomatic
complexity метода выше 10. Эти findings не блокируют CI автоматически: они требуют проверить
разделение ответственности и зафиксировать решение в review.

Исключение архитектурного нарушения допускается только временно в
[`backend/scripts/architecture-allowlist.php`](../backend/scripts/architecture-allowlist.php).
Запись должна точно указывать `path`, `rule`, `line`, причину и отдельную задачу удаления долга в
формате `TASK-A000` или `TASK-000`. Некорректная или устаревшая запись сама ломает gate; общий
baseline и wildcard-исключения не поддерживаются.

## 4. API

API versioning:
`/api/v1/...`

Примеры:
- `GET /api/v1/products`
- `POST /api/v1/products`
- `GET /api/v1/products/{product}`
- `PATCH /api/v1/products/{product}`
- `DELETE /api/v1/products/{product}`

Для списков:
- pagination;
- sorting;
- filtering;
- search.

JSON responses должны быть стабильными.

Использовать API Resources.

## 5. Frontend Admin

Предлагаемая структура:

```text
frontend/admin/
└── src/
    ├── components/
    ├── layouts/
    ├── views/
    │   ├── dashboard/
    │   ├── products/
    │   ├── categories/
    │   ├── attributes/
    │   ├── brands/
    │   ├── orders/
    │   ├── contacts/
    │   ├── content/
    │   ├── seo/
    │   ├── analytics/
    │   ├── settings/
    │   └── users/
    ├── stores/
    ├── services/
    ├── composables/
    ├── router/
    └── types/
```

UI — TailAdmin Vue style.

## 6. Frontend Client

Nuxt:
- pages;
- layouts;
- components;
- composables;
- stores;
- API services;
- SEO composables;
- schema.org structured data;
- image optimization.

Публичные страницы должны быть индексируемыми там, где это необходимо.

## 7. Cart

Так как регистрации нет:
- корзина должна работать без аккаунта;
- cart identifier хранится у клиента;
- сервер должен валидировать цены и доступность товара при оформлении;
- нельзя доверять итоговой сумме из браузера.

## 8. Order creation

Поток:

```text
Client
  ↓
POST /orders
  ↓
Validate contact data
  ↓
Validate cart
  ↓
Read actual product prices
  ↓
Create order
  ↓
Create order items with price snapshots
  ↓
Queue email notification
  ↓
Return order number
```

Цена в `order_items` должна сохраняться как snapshot.

## 9. Payments

На сайте оплаты нет.

Но модель заказа должна позволять фиксировать:
- payment status;
- paid_at;
- payment amount;
- payment reference;
- payment method.

Это позволит менеджеру внести оплату, поступившую позже.

## 10. Statistics

Нельзя вычислять "реализованные продажи" только по месяцу создания заказа.

Пример:

```text
created_at = 2026-01-31
paid_at    = 2026-02-02
```

В отчетах:
- created orders -> January;
- paid sales -> February.

## 11. Imports

Excel import:
- upload file;
- create import job;
- queue;
- validate rows;
- process chunks;
- save row errors;
- produce result;
- show result in Admin.

Внутри backend импорт разделён на явные роли: workbook reader, value/row parser,
validator/planner, executor, template writer и error report writer. Orchestration-сервисы владеют
границами транзакций, блокировками и durable checkpoints, а lifecycle-сервис — завершением,
повторной диспетчеризацией и cleanup временного файла.

Не обрабатывать 100k+ строк одним HTTP запросом.

## 12. Media

Файлы должны иметь:
- MIME validation;
- size limit;
- generated storage name;
- metadata;
- relation to entity;
- optional alt;
- image variants/thumbnails.

## 13. Audit

Аудитировать важные действия:
- login;
- logout;
- changes to users/roles;
- product changes;
- price changes;
- order status changes;
- payment status changes;
- deletion;
- content changes.

Не сохранять секреты и лишние персональные данные в audit payload.
