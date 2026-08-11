# API Contract

## Base

`/api/v1`

## Public API

### Catalog

`GET /categories`

`GET /categories/{slug}`

`GET /products`

`GET /products/{slug}`

`GET /brands`

`GET /brands/{slug}`

### Cart

`GET /cart`

`POST /cart/items`

`PATCH /cart/items/{item}`

`DELETE /cart/items/{item}`

### Orders

`POST /orders`

`GET /orders/{order_number}/confirmation`

### Content

`GET /pages/{slug}`

`GET /site-settings/public`

## Admin API

Admin API должен быть защищен authentication + authorization.

### Products

- `GET /admin/products`
- `POST /admin/products`
- `GET /admin/products/{product}`
- `PATCH /admin/products/{product}`
- `DELETE /admin/products/{product}`

### Categories

- `GET /admin/categories`
- `POST /admin/categories`
- `GET /admin/categories/{category}`
- `PATCH /admin/categories/{category}`
- `DELETE /admin/categories/{category}`

### Brands

CRUD.

### Attributes

CRUD.

### Orders

- list;
- view;
- update status;
- update payment status;
- add comment;
- record payment.

### Contacts

CRUD/status workflow.

### Content

CRUD.

### SEO

CRUD.

### Analytics

Read-only reporting endpoints.

## API rules

- consistent HTTP status codes;
- validation errors in stable format;
- pagination;
- filtering;
- sorting;
- no hidden side effects;
- authorization on every protected operation;
- API documentation updated with changes.
