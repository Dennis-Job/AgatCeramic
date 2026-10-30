# SDD ledger — plan: docs/superpowers/plans/2026-10-30-product-collections.md

- Spec approved in chat, 2026-10-30.
- Branch: codex/product-collections. No commits/push authorized.
- Pre-flight shared interfaces: Task 1 schema/REST/public resource → Tasks 2–5;
  collection_id and uses_collections names match across all tasks.
- Ruling: execute the approved implementation without another plan-approval question —
  current developer authorization rules take precedence over skill handoff boilerplate.
- Workspace: existing checkout, separate feature branch; no new worktree requested or created.
- Baseline: Admin brandSearch 4 tests passed; Backend BrandManagement 9 tests/1 expected Unicode skip passed. Docker escalation granted; native backend vendor incomplete, Docker runtime used.
- Tasks 1–5 complete: backend/domain/DB/public projection, Admin, guarded transfer, workbook compatibility, Client section.
- Task 6 complete: OpenAPI v13.0/compatibility, canonical docs, statuses, independent code/UI review and verification.
- Backend SQLite: 699 tests/7728 assertions, 4 expected skips, no failures. Full PostgreSQL feature: 603/7389; concurrency 15/77; schema up/down/up passed.
- Pint673/PHPStan/Composer validate/architecture passed; Composer audit fails on pre-existing symfony/mime CVE-2026-45070, lock unchanged.
- Admin: lint/format/build passed; 281 unit, 28 focused E2E passed, five widths. Client: build/typecheck/format; initial15/17, failed2 then green; final layout/axe green.
- Independent UI Design Guard ACCEPTED,30 screenshots. Two Important code findings reproduced then fixed: false/0/'0' category guard and long transliterated slug; regression suite17/122 green. No Critical/Minor findings.
- Ruling: existing ink-soft token selected for newly introduced Client caption; no owner-approved palette/global styles changed. This settles the unassigned new-caption token choice without changing established components.
- Ruling: no additional feature/dependency updates from audit. Existing symfony/mime advisory is reported separately; changing lock would extend approved scope.
- Auto-review rejected full backup transfer before explicit storage consent. User then explicitly authorized local copy/restore in named temporary container; subsequent copy/restore approved.
- Protected original and fresh cutover backups600; both restored/tested on allowed local PostgreSQL copy. Working migration/apply6/reapply0 passed; 2 collections/6 assignments/6 original values,0 incompatible pairs.
- Maintenance ended; queue/scheduler running. Actual Admin/public API/SSR product routes200; incomplete collection story absent, navigation retained.
- Temporary container with full-data copy removed; host copies deleted, protected source backups retained. No commit/push created. Final file inventory/report in docs/PRODUCT_COLLECTIONS_2026-10-30.md.
