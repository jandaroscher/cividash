<?php

namespace Tests\Unit;

use App\Enums\TimeGranularity;
use PHPUnit\Framework\TestCase;

class TimeGranularityTest extends TestCase
{
    // --- periodKeyPattern validation ---

    /** @dataProvider validPeriodKeysProvider */
    public function test_valid_period_key_matches_pattern(TimeGranularity $granularity, string $periodKey): void
    {
        $pattern = $granularity->periodKeyPattern();
        $this->assertMatchesRegularExpression($pattern, $periodKey);
    }

    public static function validPeriodKeysProvider(): array
    {
        return [
            'year 2023' => [TimeGranularity::Year, '2023'],
            'year 1900' => [TimeGranularity::Year, '1900'],
            'year 2100' => [TimeGranularity::Year, '2100'],
            'quarter Q1' => [TimeGranularity::Quarter, '2023-Q1'],
            'quarter Q4' => [TimeGranularity::Quarter, '2023-Q4'],
            'month January' => [TimeGranularity::Month, '2023-01'],
            'month December' => [TimeGranularity::Month, '2023-12'],
            'week W01' => [TimeGranularity::Week, '2023-W01'],
            'week W52' => [TimeGranularity::Week, '2023-W52'],
            'day start of year' => [TimeGranularity::Day, '2023-01-01'],
            'day end of year' => [TimeGranularity::Day, '2023-12-31'],
        ];
    }

    /** @dataProvider invalidPeriodKeysProvider */
    public function test_invalid_period_key_rejected(TimeGranularity $granularity, string $periodKey): void
    {
        $this->assertFalse($granularity->isValidPeriodKey($periodKey));
    }

    public static function invalidPeriodKeysProvider(): array
    {
        return [
            'year with quarter' => [TimeGranularity::Year, '2023-Q1'],
            'year with text' => [TimeGranularity::Year, 'abc'],
            'quarter Q0' => [TimeGranularity::Quarter, '2023-Q0'],
            'quarter Q5' => [TimeGranularity::Quarter, '2023-Q5'],
            'quarter missing Q' => [TimeGranularity::Quarter, '2023-1'],
            'month 00' => [TimeGranularity::Month, '2023-00'],
            'month 13' => [TimeGranularity::Month, '2023-13'],
            'week W0' => [TimeGranularity::Week, '2023-W0'],
            'week W00' => [TimeGranularity::Week, '2023-W00'],
            'week W53 in non-53-week year' => [TimeGranularity::Week, '2023-W53'],
            'day month 13' => [TimeGranularity::Day, '2023-13-01'],
            'day day 32' => [TimeGranularity::Day, '2023-01-32'],
            'day impossible Feb 31' => [TimeGranularity::Day, '2023-02-31'],
            'day impossible Apr 31' => [TimeGranularity::Day, '2023-04-31'],
            'day impossible Feb 29 non-leap' => [TimeGranularity::Day, '2023-02-29'],
        ];
    }

    public function test_week_53_valid_for_53_week_year(): void
    {
        // 2020 has ISO week 53
        $this->assertTrue(TimeGranularity::Week->isValidPeriodKey('2020-W53'));
    }

    public function test_feb_29_valid_for_leap_year(): void
    {
        $this->assertTrue(TimeGranularity::Day->isValidPeriodKey('2024-02-29'));
    }

    // --- generateLabel ---

    /** @dataProvider labelGenerationProvider */
    public function test_generate_label(TimeGranularity $granularity, string $periodKey, string $locale, string $expected): void
    {
        $this->assertEquals($expected, $granularity->generateLabel($periodKey, $locale));
    }

    public static function labelGenerationProvider(): array
    {
        return [
            'year DE' => [TimeGranularity::Year, '2023', 'de', '2023'],
            'year EN' => [TimeGranularity::Year, '2023', 'en', '2023'],
            'quarter Q1 DE' => [TimeGranularity::Quarter, '2023-Q1', 'de', 'Q1 2023'],
            'quarter Q4 EN' => [TimeGranularity::Quarter, '2023-Q4', 'en', 'Q4 2023'],
            'month January DE' => [TimeGranularity::Month, '2023-01', 'de', 'Jan 2023'],
            'month January EN' => [TimeGranularity::Month, '2023-01', 'en', 'Jan 2023'],
            'month December DE' => [TimeGranularity::Month, '2023-12', 'de', 'Dez 2023'],
            'month December EN' => [TimeGranularity::Month, '2023-12', 'en', 'Dec 2023'],
            'week W5 DE' => [TimeGranularity::Week, '2023-W05', 'de', 'KW 5 2023'],
            'week W5 EN' => [TimeGranularity::Week, '2023-W05', 'en', 'W5 2023'],
            'week W52 DE' => [TimeGranularity::Week, '2023-W52', 'de', 'KW 52 2023'],
            'day DE' => [TimeGranularity::Day, '2023-01-15', 'de', '15.01.2023'],
            'day EN' => [TimeGranularity::Day, '2023-01-15', 'en', '15/01/2023'],
        ];
    }

    // --- previousPeriodKey ---

    /** @dataProvider previousPeriodKeyProvider */
    public function test_previous_period_key(TimeGranularity $granularity, string $periodKey, ?string $expected): void
    {
        $this->assertEquals($expected, $granularity->previousPeriodKey($periodKey));
    }

    public static function previousPeriodKeyProvider(): array
    {
        return [
            'year normal' => [TimeGranularity::Year, '2023', '2022'],
            'quarter Q2' => [TimeGranularity::Quarter, '2023-Q2', '2023-Q1'],
            'quarter Q1 wraps to previous year' => [TimeGranularity::Quarter, '2023-Q1', '2022-Q4'],
            'month March' => [TimeGranularity::Month, '2023-03', '2023-02'],
            'month January wraps' => [TimeGranularity::Month, '2023-01', '2022-12'],
            'week W10' => [TimeGranularity::Week, '2023-W10', '2023-W09'],
            'week W01 wraps' => [TimeGranularity::Week, '2023-W01', '2022-W52'],
            'week W01 wraps to W53' => [TimeGranularity::Week, '2021-W01', '2020-W53'],
            'day normal' => [TimeGranularity::Day, '2023-01-15', '2023-01-14'],
            'day first of month' => [TimeGranularity::Day, '2023-02-01', '2023-01-31'],
            'day first of year' => [TimeGranularity::Day, '2023-01-01', '2022-12-31'],
        ];
    }

    // --- cases ---

    public function test_all_granularity_cases_exist(): void
    {
        $cases = TimeGranularity::cases();
        $values = array_map(fn (TimeGranularity $g) => $g->value, $cases);

        $this->assertContains('year', $values);
        $this->assertContains('quarter', $values);
        $this->assertContains('month', $values);
        $this->assertContains('week', $values);
        $this->assertContains('day', $values);
        $this->assertCount(5, $cases);
    }

    // --- inputPlaceholder and inputHelperText ---
    // These delegate to __() translation keys and require Laravel boot.
    // Covered by Feature tests via the Filament form rendering.

    // --- isValidInput (German format) ---

    public function test_is_valid_input_accepts_german_format(): void
    {
        $this->assertTrue(TimeGranularity::Year->isValidInput('2023'));
        $this->assertTrue(TimeGranularity::Quarter->isValidInput('2023-Q1'));
        $this->assertTrue(TimeGranularity::Month->isValidInput('01.2023'));
        $this->assertTrue(TimeGranularity::Month->isValidInput('12.2023'));
        $this->assertTrue(TimeGranularity::Week->isValidInput('2023-KW5'));
        $this->assertTrue(TimeGranularity::Week->isValidInput('2023-KW05'));
        $this->assertTrue(TimeGranularity::Day->isValidInput('15.01.2023'));
        $this->assertTrue(TimeGranularity::Day->isValidInput('1.1.2023'));
    }

    public function test_is_valid_input_rejects_wrong_format(): void
    {
        $this->assertFalse(TimeGranularity::Day->isValidInput('1. Tag'));
        $this->assertFalse(TimeGranularity::Day->isValidInput('2023-01-15'));
        $this->assertFalse(TimeGranularity::Month->isValidInput('2023-01'));
        $this->assertFalse(TimeGranularity::Month->isValidInput('Januar 2023'));
    }

    // --- normalizeInput (German -> ISO) ---

    public function test_normalize_input_converts_german_to_iso(): void
    {
        $this->assertEquals('2023', TimeGranularity::Year->normalizeInput('2023'));
        $this->assertEquals('2023-Q1', TimeGranularity::Quarter->normalizeInput('2023-Q1'));
        $this->assertEquals('2023-01', TimeGranularity::Month->normalizeInput('01.2023'));
        $this->assertEquals('2023-W05', TimeGranularity::Week->normalizeInput('2023-KW5'));
        $this->assertEquals('2023-W05', TimeGranularity::Week->normalizeInput('2023-KW05'));
        $this->assertEquals('2023-01-15', TimeGranularity::Day->normalizeInput('15.01.2023'));
        $this->assertEquals('2023-01-05', TimeGranularity::Day->normalizeInput('5.1.2023'));
    }

    public function test_normalize_input_returns_null_for_invalid(): void
    {
        $this->assertNull(TimeGranularity::Day->normalizeInput('1. Tag'));
        $this->assertNull(TimeGranularity::Month->normalizeInput('abc'));
    }

    // --- toDisplayFormat (ISO -> German) ---

    public function test_to_display_format_converts_iso_to_german(): void
    {
        $this->assertEquals('2023', TimeGranularity::Year->toDisplayFormat('2023'));
        $this->assertEquals('2023-Q1', TimeGranularity::Quarter->toDisplayFormat('2023-Q1'));
        $this->assertEquals('01.2023', TimeGranularity::Month->toDisplayFormat('2023-01'));
        $this->assertEquals('2023-KW5', TimeGranularity::Week->toDisplayFormat('2023-W05'));
        $this->assertEquals('15.01.2023', TimeGranularity::Day->toDisplayFormat('2023-01-15'));
    }

    // --- isValidPeriodKey (ISO format, internal) ---

    public function test_is_valid_period_key_accepts_iso(): void
    {
        $this->assertTrue(TimeGranularity::Year->isValidPeriodKey('2023'));
        $this->assertTrue(TimeGranularity::Quarter->isValidPeriodKey('2023-Q1'));
        $this->assertTrue(TimeGranularity::Month->isValidPeriodKey('2023-01'));
        $this->assertTrue(TimeGranularity::Week->isValidPeriodKey('2023-W05'));
        $this->assertTrue(TimeGranularity::Day->isValidPeriodKey('2023-01-15'));
    }

    // --- generateLabel with invalid input ---

    public function test_generate_label_returns_raw_value_for_invalid_format(): void
    {
        $this->assertEquals('1. Tag', TimeGranularity::Day->generateLabel('1. Tag'));
        $this->assertEquals('abc', TimeGranularity::Year->generateLabel('abc'));
        $this->assertEquals('Q1 2023', TimeGranularity::Quarter->generateLabel('Q1 2023'));
    }

    // --- trendLabel ---

    public function test_trend_label_de(): void
    {
        $this->assertEquals('Veränderung zum Vorjahr', TimeGranularity::Year->trendLabel('de'));
        $this->assertEquals('Veränderung zum Vorquartal', TimeGranularity::Quarter->trendLabel('de'));
        $this->assertEquals('Veränderung zum Vormonat', TimeGranularity::Month->trendLabel('de'));
        $this->assertEquals('Veränderung zur Vorwoche', TimeGranularity::Week->trendLabel('de'));
        $this->assertEquals('Veränderung zum Vortag', TimeGranularity::Day->trendLabel('de'));
    }

    public function test_trend_label_en(): void
    {
        $this->assertEquals('Change from previous year', TimeGranularity::Year->trendLabel('en'));
        $this->assertEquals('Change from previous quarter', TimeGranularity::Quarter->trendLabel('en'));
        $this->assertEquals('Change from previous month', TimeGranularity::Month->trendLabel('en'));
        $this->assertEquals('Change from previous week', TimeGranularity::Week->trendLabel('en'));
        $this->assertEquals('Change from previous day', TimeGranularity::Day->trendLabel('en'));
    }
}
