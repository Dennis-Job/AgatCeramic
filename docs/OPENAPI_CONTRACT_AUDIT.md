# Interim Audit — OpenAPI contract (TASK-A014)

Audit date: 2026-09-09.

## Evidence and result

The Laravel runtime registry contains 105 versioned routes, representing 111 distinct
HTTP operations after `HEAD` is excluded and the framework's `PUT|PATCH` routes are
expanded. `OpenApiRouteCoverageTest` compares that registry to `docs/openapi.json`
exactly. It also verifies Sanctum security, path parameters, standard JSON error
envelopes, paginated response `links`/`meta`, request-body schemas/examples and
operation summaries/IDs.

The reconciliation found and corrected these contract gaps:

- `PUT /admin/users/{user}` and `PUT /admin/roles/{role}` were accepted by Laravel but
  absent from OpenAPI;
- both audit-log operations had no response schemas; the documented `404` for a single
  audit log incorrectly referred to `BadRequest` instead of `NotFound`;
- the human-readable guide still advertised planned public catalog/content routes and
  had a placeholder in the contacts section. It now lists only implemented public
  contact routes and documents the protected contact workspace flow.

## CI gates

`backend/scripts/assert-openapi-compatible.php` compares the base and candidate
specifications in CI. Removing an operation, declared response or schema; changing
operation security; making a request body mandatory; or removing required/enum schema
values fails unless `info.version` is bumped. A breaking change still requires the
repository's migration plan and synchronized guide/OpenAPI updates during review.

## Limits

The route coverage gate validates registered wire endpoints and their structural
OpenAPI metadata. Runtime behavioural assertions (validation, permissions, error
details, streaming downloads and concurrent checkout) remain covered by their feature
and integration tests; this audit does not substitute for those tests.
