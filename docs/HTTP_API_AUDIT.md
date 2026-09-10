# Interim Audit — HTTP/API layer (TASK-A008)

Audit date: 2026-09-10.

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

### A008-1 — controller type boundary normalized

Protected actions now obtain `App\Models\User` only through the base controller's runtime-checked
`authenticatedAdmin()` boundary. Upload endpoints similarly use a single-file accessor after Form
Request validation. Scalar and structured Form Request inputs are converted at the controller
boundary (including pagination, enum, identifiers and bulk replacements) before entering
services. `php vendor/bin/phpstan analyse app/Http/Controllers --memory-limit=1G` now passes with
zero errors.

The full project strict gate remains owned by TASK-A007: remaining work in Resources, Form Request
override annotations and model declarations is tracked with the quality/model audits rather than
being hidden by ignores. No public wire value or response body changed.

### A008-2 — four import controllers are not thin

`ProductImportController`, `ProductGroupImportController`, `ProductPriceStatusImportController`
and `ProductImageImportController` each create database records, store/clean files and dispatch
jobs directly. This duplicates the same async-operation lifecycle and prevents one explicit
after-commit boundary. Extraction must be performed once in TASK-A009/TASK-A013, where transaction,
ownership, retry and cleanup semantics are owned; duplicating a new service independently in
A008 would violate the scope separation.

## Decision

No public API behaviour or OpenAPI operation changed, so no contract edit is required. Targeted
feature/permission/error coverage passes (89 tests, 692 assertions). The shared import lifecycle
finding remains assigned to A009/A013 rather than being duplicated in the HTTP layer.
