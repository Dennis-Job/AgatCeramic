# Исторический аудит OpenAPI-контракта (TASK-A014)

> **Статус:** исторический evidence-снимок на дату проверки. Указанные ниже пути, количества и
> результаты тестов не являются текущими метриками. Актуальное состояние описано в
> [`CURRENT_STATE.md`](CURRENT_STATE.md), действующие проверки — в [`CI.md`](CI.md).

Дата аудита: 2026-09-09.

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

The reproducibly locked Redocly CLI validates the OpenAPI 3.1 structure, references,
schemas, parameters, examples and unique operation IDs. A project convention pass also
rejects unsupported schema format/type combinations and malformed or empty request/response
media-type maps. Its fixtures prove these project-specific checks independently.

`backend/scripts/assert-openapi-compatible.php` recursively compares base and candidate
operations. Request schemas are checked contravariantly (existing client inputs must remain
accepted), while response schemas are checked covariantly (new server outputs must remain
valid for existing clients). The gate covers types/nullability, required/properties, enum,
bounds, formats, arrays, composition, additional properties, parameters, headers, status
codes, media types and security alternatives, including local `$ref` traversal. Mutation
fixtures cover breaking and directionally compatible changes.

A breaking change is accepted only for a major `info.version` increase and only when
`OPENAPI_MIGRATION_PLAN.md` contains the matching version section followed by an explicit
`Breaking change` heading. Patch/minor changes cannot override the result.

## Limits

The route coverage gate validates registered wire endpoints and their structural
OpenAPI metadata. Semantic validation also repaired two historical dangling role-operation
schema references without changing runtime behavior. Runtime behavioural assertions
(validation, permissions, error details, streaming downloads and concurrent checkout)
remain covered by their feature and integration tests; these contract gates do not
substitute for those tests.
