# PR #5 CI repair implementation plan

> **For agentic workers:** Use systematic debugging for each failure domain; independent dependency and test repairs may proceed in parallel. Do not commit or push without the required user authorization.

**Goal:** Repair PR #5 CI failures without weakening security, accessibility or visual regression gates. The owner explicitly chose to retain the blocking Client audit until upstream fixes are available.

**Architecture:** Keep the approved application behavior and palette. Update vulnerable dependency chains using supported releases, repair stale test setup, and adopt only independently reviewed platform screenshots.

**Tech stack:** Laravel/PHP, Composer, Nuxt, npm, Vue, Vitest, Playwright, Docker, GitHub Actions.

**Spec:** PR #5 CI logs for head `ba6d597e0bd9b89858e14ca61ef529d4d983d4cf`; `docs/CI.md`, `docs/UI_DESIGN_REVIEW.md`, `docs/ADMIN_APPROVED_PALETTE_TESTS.md`.

## Constraints and review focus

- Preserve API contracts, business behavior, approved colors and design tokens.
- Preserve audit thresholds, exact palette assertions, screenshot tolerance (30 pixels), and retries=0.
- Never substitute screenshots between operating systems or update snapshots without visual review.
- Prefer compatible, minimal lock updates; investigate unsupported dependency changes before adopting them.
- Keep hidden filter tests meaningful by opening the real filter panel before interacting.
- Use isolated test databases and Compose test services; do not reset development data.
- Final review must include dependency compatibility, both platforms, accessibility and repository hygiene.

## 1. Dependency audits

Files: `backend/composer.lock`; `frontend/client/package-lock.json`; package manifests only if a supported minimal fix requires them.

- [x] Reproduce Composer and production npm audit failures and inspect dependency ancestry.
- [x] Verify patched versions and upstream compatibility using primary sources.
- [x] Apply the supported Symfony lock update, preserving security gates. Revert the ineffective Nuxt trial; retain the blocking Client audit by the owner's explicit decision.
- [x] Verify Composer install/validate/audit and backend tests. Client manifest/lock match HEAD, so no client implementation/build changes are included; the production audit still reports 11 high findings.

## 2. Stale product interaction tests

Files: `frontend/admin/e2e/scrollHeader.spec.ts`, `frontend/admin/e2e/softFocus.spec.ts` and a shared test helper only if justified.

- [x] Reproduce missing activity radio controls and trace their current disclosure behavior.
- [x] Open the existing filter disclosure as a user would; retain scroll, pointer, keyboard and forced-colors assertions.
- [x] Run the affected tests with strict checks, then the full Admin suite: 631 passed on Windows and 631 on Linux.

## 3. Visual baselines

Files: reviewed PNGs in `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/`; test fixtures only if investigation proves stale fixture expectations.

- [x] Reproduce Windows and Linux baseline mismatches and collect actual/diff evidence. Three Windows CI-only differences require a new remote run and are not considered resolved locally.
- [x] Compare against current approved product, category, team-access and content designs.
- [x] Obtain independent UI Design Guard review of the 42 actual images before adoption.
- [x] Adopt only accepted same-platform actual images; rerun strict baseline/palette tests and full Admin E2E: both platforms pass all 631 tests, retries=0.

## 4. Documentation and final verification

Files: `tasks/DONE.md`, `tasks/IN_PROGRESS.md`, `docs/CI.md`, this plan and a focused CI repair report.

- [x] Reproduce repository hygiene failure and correct the saved-draft preview anchor.
- [x] Record implementation, review evidence and exact verification results; remove the resolved Composer audit limitation and retain Client findings.
- [x] Run repository hygiene, relevant formatting/static analysis, dependency audits and complete local test gates. Client audit remains blocking by the owner's decision.
- [x] Review final diff independently. Prepare concrete changes for commit/push and remote CI verification; the owner explicitly authorized commit/push and CI verification.
- [ ] Update PR validation with the final remote check results after publication is authorized.
