# Phase 15 Final Report — Security Hardening

**Status: complete for the security-hardening scope. The RBAC redesign named in the roadmap's Phase 15
description was not done - not because it was deferred as too risky (the original reasoning here), but
because a later direct re-investigation found there is no actual duplication between `role_id` and Spatie
to unify. See §"the load-bearing decision this phase made" below, and `docs/SECURITY_AUDIT.md` §6.3 for the
full corrected reasoning.** See `PHASE_15_SECURITY_HARDENING.md` and `docs/SECURITY_AUDIT.md` §6 for full
detail; this report is the index and the numbers.

## Exact numbers

| Metric | Count |
|---|---|
| Commits this phase | 4 |
| Findings from the background self-audit | 17 (2 HIGH/MEDIUM-adjacent already fixed pre-audit via audit logging, 7 HIGH/MEDIUM fixed, 5 LOW fixed, 1 investigated and correctly left unchanged, 1 LOW deferred with documented reasoning) |
| New/extended `auditLog()` call sites | 2 (`EmployeeService::create()`, `CommissionRuleController::store()`/`update()`) |
| New service methods | 2 (`AffiliateService::reverseConversionsForOrder()`, `TenantContext::isSellerOwner()`) |
| New Phase 15 test files | 3 |
| Existing test files extended with new regression tests | 4 (`Phase4/EmployeeControllerTest`, `Phase5/PurchaseOrderControllerTest`, `Phase6/PosShiftServiceTest`, `Phase7/AffiliateServiceTest`) |
| New tests this phase | 27 |
| Total test suite at Phase 15 start | 291 passing |
| Total test suite at Phase 15 end | 318 passing, 0 failing |

## What changed

Seven real HIGH/MEDIUM vulnerabilities fixed across purchase orders (cross-tenant variant IDOR), the
affiliate/commission engine (self-referral payout, no return-clawback, unbounded commission rate), and POS
(cross-tenant shift injection, unvalidated payment splits) - plus employee-roster privilege escalation
(any employee had full owner authority over creating/deactivating staff). Five LOW-severity gaps fixed
(missing validators, two TypeError risks, an unthrottled public endpoint). `docs/SECURITY_AUDIT.md` §6
extended with full technical detail for all 17 findings, matching the structure Phase 1's original audit
established.

## The load-bearing decision this phase made

Treat a background audit agent's findings as leads requiring independent verification, not ground truth to
act on directly - one finding (a "latent mass-assignment surface" on two models' `$fillable`) turned out to
be misdiagnosed at the model level once traced to its actual root cause (`Model::unguard()` running
globally, already Phase 2's own documented deferral). The fix for that framing was made, its own regression
test failed, and both were reverted rather than shipped with a comment implying protection that doesn't
exist anywhere in the app. Getting this wrong in either direction - blindly patching what the agent said, or
dismissing the finding once the literal framing didn't hold up - would have been worse than the extra
verification step actually taken.

A second instance of the same discipline, added after this report's original version: this report itself
originally deferred the "RBAC redesign" as too risky to attempt, repeating Phase 2's own framing without
re-checking it against the current code. Prompted by a direct user question about which mechanism to keep,
re-reading the actual authorization code (`RoleMiddleware`, `CheckPermissions`, `UserPermissionController`)
found that framing wrong - `role_id` and Spatie's permission system are two different, correctly-coexisting
concerns, not a duplication needing a merge (full reasoning: `docs/SECURITY_AUDIT.md` §6.3). Attempting the
one genuinely unused piece found on re-investigation (Spatie's Role-assignment mechanism, isolated by
dropping the `HasRoles` trait from `User.php`) was tried and reverted immediately when the full test suite
caught that `HasPermissions` itself depends on a method only `HasRoles` provides. Repeating an earlier
finding without re-verifying it is its own failure mode, distinct from mishandling a new one - this project
treats both as worth catching and correcting in place, not just the first.

## Documented, not built this phase (with reason)

| Finding | Why not built now | Doc |
|---|---|---|
| RBAC redesign (dual `role_id`/Spatie → one mechanism) | Turned out, on direct re-investigation, not to be needed at all - `role_id` and Spatie's permission system are not duplicates (see "the load-bearing decision this phase made" above) | `SECURITY_AUDIT.md` §6.3 |
| Global `Model::unguard()` removal | Same scale of risk - requires auditing ~200+ methods across three ~14,000-line legacy API controllers to confirm none relies on receiving fields outside its declared `$fillable`; Phase 2's own already-documented deferral, confirmed again here | `SECURITY_AUDIT.md` §6.2 |
| `InventoryService` stock-movement/stock-item quantity divergence on clamp | Stock math, the same category of decision Phase 3 explicitly declined to make unsupervised; two concrete remediation options documented for a dedicated follow-up pass | `SECURITY_AUDIT.md` §6.2 |

## Verification performed

- `php -l` clean on every touched file, every commit.
- Full suite run after every commit, not batched to the end - 294 → 298 → 310 → 318, zero regressions at
  any intermediate step.
- Every fixed finding has a regression test proving the specific exploit path is closed AND the legitimate
  path still works (not just that a validation rule exists) - e.g. the owning seller can still create a
  purchase order/POS sale/employee, the self-referral test proves a *different* buyer still gets attributed
  correctly, the commission-rate cap test proves exactly 100% is still accepted.
- Two pre-existing tests (`Phase7\AffiliateServiceTest`, `Phase12\AnalyticsServiceTest`) broke against the
  self-referral fix because their fixtures happened to use the same user as both link owner and buyer -
  exactly the case now correctly rejected. Fixed the fixtures to use a distinct buyer rather than weakening
  the fix.

## What Phase 15 did not do (explicitly, scope boundaries)

Did not redesign RBAC (see above). Did not remove global `Model::unguard()` (see above). Did not change
`InventoryService`'s stock-clamping behavior (see above). Did not extend `auditLog()` to every money-moving
call this session's Phases 9-10 added - those already have a full structured record via the
transactions/ledger tables, so a duplicate text log would be noise. Did not attempt an exhaustive
endpoint-by-endpoint re-audit of Phases 1-3 (already covered by Phase 1/2's own audits) - this phase's scope
was Phase 4-14's new surface specifically.
