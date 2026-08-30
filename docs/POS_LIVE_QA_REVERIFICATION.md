# POS re-verification (product owner reported "مش شغال" / "not working")

Live browser (Playwright) re-verification of the seller POS, requested as the second item in the product
owner's "كلهم بالترتيب" priority list, after the earlier session's POS fixes. Found and fixed 3 more real
bugs - the checkout-completion one is the most likely root cause of "not working."

## What was found

1. **Console-polluting crash on every page** (`public/assets/admin/custom/custom.js`, two
   `document.addEventListener("DOMContentLoaded", ...)` blocks around the category-order and
   feature-section-order admin features): both queried a container element (`.category-order-container`,
   `.section-order-container`) and called `TweenLite.to(container, ...)` unconditionally - loaded via the
   shared `include_script.blade.php` on every admin/seller/delivery_boy page, so it ran (and threw "Cannot
   tween a null target.") on every page that isn't the one admin page each container actually exists on,
   POS included. Not itself functionally blocking (each `addEventListener` handler runs independently), but
   real console noise on literally every page load. Fixed with an early `if (!container) return;` guard in
   both blocks.
2. **2 more broken FilePond asset paths**, distinct from the 3 already fixed earlier this session:
   `filepond-plugin-media-preview.min.css` (in `include_css.blade.php`, all 3 panels) and
   `filepond-plugin-image-validate-size.js` (in `include_script.blade.php`, all 3 panels) pointed at
   `/assets/filepond/dist/...`, a path that doesn't have those two files (only non-`.min` or altogether
   missing there) - the real files live under `/assets/admin/css/` and `/assets/admin/js/` respectively,
   matching the pattern the earlier fix already used for the other 3.
3. **The payment-method radio buttons' `<label for="...">` never matched any real `id`** on 12 of them (6 in
   the regular-product payment modal, 6 in the combo-product one, in
   `resources/views/seller/pages/forms/pos.blade.php`) - every `<input class="payment_method" type="radio">`
   had no `id` attribute at all, so clicking the label text (Cash/Card Payment/Bar Code.../Net Banking/Online
   Payment/Other - the natural way a user selects a radio option) did nothing. Clicking exactly on the small
   radio circle itself still worked, but that's not how most users interact with a labeled radio group.
   Fixed by giving each input a real `id` matching its label's `for` value.
4. **The actual checkout-completion crash** (`App\Http\Controllers\Seller\PosController::place_order()` and
   `combo_place_order()`): both looked up the buyer's mobile number via `fetchDetails(User::class, ['id' =>
   $user_id], 'mobile')` - an Eloquent Collection - then indexed `[0]->mobile`. `place_order()`'s guard was
   `!empty($user_mobile)`, which is always `true` for an object regardless of whether the lookup found
   anything (same bug class this session already found and fixed twice in
   `App\v1\ApiController::place_order()` - see `docs/PHASE_21_API_AUDIT.md`); `combo_place_order()` had no
   guard at all. **This crashed every walk-in POS sale with no "Existing Customer" selected** (`place_order()`
   allows this - no customer required for a regular sale) with "Undefined array key 0", a 500 response, and
   the payment modal simply sitting there doing nothing when "Pay Now" was clicked - exactly matching "POS
   مش شغال." `combo_place_order()` does require a customer to be selected, so its instance of the bug needs a
   stale/deleted user_id to trigger rather than a walk-in sale, but it's the same unguarded pattern, fixed the
   same way.

## Verification

Live Playwright click-through, not just unit tests: seller login → POS → add a product → Proceed To Order →
click the "Cash" payment label (not the radio itself) → confirm the checkout dialog → Pay Now. Before the
fixes: label click did nothing (bug 3), and even with the radio selected directly, submission 500'd (bug 4).
After: the radio selects correctly from the label click, the sale completes, a real `Order` row is created
(`payment_method = COD`, `is_pos_order = 1`), and the cart clears - the same outcome a real cashier would see.

Regression coverage: `tests/Feature/Phase1/PosSaleTest.php`'s new
`test_a_walk_in_sale_with_no_customer_selected_succeeds` and
`tests/Feature/Phase6/PosComboStockTest.php`'s new
`test_a_combo_sale_with_a_stale_customer_id_succeeds_instead_of_crashing` cover bug 4 specifically (both
crashed before the fix, confirmed by temporarily reverting it). Full suite: 661 passing, up from 659 after
the storefront build, zero regressions.
