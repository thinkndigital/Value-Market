# Phase 11 — CRM + Employees

`docs/IMPLEMENTATION_ROADMAP.md` (Phase 11) scopes this as net-new: *"built on existing users/orders
history plus the Phase 4 employee model."* `docs/DATABASE_GAP_ANALYSIS.md` §5: *"CRM (segments, tags, notes,
CLV): Only implicit via orders/users history"*, with explicit guidance this phase follows directly: *"CLV
can be computed, not stored."*

The "+ Employees" half of this phase's name doesn't add new employee schema — Phase 4 already built
`employees`/`branches`. What this phase does is let CRM actions (notes, tag assignment) be attributed to
whoever actually performed them, which naturally includes a seller's employees once the Phase 4 follow-up
(migrating Seller-panel controllers to `TenantContext`) lands — this phase's own controller already goes
through `TenantContext`, so it inherits that support automatically, same as every other Phase 4+ controller.

## 1. Why notes/tags/segments are seller-scoped, not global

The same customer can order from multiple sellers in this multi-vendor marketplace. A vendor's private notes
about a buyer ("always asks for gift wrap," "disputed a charge once") are that vendor's own business
intelligence — not something every other seller on the platform should see about a shared customer. Every
table carries a nullable `seller_id`: `null` means platform/admin-level (visible to admin only, via direct
service calls — no seller-facing route exposes `sellerId = null`), a real `seller_id` means that seller's
private view. `Seller\CrmController` always resolves the acting seller via `TenantContext` (never a
request-supplied id) and additionally checks the target customer has **actually ordered from this
seller** (`order_items.seller_id` match) before allowing a note/tag — a seller can't snoop on an arbitrary
platform user they've never sold to, and (proven by test, not just reasoned about) can't read another
seller's private notes about a customer they happen to share.

## 2. Notes and tags

`customer_notes`: freeform text, `author_user_id` recorded (whoever wrote it — the acting seller or, once
the Phase 4 follow-up lands, one of their employees). `customer_tags` + `customer_tag_assignments`:
`CrmService::tagCustomer()` is idempotent (tagging a customer who already has that tag is a no-op, not a
duplicate row) via `firstOrCreate()` against a unique `(customer_user_id, customer_tag_id)` constraint.

## 3. Segments and CLV — computed, never stored

`customer_segments` stores only a **filter definition** (`criteria` JSON: `min_orders`,
`min_total_spent`, `max_total_spent`) — never a materialized membership list. `CrmService::evaluateSegment()`
runs the filter fresh against delivered `order_items` every call (grouped by customer, `HAVING` clauses for
each configured criterion), so membership can never go stale relative to new orders — there's no cached list
to forget to refresh.

`CrmService::customerLifetimeValue()` is the same principle at the single-customer level: always the live
sum of that customer's delivered `order_items.sub_total` (optionally scoped to one seller), never a column
anywhere that could drift from the real order history. This directly follows
`DATABASE_GAP_ANALYSIS.md`'s own explicit instruction rather than inventing a stored-and-synced alternative.

## 4. What this phase does not do (explicitly, scope boundaries)

- **No UI** — this phase delivers the backend, matching every prior phase's pattern.
- **No admin-facing CRM controller** — `Seller\CrmController` is the only route surface built; admin/
  platform-level CRM (`seller_id = null`) is reachable via `CrmService` directly but has no HTTP endpoint
  yet, since the roadmap's one-line scope didn't specify an admin view and building one speculatively risks
  guessing at what admin-level CRM should actually look like.
- **No segment→campaign/notification integration** (e.g. "send this segment a promo") — segments are a
  query primitive; wiring them to marketing actions is separate, larger scope.
- **Employee-authored CRM actions aren't yet distinguishable from the owning seller's own** — `author_user_id`
  records who acted, but nothing in the UI/API surfaces "written by employee X" differently; that's a display
  concern for the eventual UI, not a data-model gap.

## 5. Tests

`tests/Feature/Phase11/` (2 new files, 13 new tests):

- `CrmServiceTest.php` (9) — notes round-trip and are seller-scoped (don't leak across sellers, proven
  directly, not just checking the addressed seller's own view); empty notes rejected; tag assignment is
  idempotent; untagging removes the assignment; CLV sums only delivered items for the requested seller,
  ignoring another seller's items for the same customer; segment evaluation matches on `min_total_spent` and
  `min_orders` correctly (proven with both a qualifying and a non-qualifying customer in the same test, not
  just checking one direction) and stays scoped to the segment's own seller.
- `CrmControllerTest.php` (4) — a seller can note a customer who ordered from them; cannot note an arbitrary
  platform user who never did; **cannot read another seller's private note about a customer they both
  share** (the scenario §1 exists to prevent, proven end to end through the real controller — a customer
  with orders from both sellers, seller B's `listNotes()` call correctly returns zero of seller A's notes);
  the lifetime-value endpoint returns the correct seller-scoped figure.

`tests/Feature/Phase1/MigrationBaselineTest.php`'s table-count assertion updated 117 → 121 for the 4 new
tables (expected consequence of this phase's migration).

Full suite: **278 passing** (265 before this phase), zero regressions.
