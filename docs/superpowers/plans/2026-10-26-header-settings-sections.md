# Header settings sections implementation plan

> **For agentic workers:** Use the existing workspace and execute the small, coupled change inline; independent UI Design Guard reviews the result.

**Goal:** Implement the owner's selected first visual option for Content → Appearance → Header and navigation.

**Architecture:** Compose three existing `UiCard` sections in `SiteChromeEditor`. Reuse `MediaImageEditor` and `UiFileDropzone`, adding an opt-in media permission restriction matching the previous logo field. Keep API, preview, draft/publish, footer and global tokens unchanged.

**Tech stack:** Vue 3, TypeScript, existing Seller UI-kit.

**Spec:** Owner selection “Первый вариант” on 2026-10-26; generated reference `exec-677f5fed-e940-4ee5-9340-2b51290e254a.png` in this chat. Three visible sections with gray headers and white bodies; existing upload control, compact navigation rows. Section chevrons are omitted because sections remain visible and have no collapse interaction. Existing actual content and empty logo state take precedence over illustrative mock data.

## Constraints and review focus

- Preserve approved colors/fonts and the three-zone workspace.
- Without `media.manage`, show the selected logo/fallback without selection, removal or upload controls; do not request media for an empty logo.
- Pending files and uploading must continue emitting existing guards.
- Existing image editor consumers retain default permissions and behavior.
- No page overflow at 320/640/768/1024/1280 px; verify the narrow desktop inspector at 1440 px too.

## Steps

- [x] Add meaningful permission/preview tests; verify the new restriction test fails before implementation.
- [x] Implement opt-in permission restriction and logo-specific upload wording in existing media editor.
- [x] Compose upper strip, logo and navigation sections; preserve menu CRUD and accessibility labels.
- [x] Adapt and run appearance/media regression checks, unit, lint, formatting and build.
- [x] Inspect real UI and responsive screenshots; obtain independent UI Design Guard review.
- [x] Document result, review and checks; update task status. No commit requested.
