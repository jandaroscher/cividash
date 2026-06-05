<?php

namespace App\Services;

use App\Enums\TimeGranularity;
use App\Models\Tile;
use App\Models\TimePeriod;

class TimePeriodService
{
    /**
     * Resolve or create a TimePeriod for a tile, normalizing the input and generating the label.
     *
     * @param  string  $periodKey  The period key (may be in German input format or ISO format).
     * @param  Tile  $tile  The tile to associate with.
     * @return TimePeriod The resolved or created TimePeriod.
     */
    public function resolveOrCreate(string $periodKey, Tile $tile): TimePeriod
    {
        $granularity = TimeGranularity::tryFrom($tile->time_granularity ?? 'year') ?? TimeGranularity::Year;

        // Normalize input (German format → ISO) if needed
        $normalizedKey = $granularity->normalizeInput($periodKey) ?? $periodKey;

        return TimePeriod::firstOrCreate([
            'tile_id' => $tile->id,
            'period_key' => $normalizedKey,
        ], [
            'granularity' => $granularity->value,
            'label' => $granularity->generateLabel($normalizedKey),
            'tenant_id' => $tile->tenant_id,
        ]);
    }

    /**
     * Regenerate the label for a TimePeriod based on its granularity and period_key.
     */
    public function regenerateLabel(TimePeriod $timePeriod): string
    {
        $granularity = $timePeriod->granularity instanceof TimeGranularity
            ? $timePeriod->granularity
            : (TimeGranularity::tryFrom($timePeriod->granularity ?? 'year') ?? TimeGranularity::Year);

        return $granularity->generateLabel($timePeriod->period_key);
    }
}
