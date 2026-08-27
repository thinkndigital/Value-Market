# Phase 14 — AI Analytics Layer

(Phase 13 — Mobile Applications — remains blocked: the Flutter source has not been provided, confirmed
absent from this repository per `docs/IMPLEMENTATION_ROADMAP.md`'s own blocking note. Skipped in sequence,
not silently dropped.)

`docs/IMPLEMENTATION_ROADMAP.md` (Phase 14) scopes this precisely: *"API/service scaffolding only, per
master prompt Section 34 — no hardcoded fake insights."* That instruction is the load-bearing constraint
for everything in this phase.

## 1. What "scaffolding, no fake insights" means in practice

Two ways this phase could have gone wrong, both avoided:

- **Hardcoded fake insights** — canned strings like *"Your sales are trending up! Consider running a
  promotion"* regardless of what the data actually says. Never built.
- **A stubbed/mocked "AI" call** that pretends to be intelligent while returning made-up numbers — this is
  the same failure mode with extra steps. No AI/LLM provider credentials exist for this application (no API
  key configured, nothing to call), and faking one out to produce plausible-looking insights would be
  exactly the "hardcoded fake insights" the instruction forbids, just dressed up as an API response.

**What this phase actually built instead**: `AnalyticsInsightService`, whose every returned number is a real
value derived live from `AnalyticsService` (Phase 12) or direct queries against real tables — never invented,
never a placeholder. This is genuinely closer to "rule-based analytics" than "AI," and is named/described
that way rather than oversold as artificial intelligence it isn't.

## 2. What's built

- **`periodOverPeriodRevenue($sellerId, $from, $to)`** — compares real revenue (via Phase 12's
  `salesSummary()`) in the requested period against the immediately preceding period of the same length.
  `change_percent` is `null` — not `0`, and not a divide-by-zero crash — when the previous period had zero
  revenue, since "infinite percent growth from zero" isn't a meaningful number to report.
- **`lowStockAlerts($sellerId, $threshold)`** — variants genuinely low on stock right now (`0 < quantity <=
  threshold`) from the real `stock_items` table (Phase 5). A rule-based flag on live data, not a forecast or
  prediction — described as such.

## 3. The documented extension point for a real AI provider

A true LLM-backed insights layer — e.g. summarizing `AnalyticsService`'s numbers into natural-language
observations, or genuinely predictive analysis — is real, valuable future work, but requires:

1. An actual AI provider decision and API credentials (OpenAI, Anthropic, or similar) configured for this
   application, which don't exist yet and aren't this phase's call to make unilaterally.
2. A clear contract for what goes *in* (real `AnalyticsService`/`AnalyticsInsightService` output — never
   fabricated context) and what comes back out, so a future integration wraps genuine data rather than
   inventing it.

`AnalyticsInsightService`'s existing methods are exactly that "real data in" half of the contract, ready for
a future provider to consume once the credentials/decision exist. Building the provider side now, without
real credentials, would mean either leaving it non-functional (dead code) or mocking a response (the
forbidden fake-insight pattern) — neither is what "scaffolding" should mean.

## 4. What this phase does not do (explicitly, scope boundaries)

- **No real AI/LLM integration** — see §3; requires a provider decision and credentials this phase doesn't
  have.
- **No new UI** — this phase delivers the backend, matching every prior phase's pattern.
- **No predictive/forecasting analytics** (demand forecasting, churn prediction, etc.) — these are
  genuinely AI/ML territory, not rule-based derivations, and are exactly what §3's real-provider integration
  would eventually enable; not faked here.

## 5. Tests

`tests/Feature/Phase14/AnalyticsInsightServiceTest.php` (4 tests): period-over-period revenue computed
correctly against a real previous period; the zero-previous-revenue case returns `null` rather than crashing
or fabricating a percentage; low-stock alerts correctly include an in-range variant and exclude both a
well-stocked one and an out-of-stock one (proven as three distinct cases in one test, not just "the query
runs"); low-stock alerts scoped correctly to the requested seller.

Full suite: **291 passing** (287 before this phase), zero regressions. No migration this phase.
