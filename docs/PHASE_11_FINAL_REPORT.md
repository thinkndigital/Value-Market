# Phase 11 Final Report — CRM + Employees

**Status: complete for the scope delivered — see §4 in `PHASE_11_CRM.md` for what's explicitly deferred.**
That document carries full design/implementation detail; this report is the index and the numbers.

## Exact numbers

| Metric | Count |
|---|---|
| Commits this phase | 1 |
| New tables | 4 (`customer_notes`, `customer_tags`, `customer_tag_assignments`, `customer_segments`) |
| New models | 4 |
| New services | 1 (`CrmService`) |
| New controllers | 1 (`Seller\CrmController`) |
| New routes | 4 |
| New Phase 11 test files | 2 |
| New Phase 11 tests | 13 |
| Existing test updated (not a regression — expected table-count shift) | 1 (`MigrationBaselineTest`, 117 → 121) |
| Total test suite (Phase 1–11) | 278 passing, 0 failing |
| Total test suite at Phase 11 start | 265 |

## What changed

- **Notes and tags**: seller-scoped (a nullable `seller_id` — the same customer can be shared across
  multiple sellers in this marketplace, and each vendor's private CRM notes about them shouldn't leak to
  another vendor). Tag assignment is idempotent.
- **Segments**: a saved filter definition (`min_orders`/`min_total_spent`/`max_total_spent`), evaluated
  fresh against delivered `order_items` on every call — never a materialized, potentially-stale membership
  list.
- **Customer Lifetime Value**: computed on demand from delivered order history, never stored anywhere —
  directly following `DATABASE_GAP_ANALYSIS.md`'s own explicit guidance ("CLV can be computed, not
  stored").
- **`Seller\CrmController`**: every action requires the target customer to have actually ordered from the
  acting seller (`order_items.seller_id` match) before allowing a note/tag — a seller cannot use this to
  look up an arbitrary platform user they've never sold to.

## The IDOR scenario worth calling out specifically

Because the same customer can legitimately have orders from multiple sellers, the "does this customer belong
to me" ownership check alone isn't sufficient to prevent cross-seller leakage — a customer who ordered from
*both* Seller A and Seller B would pass that check for either seller, but Seller B still must not see Seller
A's private notes about them. `CrmControllerTest::test_a_seller_cannot_list_notes_seller_a_wrote_about_a_shared_customer`
builds exactly that scenario (one customer, real orders from both sellers) and confirms Seller B's
`listNotes()` call returns zero of Seller A's notes — the seller-scoping on the notes themselves, not just
the ownership gate, is what's actually tested.

## Documented, not built this phase (with reason)

| Finding | Why not built now | Doc |
|---|---|---|
| No admin-facing CRM controller | `CrmService` supports platform-level (`seller_id = null`) CRM already; no HTTP endpoint built since the roadmap's scope didn't specify an admin view and one wasn't guessed at | `PHASE_11_CRM.md` §4 |
| No segment→campaign/notification integration | Segments are a query primitive; wiring to marketing actions is separate, larger scope | `PHASE_11_CRM.md` §4 |
| No UI | This phase delivers the backend; matches every prior phase's pattern | `PHASE_11_CRM.md` §4 |

## Verification performed

- Migration run clean against the real MariaDB instance this repo's test suite uses.
- `php -l` clean on every touched/new PHP file.
- `php artisan route:list` confirms all 4 new routes registered, no name collisions.
- Full suite run after the change: **278/278 passing**, zero regressions.
- The cross-seller note-leak scenario (see above) is proven end to end through the real controller with a
  customer holding real orders from both sellers involved — not a synthetic single-seller setup that
  wouldn't have caught the actual risk.
- Segment evaluation is proven in both directions (a qualifying customer included, a non-qualifying one
  excluded) for each criterion, not just "the query runs."

## What Phase 11 did not do (explicitly, scope boundaries)

Did not build an admin-facing CRM controller/route (service-level support exists). Did not build
segment-to-campaign integration. Did not build any new UI. Did not add new employee schema — Phase 4 already
built `employees`/`branches`; this phase's own controller inherits employee-login support automatically via
`TenantContext`, the same as every controller since Phase 4.
