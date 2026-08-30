# Phase 4 (32-phase SaaS brief) — Multi-Tenant Architecture Decision

The brief asks whether this app needs a `Merchant` concept above `Seller` for its multi-tenant SaaS vision.
Decision, confirmed with the product owner: **no new model** — `Seller` already functions as the tenant
unit, and every merchant-facing feature this session has built (Phase 6 payment gateways, Phase 12
affiliate program) was already built directly on `seller_id`, not a placeholder waiting for a `Merchant`
layer.

## Evidence (not assumed — verified by reading the actual code)

1. **Every seller-owned resource in every panel is already scoped by `seller_id`/`store_id`** — products,
   orders, branches, employees, and now (this session) payment gateways and affiliate settings. Confirmed
   in `docs/COMPLETE_SYSTEM_MAP.md` §5 (Phase 1) and re-confirmed by every phase built since.
2. **A seller gets exactly one `SellerStore` row, created exactly once, at registration/approval time** —
   grepped for every place a `SellerStore` row is inserted: `Seller\v1\ApiController::register()`
   (mobile app self-registration) and `Admin\SellerController::store()` (admin-created sellers). Both are
   onboarding events, not a general "add another store" feature — no controller anywhere lets an existing
   seller create a second store. `seller_store.seller_id` has no unique DB constraint (so nothing would
   break if that changed later), but nothing in the app today exercises more than one store per seller.
3. **This session's own architectural choices already assumed `Seller` is the tenant unit**, not a
   `Merchant` layer above it: Phase 6's `seller_payment_gateways.seller_id`, Phase 12's per-product/
   per-store affiliate settings — both keyed directly to `Seller`/`SellerStore`, with no intermediate
   entity anywhere in the design.

## Why not build `Merchant` anyway, as a "just in case"

This repo's own Phase 1 architecture docs (`PHASE_1_ARCHITECTURE.md` Task F) already established the rule
this decision follows: *"Do not create abstractions merely for appearance."* A `Merchant` model with a
1:1 relationship to `Seller` and no behavior of its own would be exactly that — a rename with extra steps,
not a real capability. If a genuine one-merchant-owns-multiple-stores need shows up later (flagged, not
built speculatively), it can be added then without disrupting anything built on `seller_id` today, since
nothing currently assumes uniqueness at the database level.

## Status

Phase 4 is **CLOSED** — the brief's ask is satisfied by the existing architecture, verified rather than
assumed, with the decision and evidence recorded here instead of silently marking it "not applicable."
