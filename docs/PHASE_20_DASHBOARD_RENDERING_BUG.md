# Phase 20 — /admin/home and /seller/home rendered as a blank dashboard

## Context

Found while assessing progress on the redesign roadmap item for `/admin/home` (`docs/IMPLEMENTATION_ROADMAP.md`).
Before redesigning anything, the actual current page was loaded in a real headless browser (the system
prompt's own standing instruction for UI work: verify in a browser, don't just read the Blade source) to get
a real "before" screenshot. The result: the sidebar and header rendered correctly (confirming Phase 12/13's
redesign work), but the entire main content area - every stat card, the revenue chart, orders overview,
everything - was completely blank. `curl`-fetching the same authenticated URL returned a full 287KB response
with all the real markup and real data present. The live browser DOM, after the page finished loading, had
lost roughly 230KB of it - the `.container-fluid.mt-5.px-6` wrapper that should hold the whole dashboard was
present but empty.

## Root cause

`resources/views/admin/pages/forms/home.blade.php` (and its seller equivalent,
`resources/views/seller/pages/forms/home.blade.php`) both `@include('Chatify::layouts.headLinks')` partway
through `@section('content')` - i.e. inside `<body>` - to pull in the assets the embedded chat widget needs.

`resources/views/vendor/Chatify/layouts/headLinks.blade.php` (this app's own published copy of the Chatify
package's view) is a real `<head>` partial: it contains a `<title>` tag, five `<meta>` tags, a `<style>`
block, and several `<script src>` tags - content that only makes sense inside `<head>`, and that the Chatify
package's *other* views (`Chatify::pages.app`, `user-app`, `seller-app` - genuinely standalone chat pages)
correctly use it for.

Per the HTML5 parsing spec, when a browser's parser is in the "in body" insertion mode and encounters a
`<title>`, `<meta>`, `<style>`, `<script>`, `<link>`, `<base>`, or `<template>` start tag, it does not treat
it as ordinary body content - it reprocesses the token using the "in head" insertion mode's rules instead.
In practice, real browsers (verified against Chromium via a live headless-browser test, not just spec
reading) treat this as a strong signal that the document has re-entered head-like parsing, and everything
that follows gets misplaced instead of ending up as visible body content - which is exactly what was
observed: markup up to and including Chatify's own `<script>` tags survived, and everything after it
(the entire real dashboard) vanished from the rendered DOM.

This is a genuine, deterministic bug independent of network conditions - it does not require any external
resource to fail to reproduce; it was confirmed with the sandboxed test environment's normal outbound
network policy, no deliberate request-blocking involved.

### A second, related issue on the same page

While diagnosing this, `resources/views/admin/layout.blade.php` was also found to only load its own local
jQuery (`assets/admin/js/jquery.min.js`) via `admin.include_script`, which is placed *after* `</body>`.
`Chatify::layouts.headLinks` separately loads a second copy of jQuery from an external CDN
(`code.jquery.com`), positioned earlier in the page - meaning that, until this fix, the *only* jQuery
available to any inline script on `/admin/home` before the very end of the page was that external CDN copy.
If it's ever slow, blocked, or unreachable (ad blockers, restrictive corporate networks, a CDN outage), every
script on the page that assumes jQuery is already loaded breaks. The app already ships its own local jQuery
copy, making the CDN dependency entirely unnecessary. Fixed independently of the parsing bug above, since
both needed fixing for the dashboard to be reliably testable and reliably correct for real users.

## Fix

**The parsing bug** (`resources/views/admin/include_css.blade.php`, `resources/views/seller/include_css.blade.php`):
added `@stack('chatify_head')` immediately before each layout's real `</head>`. `home.blade.php` in both
panels now does:

```blade
@push('chatify_head')
    @include('Chatify::layouts.headLinks')
@endpush
```

instead of a bare `@include`, so all of Chatify's head-only markup actually renders inside `<head>` where it
belongs - the standard Laravel mechanism for exactly this situation, not a rewrite of the Chatify partial
itself (which is still used as-is by the package's own standalone chat pages, where the original bare
`@include` remains correct because those pages don't yet have a full `<head>` of their own by that point in
the same way).

**The jQuery CDN dependency** (`resources/views/admin/layout.blade.php`, `resources/views/seller/layout.blade.php`):
added `<script src="{{ asset('/assets/admin/js/jquery.min.js') }}"></script>` immediately after `<body>`
opens, in both layouts - guaranteeing `$`/`jQuery` are available from the very start of the page, regardless
of the CDN script's reachability. The CDN script tag itself was left in place (Chatify's own package code,
now harmless once the local copy has already defined `$`/`jQuery` first) rather than edited, to keep the
change minimal.

**Incidental fix in the same investigation, unrelated to either bug above**:
`public/assets/admin/custom/custom.js:144` did `document.getElementById("app_url").dataset.appUrl` with no
null check. `#app_url` is only rendered by the authenticated `x-admin.header`/`x-seller.header` components,
so this line threw `Cannot read properties of null (reading 'dataset')` on every page using the pre-auth
login layout (admin/seller/delivery_boy login pages all share this one script file), silently killing every
other line in the file - form validation, media pickers, AJAX endpoint helpers - on all three login pages.
Now falls back to `window.location.origin + "/"` when the element isn't present.

## Verification

- Real headless-browser (Playwright + the environment's pre-installed Chromium) screenshot of `/admin/home`
  before and after: before, a blank content area beneath a working sidebar/header; after, the full dashboard
  - stat cards (Sellers/Orders/Products/Total Earnings), the Revenue Analytics chart card, New Messages,
  Orders Overview, Customer Statistics, Recent Tickets, Recent Orders - all rendering with real (zero, on
  this empty dev DB) values.
- `tests/Feature/DashboardHeadStackingTest.php` (new, 2 tests): asserts, at the HTML-response-string level,
  that Chatify's `messenger-color` `<meta>` tag appears before `</head>` (not after, i.e. not in `<body>`)
  for both `/admin/home` and `/seller/home`, and that the dashboard's own chart markup still renders after
  `</head>` as real body content. PHPUnit has no real HTML parser to reproduce the browser's exact
  reparenting behavior, so this test checks the fix's precondition (correct tag placement) rather than
  re-deriving the browser bug directly - the actual "does it render" claim is the screenshot evidence above.
- Full suite: 383/383 passing (was 381 before this phase; +2 for the new test).

## What this phase did not touch

While screenshotting the fixed dashboard, the Orders Overview card showed clearly-wrong-looking numbers (e.g.
"Delivered 29646") next to "0 sellers/0 products" on the same page. This was originally flagged here as a
suspected pre-existing query bug on "an empty test database" - that assumption was wrong (the dev DB actually
holds 145k real `order_items` rows across 3 stores, seeded for Phase 19's own performance profiling) and has
since been root-caused and fixed as its own follow-up: see `docs/PHASE_20_1_STORE_SCOPE_FALLBACK.md`. Left
the incorrect note here as a paper trail rather than deleting it silently.

**The Revenue Analytics and Customer Statistics charts still don't render** (found while checking whether
this phase's fix also resolved them - it didn't, and they're a separate, pre-existing issue). Both are
`ApexCharts` instances instantiated in inline `<script>` blocks inside `@section('content')`. `ApexCharts`
itself is only ever loaded via `admin.include_script.blade.php` (`assets/js/plugins/apexcharts.js`), which -
like the jQuery issue this phase already fixed - is `@include`d after `</body>`, i.e. after the inline script
that tries to use it. The `document.ready()` wrapper *should* make this safe (it doesn't fire until the whole
document, script tags included, has finished parsing) - but a live browser check showed `window.ApexCharts`
still `undefined` 45 seconds after page load, even though the browser did eventually issue a request for
`apexcharts.js` and that request returns a valid 522KB UMD bundle (`curl`-verified, HTTP 200, well-formed).
`admin.include_script.blade.php` loads roughly 60 scripts in strict sequence (several genuinely duplicated -
both `jquery.min.js` and `jquery.js`, both `jquery-sortable.js` and `sortable.js`, the whole FilePond dist
bundle alongside a separate `filepond.js`) with no bundling, deferral, or parallelization, on top of a second,
independent full copy of most of that same list already loaded by the login page moments earlier. Whether
`apexcharts.js` genuinely never finishes executing, or would work given more patience than tested here (45s),
this script-loading architecture is fragile and slow regardless of the specific charts' fate - it's a real
finding, but fixing it (bundling/deduplicating/deferring ~60 legacy script includes) is a distinctly different
and much larger undertaking than this phase's parsing-corruption fix, and is flagged here rather than
attempted blind.
