<?php

namespace App\Enums;

enum TimeGranularity: string
{
    case Year = 'year';
    case Quarter = 'quarter';
    case Month = 'month';
    case Week = 'week';
    case Day = 'day';

    // ─── Internal period_key validation (ISO format, stored in DB) ───

    /**
     * Regex pattern for basic structural validation of a stored period_key.
     * For Day/Week, use isValidPeriodKey() which adds calendar checks.
     */
    public function periodKeyPattern(): string
    {
        return match ($this) {
            self::Year => '/^\d{4}$/',
            self::Quarter => '/^\d{4}-Q[1-4]$/',
            self::Month => '/^\d{4}-(0[1-9]|1[0-2])$/',
            self::Week => '/^\d{4}-W(0[1-9]|[1-4]\d|5[0-3])$/',
            self::Day => '/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/',
        };
    }

    /**
     * Check if a period_key is valid, including calendar validation for days and weeks.
     */
    public function isValidPeriodKey(string $periodKey): bool
    {
        if (! preg_match($this->periodKeyPattern(), $periodKey)) {
            return false;
        }

        return match ($this) {
            self::Day => $this->isValidCalendarDate($periodKey),
            self::Week => $this->isValidIsoWeek($periodKey),
            default => true,
        };
    }

    // ─── User input (German format) ───

    /**
     * Regex pattern to validate user input (German format).
     */
    public function inputPattern(): string
    {
        return match ($this) {
            self::Year => '/^\d{4}$/',
            self::Quarter => '/^\d{4}-Q[1-4]$/',
            self::Month => '/^(0[1-9]|1[0-2])\.\d{4}$/',
            self::Week => '/^\d{4}-KW(0?[1-9]|[1-4]\d|5[0-3])$/',
            self::Day => '/^(0?[1-9]|[12]\d|3[01])\.(0?[1-9]|1[0-2])\.\d{4}$/',
        };
    }

    /**
     * Validate user input format, including calendar checks.
     */
    public function isValidInput(string $input): bool
    {
        if (! preg_match($this->inputPattern(), $input)) {
            return false;
        }

        // Calendar validation for day and week inputs
        return match ($this) {
            self::Day => $this->isValidCalendarDate($this->normalizeDayInput($input)),
            self::Week => $this->isValidIsoWeek($this->normalizeWeekInput($input)),
            default => true,
        };
    }

    /**
     * Convert user input (German format) to stored period_key (ISO format).
     * Returns null if input is invalid.
     */
    public function normalizeInput(string $input): ?string
    {
        if (! $this->isValidInput($input)) {
            return null;
        }

        return match ($this) {
            self::Year, self::Quarter => $input,
            self::Month => $this->normalizeMonthInput($input),
            self::Week => $this->normalizeWeekInput($input),
            self::Day => $this->normalizeDayInput($input),
        };
    }

    /**
     * Convert stored period_key (ISO) back to display format (German) for form fields.
     */
    public function toDisplayFormat(string $periodKey): string
    {
        if (! $this->isValidPeriodKey($periodKey)) {
            return $periodKey;
        }

        return match ($this) {
            self::Year, self::Quarter => $periodKey,
            self::Month => $this->periodKeyToMonthDisplay($periodKey),
            self::Week => $this->periodKeyToWeekDisplay($periodKey),
            self::Day => $this->periodKeyToDayDisplay($periodKey),
        };
    }

    /**
     * Placeholder for the input field.
     */
    public function inputPlaceholder(): string
    {
        return __("filament.resources.tile.period_placeholder_{$this->value}");
    }

    /**
     * Helper text explaining the expected input format.
     */
    public function inputHelperText(): string
    {
        return __("filament.resources.tile.period_helper_{$this->value}");
    }

    // ─── Labels (for frontend display) ───

    /**
     * Generate a human-readable label for a stored period_key.
     * Returns the raw period_key if the format doesn't match.
     */
    public function generateLabel(string $periodKey, string $locale = 'de'): string
    {
        if (! preg_match($this->periodKeyPattern(), $periodKey)) {
            return $periodKey;
        }

        return match ($this) {
            self::Year => $periodKey,
            self::Quarter => $this->formatQuarterLabel($periodKey),
            self::Month => $this->formatMonthLabel($periodKey, $locale),
            self::Week => $this->formatWeekLabel($periodKey, $locale),
            self::Day => $this->formatDayLabel($periodKey, $locale),
        };
    }

    /**
     * Calculate the period_key of the immediately preceding period.
     * Returns null if the input doesn't match the expected format.
     */
    public function previousPeriodKey(string $periodKey): ?string
    {
        if (! $this->isValidPeriodKey($periodKey)) {
            return null;
        }

        return match ($this) {
            self::Year => (string) ((int) $periodKey - 1),
            self::Quarter => $this->previousQuarter($periodKey),
            self::Month => $this->previousMonth($periodKey),
            self::Week => $this->previousWeek($periodKey),
            self::Day => $this->previousDay($periodKey),
        };
    }

    /**
     * Options array for Filament Select fields.
     */
    public static function filamentOptions(): array
    {
        return [
            'year' => __('filament.resources.tile.granularity_year'),
            'quarter' => __('filament.resources.tile.granularity_quarter'),
            'month' => __('filament.resources.tile.granularity_month'),
            'week' => __('filament.resources.tile.granularity_week'),
            'day' => __('filament.resources.tile.granularity_day'),
        ];
    }

    /**
     * Localized trend comparison label (e.g. "Veränderung zum Vorjahr").
     */
    public function trendLabel(string $locale = 'de'): string
    {
        if ($locale === 'en') {
            return match ($this) {
                self::Year => 'Change from previous year',
                self::Quarter => 'Change from previous quarter',
                self::Month => 'Change from previous month',
                self::Week => 'Change from previous week',
                self::Day => 'Change from previous day',
            };
        }

        return match ($this) {
            self::Year => 'Veränderung zum Vorjahr',
            self::Quarter => 'Veränderung zum Vorquartal',
            self::Month => 'Veränderung zum Vormonat',
            self::Week => 'Veränderung zur Vorwoche',
            self::Day => 'Veränderung zum Vortag',
        };
    }

    // ─── Calendar validation helpers ───

    private function isValidCalendarDate(string $isoDate): bool
    {
        $parts = explode('-', $isoDate);
        if (count($parts) !== 3) {
            return false;
        }

        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }

    private function isValidIsoWeek(string $isoWeek): bool
    {
        preg_match('/^(\d{4})-W(\d{2})$/', $isoWeek, $matches);
        if (! $matches) {
            return false;
        }

        $year = (int) $matches[1];
        $week = (int) $matches[2];

        // ISO 8601: a year has 52 or 53 weeks
        $maxWeek = (int) (new \DateTimeImmutable("{$year}-12-28"))->format('W');

        return $week >= 1 && $week <= $maxWeek;
    }

    // ─── Input normalization helpers ───

    private function normalizeWeekInput(string $input): string
    {
        // "2023-KW5" or "2023-KW05" -> "2023-W05"
        preg_match('/^(\d{4})-KW(0?\d{1,2})$/', $input, $matches);

        return $matches[1].'-W'.str_pad($matches[2], 2, '0', STR_PAD_LEFT);
    }

    private function normalizeMonthInput(string $input): string
    {
        // "01.2023" -> "2023-01"
        [$month, $year] = explode('.', $input);

        return $year.'-'.str_pad($month, 2, '0', STR_PAD_LEFT);
    }

    private function normalizeDayInput(string $input): string
    {
        // "15.01.2023" or "15.1.2023" -> "2023-01-15"
        [$day, $month, $year] = explode('.', $input);

        return $year.'-'.str_pad($month, 2, '0', STR_PAD_LEFT).'-'.str_pad($day, 2, '0', STR_PAD_LEFT);
    }

    // ─── Display format helpers (ISO -> German) ───

    private function periodKeyToWeekDisplay(string $periodKey): string
    {
        preg_match('/^(\d{4})-W(\d{2})$/', $periodKey, $matches);

        return $matches[1].'-KW'.((int) $matches[2]);
    }

    private function periodKeyToMonthDisplay(string $periodKey): string
    {
        [$year, $month] = explode('-', $periodKey);

        return "{$month}.{$year}";
    }

    private function periodKeyToDayDisplay(string $periodKey): string
    {
        [$year, $month, $day] = explode('-', $periodKey);

        return "{$day}.{$month}.{$year}";
    }

    // ─── Label formatting helpers ───

    private function formatQuarterLabel(string $periodKey): string
    {
        [$year, $quarter] = explode('-', $periodKey);

        return "{$quarter} {$year}";
    }

    private function formatMonthLabel(string $periodKey, string $locale): string
    {
        [$year, $month] = explode('-', $periodKey);
        $monthNames = $locale === 'en'
            ? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
            : ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

        return $monthNames[(int) $month - 1].' '.$year;
    }

    private function formatWeekLabel(string $periodKey, string $locale): string
    {
        preg_match('/^(\d{4})-W(\d{2})$/', $periodKey, $matches);
        $year = $matches[1];
        $week = (int) $matches[2];
        $prefix = $locale === 'en' ? 'W' : 'KW ';

        return "{$prefix}{$week} {$year}";
    }

    private function formatDayLabel(string $periodKey, string $locale): string
    {
        [$year, $month, $day] = explode('-', $periodKey);

        if ($locale === 'en') {
            return "{$day}/{$month}/{$year}";
        }

        return "{$day}.{$month}.{$year}";
    }

    // ─── Previous period helpers ───

    private function previousQuarter(string $periodKey): string
    {
        [$year, $quarterStr] = explode('-', $periodKey);
        $quarter = (int) substr($quarterStr, 1);

        if ($quarter === 1) {
            return ((int) $year - 1).'-Q4';
        }

        return $year.'-Q'.($quarter - 1);
    }

    private function previousMonth(string $periodKey): string
    {
        [$year, $month] = explode('-', $periodKey);
        $m = (int) $month;

        if ($m === 1) {
            return ((int) $year - 1).'-12';
        }

        return $year.'-'.str_pad($m - 1, 2, '0', STR_PAD_LEFT);
    }

    private function previousWeek(string $periodKey): string
    {
        // Use DateTimeImmutable for correct ISO week boundary handling
        preg_match('/^(\d{4})-W(\d{2})$/', $periodKey, $matches);
        $date = (new \DateTimeImmutable)->setISODate((int) $matches[1], (int) $matches[2]);
        $previous = $date->modify('-1 week');

        return $previous->format('o').'-W'.str_pad($previous->format('W'), 2, '0', STR_PAD_LEFT);
    }

    private function previousDay(string $periodKey): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $periodKey);
        if ($date === false) {
            return null;
        }

        return $date->modify('-1 day')->format('Y-m-d');
    }
}
