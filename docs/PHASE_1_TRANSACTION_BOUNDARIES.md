# Phase 1 — Transaction Boundaries (Task E)

## 1. Correcting the Phase 0 audit's claim

The Phase 0 audit stated "zero `DB::transaction` usage across the codebase," based on a literal grep for
the string `DB::transaction`. That's true but incomplete phrasing: it missed
`DB::beginTransaction()`/`DB::commit()`/`DB::rollBack()` (the explicit, non-closure form), which **does**
exist in exactly two pre-existing places — `app/function_helper.php`'s `updateDetails()` helper, and one
narrow spot in `OrderService`'s order-status-update code (both discovered while doing this phase's work).
Both wrap a **single UPDATE statement** — which is already atomic on InnoDB without a transaction — so
neither protects any real multi-step operation. The corrected, precise finding: **narrow, single-statement
transactions existed in 2 places; the actual multi-step financial workflows (place an order, credit a
wallet, process a refund) had none.** That's the gap this phase closes for the highest-value paths.

## 2. What was fixed, and why it's correct

### `OrderService::placeOrder()` — the e-commerce order-creation path

**Pre-existing bug found by reading this method end-to-end**: the wallet debit (when a customer pays partly
with wallet balance) happened *before* order/order-items/order-charges creation and stock decrement, with no
transaction linking them. If order creation failed after the wallet was already debited, the customer's
wallet was left debited with no order to show for it — and nothing rolled it back.

**Fix**: `DB::beginTransaction()` now wraps the wallet debit through the stock-decrement loop (everything
that writes to the database for this order). Every existing early-return error path within that span now
calls `DB::rollBack()` first (previously they just `return`ed, which — now that this is inside a
transaction — would otherwise have left it open). A catch-all `try`/`catch (\Throwable $e)` wraps the whole
span, rolls back, logs the exception, and returns the same error-response shape the method already used for
other failures, so no caller-visible contract changed. Notifications and email (Firebase push, `Mail::send`)
are deliberately **outside** the transaction, after `DB::commit()` — they're external side effects, not
database writes, and holding a DB transaction open across a network call is bad practice regardless of
correctness.

**Verified**: `tests/Feature/Phase1/TransactionAtomicityTest.php` reproduces this exact shape (create an
order inside a transaction, force an exception, assert the row doesn't exist) against the now-InnoDB
`orders` table, and separately asserts a normal transaction commits correctly. Both pass. This is the
automated version of a manual proof done earlier in this phase: the identical code, run against `orders`
while it was still MyISAM, left the row committed despite the exception (MyISAM has no transaction support
at all) — converting the engine (Task B) is not cosmetic, it's what makes this fix possible.

### `WalletService` — `updateBalance()`, `updateCashReceived()`, `updateWalletBalance()`

**Pre-existing bug**: all three read `$user->balance` (or `cash_received`) into PHP, mutated it, and called
`save()` with no row locking — a textbook non-atomic read-modify-write. Two concurrent calls for the same
user (e.g. two commission payouts landing close together) could both read the same starting balance; the
second `save()` silently overwrites the first, losing an update. `updateWalletBalance()` additionally saved
the balance change and logged the transaction record as two **separate** writes — if the second failed after
the first succeeded, the user's balance would change with no transaction record explaining why, breaking
"every financial record traceable to source."

**Fix**: all three now run inside `DB::transaction()` with `User::where('id', ...)->lockForUpdate()->first()`
instead of `User::find()`, and — for `updateWalletBalance()` — the balance `save()` and the transaction-log
write happen inside the same transaction, so they commit or fail together. Business logic (validation order,
messages, the pre-existing razorpay special case) is byte-for-byte unchanged; only the atomicity/locking
wrapper is new.

**Verified**: `tests/Feature/Phase1/WalletServiceTest.php` — debit/credit math, insufficient-balance
rejection leaving the balance unchanged, zero-amount rejection, and (for credit) that the transaction record
is actually written alongside the balance change. 5 tests, all passing.

**Not verified under real concurrent load**: proving the `lockForUpdate()` fix actually prevents a lost
update under concurrency needs two overlapping database connections racing against each other, which is
disproportionate to build as an automated PHPUnit test in this session (PHPUnit is single-threaded; doing
this properly needs a multi-process harness). The fix is standard, well-understood Laravel practice for
exactly this failure mode, and was code-reviewed, not just written — but "code reviewed and standard
pattern" is a different claim from "proven under load," and this document says which one applies.

### `Seller\PosController::place_order()` — the POS order-creation path

**Fix**: the same transaction-boundary treatment as `placeOrder()` — `DB::beginTransaction()` around the
order/order-item write(s), `DB::commit()` once they succeed, catch-all rollback on any exception.

**Known risk found and deliberately NOT fixed here** (in scope for Phase 6 — POS, not Phase 1): this
method's item loop `return`s after its **first** iteration. A POS cart with more than one product only ever
creates an `OrderItems` row for the first item — every other line in the cart is silently dropped from the
order. Separately, **this method never decrements stock for regular products at all** — unlike the
e-commerce path, which calls `ProductService::updateStock()`/`ComboProductService::updateComboStock()`, no
equivalent call exists anywhere in `PosController::place_order()`. There's also a block of dead code after
the loop (`if (isset($res) && !empty($res))`, referencing a variable that is never assigned anywhere in the
method — leftover from an earlier version that called `OrderService::placeOrder()` directly, per a commented-out
line still in the file). Fixing any of this is a POS business-logic decision (how should a multi-item POS
cart behave, where exactly should stock decrement happen, does `order_charges` need a row for POS orders
too) that belongs to Phase 6's actual design work, not a transaction-boundary pass. This phase only makes
what the code *already does* atomic — it does not change what it does. Flagging it here so it isn't
mistaken for "fixed" or lost track of before Phase 6.

### `OrderService::process_refund()` — reviewed, not restructured

This method is ~360 lines and was read in full for this phase. Its actual money-moving call
(`WalletService::updateWalletBalance()`) is already atomic thanks to the fix above. A separate,
non-money-moving step afterward (recalculating `order_charges` for other sellers on a partial refund) goes
through `updateDetails()`, which independently wraps its own single `UPDATE` in a transaction (§1). These two
steps are each individually atomic but not atomic *with each other* — if the wallet refund succeeds and the
`order_charges` recalculation subsequently fails, the refund is real but that bookkeeping update is missed.
This is a narrower, lower-probability gap (two simple, dependency-free SQL operations, not a page-length
multi-service flow like `placeOrder()`) than what was fixed above, and closing it means restructuring a
360-line method this phase did not want to rewrite blind. Documented here as a known, accepted gap rather
than silently left unmentioned.

## 3. Commission crediting — already partially transactional, now more so

`OrderService`'s delivery-boy commission-crediting code (order-status-update path, ~line 1900) already had
its own narrow `DB::beginTransaction()`/`commit()`/`rollback()` around the order-item status `UPDATE`
(§1's second pre-existing example) — but the commission `Transaction::create()` and
`WalletService::updateBalance()` calls that follow happen **after** that transaction already committed, as
separate operations. `WalletService::updateBalance()` is now atomic in itself (§2), so the balance change and
whatever it does internally can't half-apply — but the order-status update and the commission credit remain
two separate top-level transactions, same shape as the `process_refund()` situation above. Not restructured
for the same reason: doing so safely means reading and understanding more of this already-large,
already-partially-transactional method than this phase's time allowed to do carefully. Flagged for the same
future attention.

## 4. What Task E explicitly asked for and where each stands

| Workflow | Status |
|---|---|
| Order creation | Fixed (`OrderService::placeOrder`), verified by automated test |
| Payment confirmation | Covered indirectly — payment-status updates flow through the same `OrderService`/`WalletService` methods fixed above; no separate dedicated payment-confirmation method was found to fix independently |
| Wallet transactions | Fixed (all 3 `WalletService` methods), verified by automated test |
| Stock changes | `ProductService::updateStock()` writes are single-row `UPDATE` statements (already InnoDB-atomic per-row); no multi-step stock operation requiring a transaction wrapper was found in this phase's scope |
| POS sales | Fixed (transaction boundary added); underlying multi-item/no-stock-decrement bugs found and documented, not fixed (Phase 6) |
| Refunds | Reviewed; primary money-moving call already atomic; a narrower non-money bookkeeping gap documented, not fixed |
| Commissions | Delivery-boy commission crediting now atomic at the `WalletService` level; not joined with the order-status update it accompanies (documented gap, §3) |
| Future accounting journal posting | N/A yet — no accounting engine exists (Phase 9); this phase's job was to leave the transaction-boundary *pattern* (begin/try/commit/catch-rollback, or `DB::transaction(closure)` for self-contained operations) demonstrated and working so Phase 9 has a proven convention to build on, not a blank page |

## 5. Scope discipline

Per the instruction not to wrap unrelated operations in giant transactions: every fix above wraps exactly
the database-write span of one coherent business operation (an order, a wallet mutation) and deliberately
excludes external side effects (push notifications, email, HTTP calls to payment gateways) from inside the
transaction boundary.
