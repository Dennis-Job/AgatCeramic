# Interim Audit — HTTP/API layer (TASK-A008)

Audit date: 2026-09-09.

## Evidence

| Area | Result |
| --- | --- |
| Runtime route registry | 105 `/api/v1` routes (`php artisan route:list --path=api/v1 --json`) |
| Input boundary | All 29 concrete controllers import an application Form Request or Laravel `Request`; the thirtieth file is the abstract base controller. |
| Output boundary | 24 controllers import API Resources; endpoints without one return an intentionally empty, download or streamed response. |
| Contract source | `docs/openapi.json` parses as valid JSON. Automated route-to-contract coverage remains TASK-A014. |
| Error envelope | Existing feature tests continue to cover the stable `error` envelope; no undocumented change was made. |

Routes are versioned below `/api/v1`; administrative routes are enclosed by `auth:sanctum` and
`active_admin`. Mutations inspected in the protected API call `Gate::authorize()` or delegate to
a service that does, while response models are mapped through Resources. Simple catalogue/read
queries in controllers are allowed by the architecture; they contain no business mutation.

## Confirmed findings

### A008-1 — controller type boundary is not strict

`php vendor/bin/phpstan analyse app/Http --memory-limit=1G` reports 546 file errors. The HTTP
portion is predominantly attributable to the following real boundary defects:

- `Request::user()` is inferred as nullable and is passed directly to services requiring
  `App\Models\User`;
- validated query/body input remains `mixed` when passed as pagination, enum, identifier and
  shaped collection values;
- several list controllers type their `when()` callback input more narrowly than the request
  value inferred by Laravel;
- upload calls use `Request::file()` as though it were always one `UploadedFile`, despite its
  framework return union.

Runtime middleware and validation currently protect the public contract, but these boundaries
need explicit typed accessors/DTOs or an authenticated-admin request abstraction before the
strict gate can be green. This work stays in A008 and does not require an OpenAPI change as long
as accepted wire values and response bodies do not change.

### A008-2 — four import controllers are not thin

`ProductImportController`, `ProductGroupImportController`, `ProductPriceStatusImportController`
and `ProductImageImportController` each create database records, store/clean files and dispatch
jobs directly. This duplicates the same async-operation lifecycle and prevents one explicit
after-commit boundary. Extraction must be performed once in TASK-A009/TASK-A013, where transaction,
ownership, retry and cleanup semantics are owned; duplicating a new service independently in
A008 would violate the scope separation.

## Decision

No public API behaviour or OpenAPI operation was changed during this audit: there is therefore no
contract edit to make. A008 remains active until typed request/authenticated-user boundaries are
implemented and its relevant feature/permission/error tests pass. The shared import lifecycle
finding is recorded as a prerequisite for A009/A013 rather than silently fixing it in the HTTP
layer.
