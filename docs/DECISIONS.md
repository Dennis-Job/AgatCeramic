# Architecture Decision Record

## ADR-001 — Laravel API-only

Status: accepted.

Laravel используется как backend/API.

Blade не используется для клиентского интерфейса.

## ADR-002 — Vue Admin

Status: accepted.

Админ-панель — Vue 3 + TypeScript + Vite.

Визуальная основа — TailAdmin Vue.

## ADR-003 — Nuxt Client

Status: accepted.

Публичный сайт — Nuxt.js.

Главная цель frontend client — SEO, скорость и удобство каталога.

## ADR-004 — No customer registration

Status: accepted.

Клиенту не требуется учетная запись.

Заказ оформляется как guest order.

## ADR-005 — No online payment

Status: accepted.

Оплата не проводится на сайте.

Backend хранит payment status и paid_at для учета оплаты менеджером.

## ADR-006 — Modular monolith

Status: accepted.

На первом этапе backend является модульным монолитом Laravel.

Микросервисы не вводятся без доказанной необходимости.

## ADR-007 — API versioning

Status: accepted.

Публичный API начинается с `/api/v1`.

## ADR-008 — Order money snapshots

Status: accepted.

Цена товара сохраняется в order_items на момент оформления заказа.

## ADR-009 — Created vs paid date

Status: accepted.

Дата создания заказа и дата оплаты являются разными бизнес-событиями.

Финансовые отчеты используют paid_at.

## ADR-010 — PII

Status: accepted.

PII минимизируется, доступ разграничивается, чувствительные данные защищаются, PII не попадает в обычные логи.

Юридические и организационные требования 152-ФЗ проверяются отдельно.

