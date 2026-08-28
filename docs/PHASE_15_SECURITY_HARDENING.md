# Phase 15 — Security Hardening

## 1. Scope and how this phase was run

`docs/IMPLEMENTATION_ROADMAP.md`'s Phase 15 description names two things: a security hardening pass over
the new subsystems Phases 4-14 added, and an RBAC redesign unifying the app's dual `role_id`/Spatie
authorization mechanism into one. Only the first is done here; §4 below explains why the second is
deliberately not attempted.

The hardening pass itself was not a manual line-by-line re-read of ~10 phases of code. A background review
agent (`Agent` tool, run independently, given the full list of Phase 4-14 controllers/services and specific
check criteria - tenant ownership on every write, mass assignment of ownership foreign keys, unvalidated
input reaching money/stock-moving code, "resolves to a seller_id" checks standing in for "is this action
allowed for this actor") was used to get broad coverage efficiently, then every one of its 17 findings was
independently re-verified against the real source before anything was fixed - the agent's report was treated
as a lead, not a ground truth. One finding (the Branch/Supplier mass-assignment flag) turned out to be wrong
in its specific framing on that re-verification; see §3.6 for how that was handled rather than silently
accepted or silently dropped.

## 2. Part 1 — audit log extension (already covered by its own commit)

Before the structured security pass, two money/privilege-adjacent events that had no history of their own
were wired into the `auditLog()` helper Phase 2 built: creating a new login-capable employee account
(`EmployeeService::create()`), and commission-rate changes (`Admin\CommissionRuleController::store()`/
`update()`, with before/after values on update). Deliberately not extended to every wallet/ledger/partner
money movement this session's Phases 9-10 added - those already persist a full structured record in
`transactions`/`journal_entries`/`partner_transactions`, so a duplicate text-log entry would be noise, not
signal, matching Phase 2's own "not a blanket log-everything pass" discipline. 3 tests
(`tests/Feature/Phase15/AuditLogExtensionTest.php`).

## 3. Part 2 — the 17-finding self-audit

Full technical detail for every finding - exact file/line, exploit scenario, fix, and its test - lives in
`docs/SECURITY_AUDIT.md` §6, which follows the same structure Phase 1's original audit established (this
phase extends that file rather than duplicating or replacing it). Summary here:

### 3.1 Fixed - HIGH/MEDIUM (7 findings)

| # | Severity | Issue | Fix |
|---|---|---|---|
| 1 | HIGH | Purchase-order line items could target another seller's product variant (unchecked, stock/cost-basis poisoning) | Every variant now verified to belong to a product this seller owns |
| 3 | MEDIUM | Affiliate self-referral paid real commission on the affiliate's own purchases | Self-referrals record no conversion |
| 4 | MEDIUM | No commission clawback existed for a return/cancellation after a commission was already paid | `AffiliateService::reverseConversionsForOrder()`, wired into both cancel/return paths |
| 6 | MEDIUM | POS sale could be posted into another seller's open shift by id-guessing | Shift lookup now requires matching the sale's own seller |
| 7 | MEDIUM | POS payment splits weren't checked to sum to the order total - invisible cash under-reporting | A split that doesn't sum to the total is discarded for the trustworthy single-line default |
| 9 | MEDIUM | Any active employee had full owner authority over the employee roster itself | `TenantContext::isSellerOwner()` gates roster mutation to the actual owner |
| 10 | MEDIUM | Percentage commission rate had no upper bound | Capped at 100 on create and update |

### 3.2 Fixed - LOW (5 findings)

| # | Issue | Fix |
|---|---|---|
| 11 | Two CRM methods could throw an uncaught TypeError instead of a clean error | Added the same null-guard every sibling method already has |
| 12 | Public affiliate click-tracker had no rate limiting | `throttle:60,1` middleware |
| 13 | `SupplierController::update()` had no validation at all | Mirrored `store()`'s validator |
| 14 | Branch lat/long accepted with zero validation | Range-checked |
| 15 | CRM tag color accepted with zero validation | Hex-color format check |

### 3.3 Investigated and confirmed to already be a documented Phase 2 deferral (not re-fixed)

Finding 16 (Branch/Supplier `$fillable` including `seller_id`) is not actually fixable at the two-model
level: `Model::unguard()` runs globally on every request (`AppServiceProvider::boot()`), making `$fillable`
inert for every model in the application. This is Phase 2's own already-documented, deliberately-deferred
finding (`docs/PHASE_2_MASS_ASSIGNMENT_AUDIT.md`). A narrowing edit was made, its regression test failed
(proving the point), and both were reverted - shipping a fillable change with a comment implying protection
that doesn't exist anywhere it's written would have been worse than leaving it alone. Full reasoning in
`SECURITY_AUDIT.md` §6.2.

### 3.4 Deferred with reasoning (1 finding)

Finding 17: `InventoryService::recordMovement()` writes the *requested* quantity to the `StockMovement`
audit row but clamps the *applied* delta on `StockItem.quantity` at a floor of zero - the two can silently
diverge. Real, but this is stock math, the same category of decision Phase 3 explicitly declined to make
unsupervised (proportional refund/restock). Two concrete remediation options are documented in
`SECURITY_AUDIT.md` §6.2 for a dedicated follow-up pass rather than a blind pick here.

## 4. Explicitly deferred: RBAC redesign

Not attempted. The roadmap names it as Phase 15 scope; Phase 2 already investigated the dual `role_id`/
Spatie mechanism directly (`docs/PHASE_2_RBAC_ARCHITECTURE.md`) and deliberately declined to unify it, for
the reason that still holds: every route's authorization ultimately traces back to whichever mechanism is
live today, and an unsupervised migration that gets even one edge case wrong risks locking out the admin
panel or silently changing who can do what, application-wide. The standing "continue on your own"
authorization for this session covers real, scoped bug-fixing of exactly the shape this phase delivered -
not a gamble with the whole application's access control while nobody is watching the outcome. Recommended
path, unchanged from Phase 2: pick `role_id` (it's the mechanism every route actually gates on today),
migrate Spatie's permission data into it deliberately with a human reviewing each step, remove the unused
mechanism last.

## 5. Verification

`php -l` on every touched file; full test suite run after every commit (never batched to the end); 27 new
regression tests added, one per exploit path actually closed, following this codebase's established pattern
of proving the attack is blocked and the legitimate path still works, not just that a `Validator` rule
exists. Full suite: 291 passing at Phase 15's start → 318 passing at its end, zero regressions at any
intermediate commit.
