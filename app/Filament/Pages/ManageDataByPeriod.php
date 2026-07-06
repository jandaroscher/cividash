<?php

namespace App\Filament\Pages;

use App\Enums\TimeGranularity;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Services\MetricValueBulkService;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ManageDataByPeriod extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.manage-data-by-period';

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?int $navigationSort = 3;

    /** Currently selected granularity (string value of TimeGranularity enum) */
    public string $selectedGranularity = 'year';

    /** Currently selected period_key */
    public ?string $selectedPeriodKey = null;

    /** Live search term filtering the tile matrix by tile title or metric label */
    public string $search = '';

    /** Form state: keyed by "tile_{id}.metric_{defId}" — selectors are NOT stored here */
    public array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.data');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.pages.manage_data_by_period.navigation_label');
    }

    public function getTitle(): string
    {
        return __('filament.pages.manage_data_by_period.title');
    }

    public function mount(): void
    {
        // Default granularity: year
        $this->selectedGranularity = 'year';

        // Default period_key: most recent for this granularity
        $this->selectedPeriodKey = $this->getDistinctPeriodKeys()->first();

        $this->fillFormFromDatabase();
        $this->form->fill($this->data);
    }

    /**
     * Livewire lifecycle hook: fires when $selectedGranularity changes via wire:model.live.
     * Kept outside the Filament form to prevent updatedInteractsWithForms from triggering
     * fillFormFromDatabase() when tile metric inputs change.
     */
    public function updatedSelectedGranularity(string $value): void
    {
        $this->selectedGranularity = $value;
        $this->selectedPeriodKey = $this->getDistinctPeriodKeys()->first();
        $this->fillFormFromDatabase();
        $this->form->fill($this->data);
    }

    /**
     * Livewire lifecycle hook: fires when $selectedPeriodKey changes via wire:model.live.
     */
    public function updatedSelectedPeriodKey(?string $value): void
    {
        $this->selectedPeriodKey = $value;
        $this->fillFormFromDatabase();
        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema($this->buildTileMatrixSchema());
    }

    /**
     * Build only the tile matrix — selectors live outside the Filament form.
     */
    protected function buildTileMatrixSchema(): array
    {
        if ($this->selectedPeriodKey === null) {
            return [];
        }

        $tiles = $this->loadTilesWithData();
        $schema = [];

        foreach ($tiles as $tile) {
            $section = $this->buildTileSection($tile);

            if ($section !== null) {
                $schema[] = $section;
            }
        }

        return $schema;
    }

    /**
     * Build a collapsible Section for a single tile with one TextInput per active MetricDefinition.
     */
    private function buildTileSection(Tile $tile): ?Section
    {
        $defs = $tile->metricDefinitions->where('is_active', true);

        if ($defs->isEmpty()) {
            return null;
        }

        // Determine if a TimePeriod for this tile + period key already exists
        $timePeriodExists = $tile->timePeriods
            ->where('period_key', $this->selectedPeriodKey)
            ->isNotEmpty();

        $isNew = ! $timePeriodExists;

        $inputs = [];

        foreach ($defs as $def) {
            $unit = $def->getTranslation('unit', app()->getLocale(), false)
                ?: $def->getTranslation('unit', 'de', false);

            $label = $def->getTranslation('label', app()->getLocale(), false)
                ?: $def->getTranslation('label', 'de', false);

            $inputs[] = TextInput::make("tile_{$tile->id}.metric_{$def->id}")
                ->label((string) $label)
                ->numeric()
                ->suffix($unit ?: null)
                ->placeholder('–');
        }

        $heading = $tile->getTranslation('title', app()->getLocale(), false)
            ?: $tile->getTranslation('title', 'de', false);

        $description = $isNew ? __('filament.pages.manage_data_by_period.badge_new') : null;

        return Section::make($heading)
            ->description($description)
            ->schema($inputs)
            ->collapsible()
            ->collapsed(false)
            ->visible(fn (): bool => $this->tileMatchesSearch($tile))
            // Filament's grid wraps every top-level component in a column div
            // that collapses to nothing when hidden (no leftover gap), but by
            // default a hidden component is also dropped from dehydration.
            // dehydratedWhenHidden() keeps its fields in $state regardless, so
            // an in-progress edit isn't lost the moment a search term hides it.
            ->dehydratedWhenHidden();
    }

    /**
     * Whether a tile should be visible for the current search term — matches
     * against the tile title or any of its active metric definitions' labels.
     */
    private function tileMatchesSearch(Tile $tile): bool
    {
        $needle = mb_strtolower(trim($this->search));

        if ($needle === '') {
            return true;
        }

        $title = mb_strtolower((string) ($tile->getTranslation('title', app()->getLocale(), false)
            ?: $tile->getTranslation('title', 'de', false)));

        if (str_contains($title, $needle)) {
            return true;
        }

        foreach ($tile->metricDefinitions as $def) {
            $label = mb_strtolower((string) ($def->getTranslation('label', app()->getLocale(), false)
                ?: $def->getTranslation('label', 'de', false)));

            if (str_contains($label, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether an active search term matched none of the currently loaded tiles.
     * Exposed as public for blade access.
     */
    public function searchHasNoMatches(): bool
    {
        if (trim($this->search) === '') {
            return false;
        }

        return $this->loadTilesWithData()
            ->every(fn (Tile $tile): bool => ! $this->tileMatchesSearch($tile));
    }

    /**
     * Fill $this->data from existing MetricValues for the selected period.
     * Only tile metric fields — selectors are NOT included in $this->data.
     */
    private function fillFormFromDatabase(): void
    {
        if ($this->selectedPeriodKey === null) {
            $this->data = [];

            return;
        }

        $newData = [];
        $tiles = $this->loadTilesWithData();

        foreach ($tiles as $tile) {
            // Index MetricValues by metric_definition_id for O(1) lookup
            $valueMap = collect();

            foreach ($tile->timePeriods as $tp) {
                foreach ($tp->metricValues as $mv) {
                    $valueMap->put($mv->metric_definition_id, $mv->value);
                }
            }

            foreach ($tile->metricDefinitions as $def) {
                $rawValue = $valueMap->get($def->id);
                // Store as string or null (empty = no existing value)
                $newData["tile_{$tile->id}"]["metric_{$def->id}"] = $rawValue !== null
                    ? (string) $rawValue
                    : null;
            }
        }

        $this->data = $newData;
    }

    /**
     * Load tiles with constrained eager-loading for the current period.
     *
     * 3 queries total: tiles, metricDefinitions, timePeriods (+metricValues).
     *
     * @return Collection<int, Tile>
     */
    private function loadTilesWithData(): Collection
    {
        $periodKey = $this->selectedPeriodKey;
        $granularity = $this->selectedGranularity;

        return Tile::query()
            ->where('time_granularity', $granularity)
            ->with([
                'metricDefinitions' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'timePeriods' => fn ($q) => $q->where('period_key', $periodKey)->forGranularity($granularity),
                'timePeriods.metricValues',
            ])
            ->orderBy('position')
            ->get();
    }

    /**
     * Return distinct period_keys for the selected granularity (newest first).
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function getDistinctPeriodKeys(): \Illuminate\Support\Collection
    {
        return TimePeriod::query()
            ->where('granularity', $this->selectedGranularity)
            ->distinct()
            ->orderByDesc('period_key')
            ->pluck('period_key');
    }

    /**
     * Period key options for the native select in the blade view.
     * Exposed as public for blade and test access.
     *
     * @return array<string, string>
     */
    public function getPeriodKeyOptionsPublic(): array
    {
        $granularity = TimeGranularity::tryFrom($this->selectedGranularity) ?? TimeGranularity::Year;

        return $this->getDistinctPeriodKeys()
            ->mapWithKeys(fn (string $key) => [$key => $granularity->generateLabel($key)])
            ->all();
    }

    /**
     * Granularity options filtered to only those with existing tiles.
     * Exposed as public for blade access.
     *
     * @return array<string, string>
     */
    public function getGranularityOptionsPublic(): array
    {
        $existingGranularities = Tile::query()
            ->whereNotNull('time_granularity')
            ->distinct()
            ->pluck('time_granularity')
            ->all();

        return collect(TimeGranularity::filamentOptions())
            ->filter(fn ($label, $value) => in_array($value, $existingGranularities, true))
            ->all();
    }

    /**
     * Header action: Save button with Mod+S keybinding.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('filament.pages.manage_data_by_period.action_save'))
                ->icon('heroicon-o-check')
                ->keyBindings(['mod+s'])
                ->action('save'),
        ];
    }

    /**
     * Persist the current form state to the database.
     */
    public function save(): void
    {
        if ($this->selectedPeriodKey === null) {
            Notification::make()
                ->title(__('filament.pages.manage_data_by_period.notification_no_period'))
                ->warning()
                ->send();

            return;
        }

        $state = $this->form->getState();

        $tiles = $this->loadTilesWithData();

        try {
            app(MetricValueBulkService::class)->save(
                $state,
                $this->selectedPeriodKey,
                $tiles
            );
        } catch (\Throwable $e) {
            Log::error('ManageDataByPeriod save failed', ['error' => $e->getMessage()]);

            Notification::make()
                ->title(__('filament.pages.manage_data_by_period.notification_error'))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('filament.pages.manage_data_by_period.notification_saved'))
            ->success()
            ->send();

        // Refresh form to pick up newly created TimePeriods
        $this->fillFormFromDatabase();
        $this->form->fill($this->data);
    }
}
