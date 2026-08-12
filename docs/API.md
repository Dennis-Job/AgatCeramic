# Контракт API

## OpenAPI

Машиночитаемая спецификация OpenAPI 3.1: [`openapi.json`](openapi.json).
В ней описываются только реализованные маршруты. При добавлении или изменении
маршрута необходимо одновременно обновить соответствующую OpenAPI-операцию;
запланированные маршруты в этом документе не являются доступными операциями API.

## Базовый путь

`/api/v1`

## Маршрутизация

Все маршруты приложения должны быть объявлены ниже `/api/v1`. `GET /api/v1` —
лёгкая точка входа для определения версии; она возвращает `{"version":"v1"}`.
Публичные и административные маршруты находятся в разных группах. Промежуточное
ПО аутентификации и авторизации для административной группы добавляется в рамках
соответствующих задач аутентификации и политик доступа.

## Публичный API

### Каталог

`GET /categories`

`GET /categories/{slug}`

`GET /products`

`GET /products/{slug}`

`GET /brands`

`GET /brands/{slug}`

### Корзина

`GET /cart`

`POST /cart/items`

`PATCH /cart/items/{item}`

`DELETE /cart/items/{item}`

### Заказы

`POST /orders`

`GET /orders/{order_number}/confirmation`

### Контент

`GET /pages/{slug}`

`GET /site-settings/public`

## Admin authentication

The first-party Admin SPA uses Laravel Sanctum's cookie-based session authentication.
Before any state-changing request, it must request `GET /sanctum/csrf-cookie` with
credentials included, then send the URL-decoded `XSRF-TOKEN` value in the `X-XSRF-TOKEN`
header. Browser requests must include credentials.

- `POST /admin/auth/login` — accepts `email` and `password`; limited to five attempts per
  minute for an email/IP combination. Invalid credentials and blocked accounts both return
  the standard `401 unauthenticated` response.
- `GET /admin/auth/me` — returns the authenticated active administrator.
- `POST /admin/auth/logout` — invalidates the current session and CSRF token.

All other administrative API routes require `auth:sanctum` and an active account. Fine-grained
authorization is introduced in TASK-024.

## Административный API

Административный API должен быть защищён аутентификацией и авторизацией.

### Товары

- `GET /admin/products`
- `POST /admin/products`
- `GET /admin/products/{product}`
- `PATCH /admin/products/{product}`
- `DELETE /admin/products/{product}`

### Категории

- `GET /admin/categories`
- `POST /admin/categories`
- `GET /admin/categories/{category}`
- `PATCH /admin/categories/{category}`
- `DELETE /admin/categories/{category}`

### Бренды

Операции создания, просмотра, изменения и удаления.

### Характеристики

Операции создания, просмотра, изменения и удаления.

### Заказы

- список;
- просмотр;
- изменение статуса;
- изменение статуса оплаты;
- добавление комментария;
- фиксация оплаты.

### Обращения

Операции создания, просмотра, изменения и удаления, а также процесс обработки
статусов.

### Контент

Операции создания, просмотра, изменения и удаления.

### SEO

Операции создания, просмотра, изменения и удаления.

### Аналитика

Маршруты только для чтения отчётов.

## Правила API

- единообразные HTTP-коды состояния;
- ошибки валидации в стабильном формате;
- пагинация;
- фильтрация;
- сортировка;
- отсутствие скрытых побочных эффектов;
- авторизация для каждой защищённой операции;
- обновление документации API при изменениях.

## Формат ошибок

Каждый ошибочный ответ `/api/v1` использует единый JSON-конверт:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Переданные данные не прошли проверку.",
    "details": {
      "name": ["Поле «Название» обязательно для заполнения."]
    }
  }
}
```

`code` стабилен и предназначен для программной обработки. `message` безопасен
для отображения; непредвиденные исключения никогда не раскрывают внутреннее
сообщение. `details` всегда является объектом и содержит сообщения валидации по
полям только при `validation_failed`; для остальных типов ошибок он пуст.

| HTTP-код | Код ошибки |
| --- | --- |
| 400 | `bad_request` |
| 401 | `unauthenticated` |
| 403 | `forbidden` |
| 404 | `not_found` |
| 405 | `method_not_allowed` |
| 409 | `conflict` |
| 422 | `validation_failed` / `unprocessable_entity` |
| 429 | `too_many_requests` |
| 5xx | `internal_server_error` / `http_error` |

## Соглашение о ресурсах API

Каждый доменный ответ API должен использовать ресурс Laravel API. Ресурсы
наследуются от `App\\Http\\Resources\\ApiResource`, именуются
`{Entity}Resource` и располагаются в пространстве имён соответствующего домена внутри
`app/Http/Resources` (например,
`App\\Http\\Resources\\Catalog\\ProductResource`). Контроллеры не должны
возвращать Eloquent-модели напрямую.

Ответ с одиночным ресурсом использует стабильный конверт:

```json
{
  "data": {
    "id": 17,
    "name": "Керамическая плитка"
  }
}
```

Списки используют `{Entity}Resource::collection($items)` и возвращают `data`
в виде массива. Если входные данные являются пагинатором, сохраняются стандартные
объекты Laravel `links` и `meta`. Поля ресурса задаются явным списком разрешённых
полей: нельзя
раскрывать атрибуты модели прямой сериализацией модели или возвратом
`$this->resource`.
