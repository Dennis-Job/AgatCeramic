# Interim Audit — models, database and integrity (TASK-A010)

Audit date: 2026-09-09.

## Confirmed structure

| Area | Result |
| --- | --- |
| Models | 33 models; every one declares Laravel 13 `#[Fillable]` attributes (not a global unguarded model). |
| Serialization | `User` is the only model carrying `#[Hidden]`, for password and remember token. Casts are declared on 27 models; enum, decimal, datetime, array and boolean fields use model casts. |
| Relations | Catalog, orders, contacts, access-control, import and cleanup relations have typed Eloquent return declarations and generic PHPDoc relation types. |
| Factories | 20 factories cover the aggregate roots and test-created records. Infrastructure/audit/cleanup records intentionally have no factory where tests create them through their application services. |
| Migrations | 58 normal migrations plus one explicitly opt-in legacy-finalization migration. The finalizer refuses non-empty variant storage and is outside the normal migration path. |

Migration source includes foreign keys with appropriate cascade/null/restrict actions, composite
uniques for pivots/values/cart lines/import rows, dedicated lookup/pagination indexes, and
PostgreSQL-specific partial/expression indexes protecting a single primary product image and
unordered product-relation invariants. Services use locked reads for the catalog/cart/order/contact
operations that require concurrency protection.

## Confirmed findings

### A010-1 — static model/factory declarations are incomplete

The strict analyzer identifies missing `#[Override]` attributes on model `casts()` methods and
factory `definition()` methods. It also reports a smaller set of factory type errors caused by
untyped Faker `randomElement()` results. These are declaration defects, not evidence of a changed
database contract, but must be fixed without broadening model types or suppressing errors.

### A010-2 — PostgreSQL verification is unavailable in this local runtime

`php artisan migrate:fresh --force` was attempted as a migration check but stopped before any
migration operation because this PHP runtime has no `pdo_pgsql` driver. It attempted to connect to
the local `agatceramic` database and failed during connection creation; no schema change occurred.
The normal SQLite suite subsequently passed: 226 tests and 1,608 assertions. PostgreSQL migration
and concurrency checks must be run only against CI-only `agatceramic_test` through the documented
environment in TASK-A015; they were not treated as passed here.

### A010-3 — model policy boundary requires an explicit decision

`User::hasPermission()` executes an authorization relation query on an Eloquent model. It is small
and currently used for active importer revalidation, but the architecture prohibits business logic
in models. Relocating it affects authorization/queue behaviour and should be designed with TASK-A009
and A012 rather than moved mechanically during this audit.

## Decision and closure condition

No migration, model schema or legacy table was changed. TASK-A010 remains active until model/factory
static declarations are corrected, the authorization helper boundary is decided, and clean
PostgreSQL migration/integrity verification succeeds against `agatceramic_test`. Legacy
`product_variants` and `product_variant_attribute_values` stay read-only until the separately
authorised, reconciled finalization migration.
