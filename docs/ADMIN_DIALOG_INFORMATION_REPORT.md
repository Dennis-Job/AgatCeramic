# Admin dialog information footers — 2026-10-03

Moved standalone explanatory copy in Admin dialogs into a shared `UiDialogFooter`
with the blue `UiAlert tone="info"` used by the product photo dialog. The footer
keeps actions at the opposite edge on wider screens and stacks them on narrow
screens. The `note` slot is also used for the active product-editor step.

Covered product main/details/photos/variants/review tabs, employee, category, category
attribute assignment, attribute, attribute group, role, page, store, slider,
settings legal preview, confirmation, and UI-kit showcase dialogs. Dialog titles,
field-level guidance, entity context, loading/empty/error states, and validation
alerts remain with their related content. Dialog descriptions retain their
`aria-describedby` targets in the footer.

## Verification

- `npm run lint` — passed.
- `npm run build` — passed; existing Vite chunk-size advisory remains.
- `prettier --write` on changed Vue files — passed.
- `npm run format:check` — reports pre-existing formatting issues in ignored
  `frontend/admin/.tmp` Playwright artifacts; changed Vue files were formatted.
- `git diff --check` — passed.
- Independent UI Design Guard — accepted; no blocking findings. The shared
  footer stays stacked at 640 px and becomes a row at 768 px. Product draft
  guidance only appears while creating a new item.
- Product photo step and photos-only dialog both explain that upload, deletion,
  and order are saved immediately.
- Visual inspection in the browser was not completed; the guard assessed the
  component structure and responsive classes from the diff.
