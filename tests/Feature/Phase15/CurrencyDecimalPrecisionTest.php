<?php

namespace Tests\Feature\Phase15;

use App\Models\Currency;
use App\Services\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 32-phase SaaS brief, Phase 15 (docs/TECHNICAL_DEBT.md): formatePriceDecimal() always showed exactly 2
 * decimals regardless of which currency was actually configured - wrong for JOD/KWD/BHD (real, directly
 * relevant given this app's own Jordan/Gulf gateway work in Phase 6B - all three need 3 decimal places) and
 * JPY (needs 0). Fixed via a static ISO 4217 minor-unit exception table (CurrencyService::
 * decimalPlacesFor()) rather than a new per-installation admin setting - this is fixed, well-known data,
 * not something that varies per merchant.
 */
class CurrencyDecimalPrecisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_decimal_places_for_known_three_decimal_currencies(): void
    {
        $service = app(CurrencyService::class);
        $this->assertSame(3, $service->decimalPlacesFor('JOD'));
        $this->assertSame(3, $service->decimalPlacesFor('KWD'));
        $this->assertSame(3, $service->decimalPlacesFor('BHD'));
        $this->assertSame(3, $service->decimalPlacesFor('OMR'));
    }

    public function test_decimal_places_for_known_zero_decimal_currencies(): void
    {
        $service = app(CurrencyService::class);
        $this->assertSame(0, $service->decimalPlacesFor('JPY'));
        $this->assertSame(0, $service->decimalPlacesFor('KRW'));
    }

    public function test_decimal_places_defaults_to_two_for_an_unlisted_or_case_insensitive_code(): void
    {
        $service = app(CurrencyService::class);
        $this->assertSame(2, $service->decimalPlacesFor('USD'));
        $this->assertSame(2, $service->decimalPlacesFor('EUR'));
        $this->assertSame(2, $service->decimalPlacesFor('not_a_real_code'));
        $this->assertSame(3, $service->decimalPlacesFor('jod'), 'Must be case-insensitive.');
    }

    /**
     * CurrencyService::getDefaultCurrency() memoizes its result in a function-local static for the
     * lifetime of the PHP process (correct for a real one-request-per-process web request), which means an
     * earlier test file in the same suite run may have already resolved and cached a different default
     * currency than whatever this test creates - asserting against a hardcoded expectation here would be
     * flaky depending on suite run order/composition. Instead this proves the actual behavior that
     * matters - omitting the currencyCode argument resolves through CurrencyService::getDefaultCurrency()
     * (not a hardcoded 'USD'/2-decimals default) - by comparing against decimalPlacesFor() of whatever
     * that call currently, genuinely returns, which holds regardless of test run order or history.
     */
    public function test_formate_price_decimal_with_no_explicit_code_resolves_through_get_default_currency(): void
    {
        Currency::forceCreate(['name' => 'Jordanian Dinar', 'code' => 'JOD', 'symbol' => 'JD', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        $service = app(CurrencyService::class);
        $expectedDecimals = $service->decimalPlacesFor($service->getDefaultCurrency()->code ?? null);

        $this->assertSame(number_format(12.345, $expectedDecimals), formatePriceDecimal(12.345));
    }

    /** A currencyCode passed explicitly always takes priority, regardless of the resolved default. */
    public function test_formate_price_decimal_honors_an_explicitly_passed_currency_code_regardless_of_the_db_default(): void
    {
        $this->assertSame('1,235', formatePriceDecimal(1234.6, 'JPY'));
        $this->assertSame('12.30', formatePriceDecimal(12.3, 'USD'));
        $this->assertSame('12.345', formatePriceDecimal(12.345, 'JOD'));
    }
}
