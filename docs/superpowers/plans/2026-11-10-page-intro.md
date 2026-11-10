# Page introduction images — implementation plan

**Goal:** Apply the owner's selected material-sample composition to existing content-page introductions and allow each image to be replaced in Admin.

**Approved visual:** first displayed concept, `exec-0ef63cac-8ae7-4298-abb2-11b4d67f7699.png`. Warm existing background, title left, material still life right; existing site shell and lower content preserved.

**Architecture:** nullable page-level `intro` JSON (`image_url`, `image_media_id`, `image_alt`) follows the existing draft/snapshot/publication and media-reference lifecycle. One shared Client component consumes mapped data and per-slug local defaults. Admin uses the existing media editor and pending-file guards.

**Scope:** catalog, about, contacts and dynamic content pages including cooperation, rezka and rospis whenever there is no enabled hero. Existing full heroes and legal, cart, checkout and product layouts retain their structure. Custom pages use a neutral material default. No commits.

**Decisions:** absent/null intro uses a standard illustration; reset restores this default. Uploaded images use contain (no destructive crop). Image failure falls back to standard art, then a stable text-only layout. Images are illustrative, not claims of actual showrooms or projects. SEO description remains a fallback for unspecified pages; known page descriptions are short editorial copy.

## Tasks

- [x] Backend: additive nullable migration, validation, admin/public/preview resources, snapshots and legacy dirty comparison, deletion protection; tests cover draft isolation, private/unsafe images and old snapshots. Synchronize API/OpenAPI.
- [x] Admin: page types/form preservation and Settings card with upload/media selection, alt, reset, busy/pending and unsaved guards; lint/build/unit and targeted E2E.
- [x] Assets: six individual optimized still-life WebP illustrations with consistent neutral background and light.
- [x] Client: shared `PageIntro.vue`, typed intro mapping in public and preview services, per-slug defaults, replacement of duplicated content intro markup. E2E: SSR defaults, custom image, failed-image fallback, hero suppression and responsive sizes 320/640/768/1024/1280.
- [x] Verify Client typecheck/build, relevant production E2E and accessibility; run independent UI Design Guard and resolve findings. Document design comparison, usage and task status.

## Review focus

Pending uploads must block save/publish and route changes. Old drafts/snapshots without intro remain compatible. Published image references survive replacing the draft. Broken or very tall uploaded images do not cause layout shift/overflow. Desktop composition and mobile stacked layout preserve title hierarchy and palette.
