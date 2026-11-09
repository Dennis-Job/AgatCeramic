# Readable Order Numbers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Номера `customer_id-sequence`, постоянный клиент по нормализованному телефону.
**Architecture:** Backend-only: customers с nullable unique phone и last_order_sequence,
nullable FK/sequence в orders; атомарное выделение в существующей checkout transaction.
**Tech Stack:** Laravel 13, PostgreSQL, PHPUnit/SQLite, Pint, PHPStan.
**Spec:** ../specs/2026-11-09-readable-order-numbers-design.md — утверждена владельцем.
**Execution:** Основная реализация в текущей рабочей папке; независимому агенту —
только PostgreSQL integration tests/support worker. Ветка codex/readable-order-numbers.
Без коммитов. Повторное разрешение на реализацию не запрашивать: оно дано владельцем.

## Global Constraints

- Только новые номера; исторические заказы и их номера не изменять.
- Общая нормализация RussianPhoneNumber; ID не даёт доступа к истории клиента.
- `customer_id`/`customer_order_sequence` запрещены во входном checkout.
- Retention удаляет связь/суффикс, очищает телефон после последнего заказа;
  технический ID и счётчик сохраняются, номера не используются повторно.
- Изменений компонентов UI нет; документация и OpenAPI синхронизируются.

## Review Focus

- Конкурентный первый checkout: один клиент, два уникальных номера.
- Cleanup против checkout: новый заказ не остаётся с потерянным телефонным ключом.
- Поздняя ошибка checkout: откат клиента и счётчика вместе с заказом.
- Обезличивание последнего заказа: телефон удалён, новый ID при повторной покупке.
- История и rollback: старые номера/поиск сохранены; выданные номера нельзя потерять при rollback.

### Task 1: Allocation and checkout

**Files:** new customers/orders migration, app/Models/Customer.php,
app/Services/OrderNumberService.php, app/Services/OrderCreationService.php,
app/Models/Order.php, app/Http/Requests/Api/V1/StoreOrderRequest.php,
tests/Feature/Api/ReadableOrderNumberTest.php, tests/Feature/Api/OrderCreationTest.php.
**Interface:** OrderNumberService::generate(string $phone): array{customer_id: int,
customer_order_sequence: int, order_number: string}; requires caller transaction.

- [x] Write behavior tests for phone equivalence, snapshots, replay, failed checkout,
  new-client separation, legacy search, server-only fields; observe RED.
- [x] Migration: customers.id/nullable unique phone/last_order_sequence, timestamps;
  orders nullable customer_id/customer_order_sequence, composite unique index,
  order_number length 64. Refuse rollback once counters have allocated numbers.
- [x] Allocate via upsert then locked customer row and increment inside checkout;
  spread allocation after input attributes. Preserve current response shape.
- [x] Run focused API tests to GREEN.

### Task 2: Retention and concurrency

**Files:** app/Services/Retention/OrderDataDestructionService.php,
tests/Feature/Retention/OrderCustomerRetentionTest.php,
tests/Integration/PostgresReadableOrderNumberTest.php,
tests/Support/readable_order_number_worker.php, migration tests.
**Interface:** existing anonymize(Order, CarbonImmutable): void / delete(Order): void.

- [x] Add RED retention tests covering remaining/last order, delete and anonymize,
  re-purchase, transaction rollback, safe schema rollback.
- [x] Lock the customer in the same transaction as destruction; detach before cleanup;
  retain phone only while another associated order exists. Preserve legacy flow.
- [x] Integration tests with separate processes: first/repeat allocation and cleanup
  racing checkout, actual committed PostgreSQL transactions.
- [x] Run focused retention/API and PostgreSQL tests to GREEN.

### Task 3: Contract, verification, review

**Files:** docs/openapi.json, API.md, DATABASE.md, DECISIONS.md, CURRENT_STATE.md,
PERSONAL_DATA_LIFECYCLE.md, OPENAPI_MIGRATION_PLAN.md, tasks/IN_PROGRESS.md / DONE.md.

- [x] Document new/legacy/ANON patterns, counters, retention, release/rollback.
- [x] Run full backend suite, Pint, PHPStan, architecture check, OpenAPI lint/compatibility;
  preserve exact failing/skipped checks in progress report.
- [x] Run migration on isolated PostgreSQL test DB before applying additive migration
  to local development DB; verify historical number counts unchanged, without PII output.
- [x] Independent code review; fix actionable findings with regression tests.
- [x] Review git diff/status, mark completed task, report evidence and limits.

## Progress

- Pre-flight: tasks 1/2 share customers fields and transaction boundary; interfaces above
  are authoritative. Task 3 only documents final behavior.
- Tasks 1–3: complete. Baseline 769 PASS / 7 SKIP; final backend 785 PASS / 7 SKIP.
- PostgreSQL: 6 concurrency tests and 16 feature/migration tests PASS. PHPStan/Pint/OpenAPI PASS.
- Final review: fixed AdminOrder nullable name/address schema; regression test RED→GREEN.
- Local additive migration applied; all 3 historical order numbers/IDs unchanged.
- No deferred findings, no commits. Full file list and evidence: ../../READABLE_ORDER_NUMBERS_2026-11-09.md.
