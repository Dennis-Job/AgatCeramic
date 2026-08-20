# TODO

## Phase 0 — Project foundation

- [x] TASK-001 Initialize Git repository and project structure
- [x] TASK-002 Initialize Laravel API-only backend
- [x] TASK-003 Initialize Vue Admin
- [x] TASK-004 Initialize Nuxt Client
- [x] TASK-005 Configure Docker development environment
- [x] TASK-006 Configure environment variables and secrets
- [x] TASK-007 Configure CI checks

## Phase 1 — Backend foundation

- [x] TASK-010 PostgreSQL connection
- [x] TASK-011 Redis
- [x] TASK-012 API v1 routing
- [x] TASK-013 API error format
- [x] TASK-014 API Resources convention
- [x] TASK-015 logging and PII masking
- [x] TASK-016 queue and scheduler
- [x] TASK-017 OpenAPI documentation

## Phase 2 — Admin authentication

- [x] TASK-020 Admin users
- [x] TASK-021 Authentication
- [x] TASK-022 Roles
- [x] TASK-023 Permissions
- [x] TASK-024 Policies
- [x] TASK-025 Audit log
- [x] TASK-026 Employee management API and page
- [x] TASK-027 Roles and permission assignments API and page
- [x] TASK-028 Permission catalogue API and page
- [x] TASK-029 Audit log API and page

## Phase 2.1 — Access control hardening

- [x] TASK-029A Admin password reset API and page
- [x] TASK-029B Audit entity snapshots and retention policy
- [x] TASK-029C Database-level audit log immutability

## Phase 3 — Catalog

- [x] TASK-030 Categories
- [x] TASK-031 Category tree
- [x] TASK-032 Attribute groups
- [x] TASK-033 Attributes
- [x] TASK-034 Category attributes
- [x] TASK-035 Brands
- [x] TASK-036 Products
- [x] TASK-037 Product variants
- [x] TASK-038 Product attributes
- [x] TASK-039 Product images
- [x] TASK-040 Related products
- [x] TASK-041 Product search/filtering

## Phase 3 — Catalog follow-ups

- [x] TASK-041A Complete the Catalog OpenAPI contract and reconcile API documentation
- [x] TASK-041B Require options when changing an attribute to select/multiselect
- [x] TASK-041C Remove product image files when deleting a product
- [x] TASK-041D Enforce a single primary product image under concurrent uploads

## Phase 3 — Catalog hardening follow-ups

- [x] TASK-041E Make product deletion and image uploads race-safe, including cleanup of every cascaded storage file
- [x] TASK-041F Enforce the no-reverse-product-relations invariant under concurrent replacements
- [x] TASK-041G Make category-tree acyclicity and parent eligibility race-safe under concurrent updates
- [x] TASK-041H Add pagination handling to the Admin Catalog UI for all paginated catalog collections and selectors
- [x] TASK-041I Complete the human Catalog API guide for category, brand, attribute, and image-management behavior
- [x] TASK-041J Reconcile or remove unused standalone Catalog Admin views

## Phase 3 — Catalog audit remediation

- [x] TASK-041K Preserve product and variant attribute-value integrity when product categories or category attribute assignments change
  - Reject or transactionally reconcile stale values when moving a product to another category.
  - Reject or transactionally reconcile affected product and variant values when detaching a category attribute.
  - Enforce required attributes after category changes and cover the selected policy in API documentation and tests.
- [x] TASK-041L Make attribute type and option changes safe for existing product and variant values
  - Detect values that would become invalid when changing an attribute type or replacing/removing options.
  - Define and implement an explicit conflict, migration, or cleanup policy without silently preserving invalid values.
  - Cover product and variant values, audit events, OpenAPI behavior, and regression tests.
- [ ] TASK-041M Complete the attribute model required by the Catalog specification
  - Reconcile `string`, `text`, `integer`, `decimal`, `boolean`, `select`, `multiselect`, `color`, and `date`; document any intentional normalization between types.
  - Add product-page visibility control and typed backend/Admin validation and editing.
  - Update migrations, API Resources, OpenAPI, the human API guide, and tests.
- [ ] TASK-041N Add catalog identifiers and units required for products and variants
  - Define product-versus-variant ownership for article numbers, barcodes, and units of measure.
  - Add database constraints, CRUD/search behavior, Admin fields, OpenAPI documentation, and tests.
- [ ] TASK-041O Trace deferred Catalog media and SEO requirements to their delivery phases
  - Explicitly map category images and brand logos/documents to the Media Library work in Phase 7.
  - Explicitly map category and brand SEO fields to Phase 8 and reconcile placeholder database fields with the documented plan.
  - Update requirements/task documentation so no Catalog requirement is left without an owner.
- [ ] TASK-041P Make Admin Catalog pagination resistant to stale requests and last-page deletions
  - Prevent out-of-order responses from replacing newer page state and disable or guard navigation while appropriate.
  - Clamp or reload the previous valid page after deleting the final item on a page.
  - Cover products, brands, attributes, and attribute groups with automated tests.
- [ ] TASK-041Q Bring Catalog dialogs and image ordering into compliance with the UI Design Review standard
  - Add dialog semantics, accessible names, focus management, focus restoration, Escape/backdrop behavior, and keyboard-safe destructive confirmations.
  - Add accessible names to icon-only actions and a keyboard-operable alternative for product image reordering with announced state changes.
  - Complete authenticated responsive QA at the required breakpoints and obtain an independent UI Design Guard review.
- [ ] TASK-041R Add automated Admin Catalog component and end-to-end coverage
  - Introduce the minimal frontend test tooling needed for pagination, filters, integrated product tabs, selectors, error/loading/empty states, and accessibility regressions.
  - Exercise the authenticated `/products`, `/categories`, `/brands`, `/attribute-groups`, and `/attributes` flows.
- [ ] TASK-041S Add real PostgreSQL concurrency regression tests for Catalog invariants
  - Exercise competing transactions for primary images, product deletion versus image uploads, reverse relations, and category-tree mutations.
  - Run these tests against PostgreSQL in CI rather than relying on sequential SQLite coverage.
- [ ] TASK-041T Make product image storage cleanup durable and retryable
  - Handle storage deletion failures without silently leaving orphaned files or skipping remaining disks/files.
  - Add an idempotent retry/outbox/queue cleanup mechanism with operational visibility and tests.
- [ ] TASK-041U Stabilize shared backend test factories used by the Catalog suite
  - Prevent `RoleFactory` from generating names that collide with seeded system roles.
  - Add a regression check proving repeated full-suite runs remain deterministic.

## Phase 4 — Import/export

- [ ] TASK-050 Excel product export
- [ ] TASK-051 Excel import
- [ ] TASK-052 Import validation
- [ ] TASK-053 Import error report
- [ ] TASK-054 Bulk product editing
- [ ] TASK-055 Queue-based bulk operations

## Phase 5 — Cart and orders

- [ ] TASK-060 Guest cart
- [ ] TASK-061 Add/update/remove cart items
- [ ] TASK-062 Create order
- [ ] TASK-063 Order number
- [ ] TASK-064 Order items snapshots
- [ ] TASK-065 Order statuses
- [ ] TASK-066 Payment status
- [ ] TASK-067 Manual payment registration
- [ ] TASK-068 Order history
- [ ] TASK-069 Order comments
- [ ] TASK-070 Email confirmation

## Phase 6 — Contacts

- [ ] TASK-080 Callback requests
- [ ] TASK-081 Email requests
- [ ] TASK-082 Partner requests
- [ ] TASK-083 Assignment to manager
- [ ] TASK-084 Contact workflow

## Phase 7 — Content

- [ ] TASK-090 Site settings
- [ ] TASK-091 Pages
- [ ] TASK-092 Banners
- [ ] TASK-093 Sliders
- [ ] TASK-094 Stores
- [ ] TASK-095 Working hours
- [ ] TASK-096 Media library

## Phase 8 — SEO

- [ ] TASK-100 SEO metadata
- [ ] TASK-101 Canonical
- [ ] TASK-102 Sitemap
- [ ] TASK-103 Robots
- [ ] TASK-104 Redirects
- [ ] TASK-105 Structured data
- [ ] TASK-106 SEO AI draft generation

## Phase 9 — Analytics

- [ ] TASK-110 Orders dashboard
- [ ] TASK-111 Paid sales dashboard
- [ ] TASK-112 Monthly reports
- [ ] TASK-113 Category/brand/product sales
- [ ] TASK-114 Average order value

## Phase 10 — Client

- [ ] TASK-120 Catalog pages
- [ ] TASK-121 Category pages
- [ ] TASK-122 Product pages
- [ ] TASK-123 Cart
- [ ] TASK-124 Checkout
- [ ] TASK-125 Order confirmation
- [ ] TASK-126 SEO implementation
- [ ] TASK-127 Structured data
- [ ] TASK-128 Performance optimization

## Phase 11 — Production

- [ ] TASK-140 Production Docker
- [ ] TASK-141 CI/CD
- [ ] TASK-142 Backups
- [ ] TASK-143 Monitoring
- [ ] TASK-144 Security hardening
- [ ] TASK-145 Production deployment
