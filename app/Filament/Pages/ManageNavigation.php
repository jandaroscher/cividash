<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasBlockActiveToggleAction;
use App\Filament\Concerns\HasNavigationItemSchema;
use App\Models\Navigation;
use Filament\Actions\Action;
use Filament\Actions\LocaleSwitcher;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Resources\Concerns\Translatable;
use Illuminate\Support\Arr;

class ManageNavigation extends Page implements HasForms
{
    use HasBlockActiveToggleAction;
    use HasNavigationItemSchema;
    use HasUnsavedDataChangesAlert;
    use InteractsWithFormActions;
    use InteractsWithForms;
    use Translatable;

    public ?string $activeLocale = null;

    protected ?string $oldActiveLocale = null;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?int $navigationSort = 22;

    protected static string $view = 'filament.pages.manage-navigation';

    protected static ?string $slug = 'navigation';

    public static function getNavigationLabel(): string
    {
        return __('filament.pages.manage_header.title');
    }

    public function getTitle(): string
    {
        return __('filament.pages.manage_header.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.settings');
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->is_admin;
    }

    public ?array $data = [];

    public ?Navigation $record = null;

    public array $otherLocaleData = [];

    /**
     * Initialize the page: ensure a Navigation record exists, set the active locale if unset,
     * load locale-specific navigation items (supporting legacy and translatable structures),
     * apply pre-fill mutations, and populate the form with the prepared data.
     */
    public function mount(): void
    {
        $this->record = Navigation::getOrCreateInstance();

        // Initialize activeLocale if not set
        if (blank($this->activeLocale)) {
            $this->activeLocale = app()->getLocale();
        }

        $locale = $this->activeLocale;

        // Get base data without translatable attributes
        $data = $this->record->toArray();

        // Get navigation_items for the active locale using Spatie's getTranslation
        $navigationItems = $this->record->getTranslation('navigation_items', $locale, false);

        // Handle both old format (direct items array) and translatable format (with locale keys)
        if (is_string($navigationItems)) {
            $navigationItems = json_decode($navigationItems, true) ?: [];
        }
        if (is_array($navigationItems)) {
            // Check if it's the translatable structure (has locale keys like 'de', 'en')
            if (isset($navigationItems['de']) || isset($navigationItems['en'])) {
                $navigationItems = $navigationItems[$locale] ?? $navigationItems['de'] ?? [];
            }
        }

        $data['navigation_items'] = is_array($navigationItems) ? $this->filterValidRepeaterItems($navigationItems) : [];

        $data = $this->mutateFormDataBeforeFill($data);

        $this->form->fill($data);
    }

    /**
     * Builds the page form schema for editing navigation items, including a translatable repeater with nested children and related toggles.
     *
     * @param  Form  $form  The form to configure.
     * @return Form The configured form instance.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Repeater::make('navigation_items')
                    ->label(__('filament.pages.manage_header.navigation_items'))
                    ->schema([
                        ...$this->navigationItemSchema(),

                        Repeater::make('children')
                            ->label(__('filament.pages.manage_header.children'))
                            ->schema($this->navigationItemSchema())
                            ->visible(fn ($get) => $get('../../dropdown_enabled') === true)
                            ->collapsible()
                            ->itemLabel(function (array $state): ?string {
                                $locale = $this->activeLocale ?? app()->getLocale();

                                return is_array($state['label'] ?? null)
                                    ? ($state['label'][$locale] ?? $state['label']['de'] ?? '')
                                    : ($state['label'] ?? null);
                            })
                            ->reorderable()
                            ->extraItemActions([
                                static::getBlockActiveToggleAction(),
                            ]),
                    ])
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(function (array $state): ?string {
                        $locale = $this->activeLocale ?? app()->getLocale();

                        return is_array($state['label'] ?? null)
                            ? ($state['label'][$locale] ?? $state['label']['de'] ?? '')
                            : ($state['label'] ?? null);
                    })
                    ->addActionLabel(__('filament.actions.add'))
                    ->extraItemActions([
                        static::getBlockActiveToggleAction(),
                    ]),

                Toggle::make('show_language_switcher')
                    ->label(__('filament.pages.manage_header.show_language_switcher'))
                    ->default(true),

                Toggle::make('dropdown_enabled')
                    ->label(__('filament.pages.manage_header.dropdown_enabled'))
                    ->helperText(__('filament.pages.manage_header.dropdown_enabled_helper'))
                    ->default(false)
                    ->reactive(),
            ])
            ->statePath('data')
            ->model($this->record);
    }

    /**
     * Provide the form action definitions used by the page form.
     *
     * @return array An array containing the configured form actions (includes the "save" action).
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->submit('save'),
        ];
    }

    /**
     * Provide header actions for the page.
     *
     * @return array An array of header action instances for the page (e.g., a LocaleSwitcher).
     */
    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }

    /**
     * Persists the current form state to the bound navigation record and shows a success notification.
     *
     * Saves the form's state into the page's record and emits a localized success notification on completion.
     */
    public function save(): void
    {
        $data = $this->form->getState();

        $data = $this->mutateFormDataBeforeSave($data);

        $this->record->fill($data);
        $this->record->save();

        Notification::make()
            ->success()
            ->title(__('filament-panels::resources/pages/edit-record.notifications.saved.title'))
            ->send();
    }

    /**
     * Get the locales supported for translation.
     *
     * @return string[] Locale codes supported by the application, in preference order.
     */
    public static function getTranslatableLocales(): array
    {
        return ['de', 'en'];
    }

    /**
     * Returns the list of model attributes that should be treated as translatable.
     *
     * @return string[] List of attribute keys that are translatable.
     */
    protected function getTranslatableAttributes(): array
    {
        return ['navigation_items'];
    }

    /**
     * Convert any translatable navigation items in the provided form data to the representation for the current locale.
     *
     * The locale is taken from $this->activeLocale if set, otherwise from the application locale.
     *
     * @param  array  $data  Form data; may include a 'navigation_items' entry that is translatable.
     * @return array The form data with 'navigation_items' transformed to the current locale's structure when present.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $locale = $this->activeLocale ?? app()->getLocale();

        if (isset($data['navigation_items']) && is_array($data['navigation_items'])) {
            $data['navigation_items'] = $this->transformTranslatableRepeaterItems($data['navigation_items'], $locale);
        }

        return $data;
    }

    /**
     * Convert form state into a translatable structure for Save, localizing navigation items for the current locale.
     *
     * If the form contains a `navigation_items` repeater, this method converts its items into a per-locale structure keyed by the active locale
     * while preserving existing translations for other locales from the underlying record.
     *
     * @param  array  $data  The form state to mutate before persisting.
     * @return array The mutated form state with `navigation_items` transformed into a translatable format.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $locale = $this->activeLocale ?? app()->getLocale();

        $existingTranslations = [];
        foreach (static::getTranslatableLocales() as $existingLocale) {
            if ($existingLocale === $locale) {
                continue;
            }
            $pending = $this->otherLocaleData[$existingLocale]['navigation_items'] ?? null;
            if ($pending !== null) {
                $existingTranslations[$existingLocale] = $this->convertRepeaterItemsToTranslatable($pending, $existingLocale);
            } else {
                $navItems = $this->record->getTranslation('navigation_items', $existingLocale, false);
                $existingTranslations[$existingLocale] = is_array($navItems) ? $navItems : [];
            }
        }

        if (isset($data['navigation_items']) && is_array($data['navigation_items'])) {
            $data['navigation_items'] = $this->convertRepeaterItemsToTranslatable(
                $data['navigation_items'],
                $locale,
                $existingTranslations
            );
        }

        return $data;
    }

    /**
     * Convert repeater-form items into a translatable structure keyed by locale.
     *
     * Transforms each item so that `label` and `url` become arrays keyed by locale (ensuring the current
     * `$locale` is present), merges in any existing translations for the same item index across other locales,
     * and recursively applies the same conversion to `children`.
     *
     * @param  array  $items  The repeater items from the form (each item may contain `label`, `url`, and `children`).
     * @param  string  $locale  The locale currently being saved; used as the key for the item-level values.
     * @param  array  $existingTranslations  Optional existing translations organized as [locale => [index => item, ...], ...]
     *                                       used to merge existing `label`/`url` arrays for the same item indices.
     * @return array The items transformed into a translatable structure where `label` and `url` are arrays keyed by locale
     *               and `children` are likewise converted.
     */
    protected function convertRepeaterItemsToTranslatable(array $items, string $locale, array $existingTranslations = []): array
    {
        return array_map(function ($item, $index) use ($locale, $existingTranslations) {
            foreach ($existingTranslations as $existingLocale => $existingItems) {
                if (isset($existingItems[$index])) {
                    $existingItem = $existingItems[$index];

                    if (isset($existingItem['label']) && is_array($existingItem['label'])) {
                        if (! isset($item['label']) || ! is_array($item['label'])) {
                            $item['label'] = $existingItem['label'];
                        } else {
                            $item['label'] = array_merge($existingItem['label'], $item['label']);
                        }
                    }

                    if (isset($existingItem['url']) && is_array($existingItem['url'])) {
                        if (! isset($item['url']) || ! is_array($item['url'])) {
                            $item['url'] = $existingItem['url'];
                        } else {
                            $item['url'] = array_merge($existingItem['url'], $item['url']);
                        }
                    }
                }
            }

            if (isset($item['label']) && ! is_array($item['label'])) {
                $item['label'] = [$locale => (string) $item['label']];
            } elseif (isset($item['label']) && is_array($item['label'])) {
                $item['label'][$locale] = (string) ($item['label'][$locale] ?? '');
            }

            if (isset($item['url']) && ! is_array($item['url'])) {
                $item['url'] = [$locale => (string) $item['url']];
            } elseif (isset($item['url']) && is_array($item['url'])) {
                $item['url'][$locale] = (string) ($item['url'][$locale] ?? '');
            }

            if (isset($item['children']) && is_array($item['children'])) {
                $existingChildren = [];
                foreach ($existingTranslations as $existingLocale => $existingItems) {
                    if (isset($existingItems[$index]['children'])) {
                        $existingChildren[$existingLocale] = $existingItems[$index]['children'];
                    }
                }
                $item['children'] = $this->convertRepeaterItemsToTranslatable($item['children'], $locale, $existingChildren);
            }

            return $item;
        }, $items, array_keys($items));
    }

    /**
     * Convert a set of repeater items that use per-locale structures into items for a single locale.
     *
     * If an item's `label` or `url` is an array of locale keys, the value for `$locale` is chosen;
     * if that key is missing the method falls back to the 'de' value, then to an empty string.
     * Nested `children` arrays are processed recursively.
     *
     * @param  array  $items  Repeater items which may contain translatable `label` and `url` arrays and nested `children`.
     * @param  string  $locale  Locale code to resolve localized values (e.g., 'de', 'en').
     * @return array The input items with `label` and `url` converted to locale-specific strings and `children` transformed recursively.
     */
    protected function transformTranslatableRepeaterItems(array $items, string $locale): array
    {
        return array_map(function ($item) use ($locale) {
            if (isset($item['label']) && is_array($item['label'])) {
                $item['label'] = $item['label'][$locale] ?? $item['label']['de'] ?? '';
            }

            if (isset($item['url']) && is_array($item['url'])) {
                $item['url'] = $item['url'][$locale] ?? $item['url']['de'] ?? '';
            }

            if (isset($item['children']) && is_array($item['children'])) {
                $item['children'] = $this->transformTranslatableRepeaterItems($item['children'], $locale);
            }

            return $item;
        }, $items);
    }

    /**
     * Filter out non-array entries from repeater items to prevent phantom empty rows.
     */
    private function filterValidRepeaterItems(array $items): array
    {
        return array_values(array_filter($items, fn ($item) => is_array($item)));
    }

    /**
     * Preserve the currently selected locale by copying it to `$oldActiveLocale` before the active locale changes.
     */
    public function updatingActiveLocale(): void
    {
        $this->oldActiveLocale = $this->activeLocale;
    }

    /**
     * Handles switching the active locale: preserves translatable form state for the previous locale, loads or prepares translatable data for the newly selected locale, and fills the form with the combined non-translatable and locale-specific data.
     *
     * The method stores the current locale's translatable attributes into a temporary cache, attempts to restore translatable data for the newly selected locale from that cache (or loads it from the record if not cached), converts repeater-based translatable attributes into the form-ready structure for the active locale, fills the form with non-translatable values plus the prepared locale data, and clears any temporary cache for the active locale.
     */
    public function updatedActiveLocale(): void
    {
        if (blank($this->oldActiveLocale)) {
            return;
        }

        $this->resetValidation();

        $translatableAttributes = $this->getTranslatableAttributes();

        // Save current locale data
        $this->otherLocaleData[$this->oldActiveLocale] = Arr::only($this->data, $translatableAttributes);

        // Try to get data from otherLocaleData first, otherwise load from record
        $newLocaleData = $this->otherLocaleData[$this->activeLocale] ?? null;

        if ($newLocaleData === null) {
            // Load from database for the new locale
            $navigationItems = $this->record->getTranslation('navigation_items', $this->activeLocale, false);

            // Handle translatable structure (with locale keys)
            if (is_string($navigationItems)) {
                $navigationItems = json_decode($navigationItems, true) ?: [];
            }
            if (is_array($navigationItems)) {
                if (isset($navigationItems['de']) || isset($navigationItems['en'])) {
                    $navigationItems = $navigationItems[$this->activeLocale] ?? $navigationItems['de'] ?? [];
                }
            } else {
                $navigationItems = [];
            }

            $newLocaleData = [
                'navigation_items' => $this->transformTranslatableRepeaterItems(
                    $this->filterValidRepeaterItems($navigationItems),
                    $this->activeLocale
                ),
            ];
        } else {
            // Transform data from otherLocaleData
            if (isset($newLocaleData['navigation_items'])) {
                $newLocaleData['navigation_items'] = $this->transformTranslatableRepeaterItems(
                    $newLocaleData['navigation_items'],
                    $this->activeLocale
                );
            }
        }

        $this->form->fill([
            ...Arr::except($this->data, $translatableAttributes),
            ...$newLocaleData,
        ]);

        unset($this->otherLocaleData[$this->activeLocale]);
    }
}
