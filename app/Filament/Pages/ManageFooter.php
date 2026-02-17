<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasBlockActiveToggleAction;
use App\Models\FooterNavigation;
use App\Models\Page as PageModel;
use Filament\Actions\Action;
use Filament\Actions\LocaleSwitcher;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

class ManageFooter extends Page implements HasForms
{
    use HasBlockActiveToggleAction;
    use HasUnsavedDataChangesAlert;
    use InteractsWithFormActions;
    use InteractsWithForms;
    use Translatable;

    public ?string $activeLocale = null;

    protected ?string $oldActiveLocale = null;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 23;

    protected static string $view = 'filament.pages.manage-footer';

    protected static ?string $slug = 'footer';

    public static function getNavigationLabel(): string
    {
        return __('filament.pages.manage_footer.title');
    }

    public function getTitle(): string
    {
        return __('filament.pages.manage_footer.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.settings');
    }

    public ?array $data = [];

    public ?FooterNavigation $record = null;

    public array $otherLocaleData = [];

    /**
     * Prepare the page state and fill the form with data for the current editing locale.
     *
     * Loads or creates the FooterNavigation record, ensures an active locale is set,
     * extracts locale-specific values for translatable attributes (footer_navigation_items,
     * social_links, copyright_text), applies form pre-fill mutations, and fills the form state.
     */
    public function mount(): void
    {
        $this->record = FooterNavigation::getOrCreateInstance();

        // Initialize activeLocale if not set
        if (blank($this->activeLocale)) {
            $this->activeLocale = app()->getLocale();
        }

        $locale = $this->activeLocale;

        // Get base data without translatable attributes
        $data = $this->record->toArray();

        // Get translatable fields for the active locale using Spatie's getTranslation
        $footerItems = $this->record->getTranslation('footer_navigation_items', $locale, false);
        $socialLinks = $this->record->getTranslation('social_links', $locale, false);
        $copyrightText = $this->record->getTranslation('copyright_text', $locale, false);

        // Handle translatable structure (with locale keys) for footer_navigation_items
        if (is_string($footerItems)) {
            $footerItems = json_decode($footerItems, true) ?: [];
        }
        if (is_array($footerItems)) {
            if (isset($footerItems['de']) || isset($footerItems['en'])) {
                $footerItems = $footerItems[$locale] ?? $footerItems['de'] ?? [];
            }
        }
        $data['footer_navigation_items'] = is_array($footerItems) ? $this->filterValidRepeaterItems($footerItems) : [];

        // Handle translatable structure for social_links
        if (is_string($socialLinks)) {
            $socialLinks = json_decode($socialLinks, true) ?: [];
        }
        if (is_array($socialLinks)) {
            if (isset($socialLinks['de']) || isset($socialLinks['en'])) {
                $socialLinks = $socialLinks[$locale] ?? $socialLinks['de'] ?? [];
            }
        }
        $data['social_links'] = is_array($socialLinks) ? $this->filterValidSocialLinks($socialLinks) : [];

        // Handle translatable structure for copyright_text
        if (is_array($copyrightText)) {
            if (isset($copyrightText['de']) || isset($copyrightText['en'])) {
                $copyrightText = $copyrightText[$locale] ?? $copyrightText['de'] ?? '';
            }
        }
        $data['copyright_text'] = $copyrightText ?? '';

        $data = $this->mutateFormDataBeforeFill($data);

        $this->form->fill($data);
    }

    /**
     * Build the Filament form schema used by the ManageFooter page.
     *
     * Configures fields for footer navigation items, layout options, social links, and copyright text,
     * binds the form state to the `data` path, and attaches the page's record as the model.
     *
     * @param  Form  $form  The form instance to configure.
     * @return Form The configured form instance.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Repeater::make('footer_navigation_items')
                    ->label(__('filament.pages.manage_footer.footer_navigation_items'))
                    ->schema($this->getNavigationItemSchemaForForm())
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

                Select::make('layout_type')
                    ->label(__('filament.pages.manage_footer.layout_type'))
                    ->options([
                        'single-row' => __('filament.pages.manage_footer.layout_single_row'),
                        'multi-column' => __('filament.pages.manage_footer.layout_multi_column'),
                        'grid' => __('filament.pages.manage_footer.layout_grid'),
                    ])
                    ->default('single-row')
                    ->required()
                    ->reactive(),

                TextInput::make('columns')
                    ->label(__('filament.pages.manage_footer.columns'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12)
                    ->default(3)
                    ->required(fn ($get) => in_array($get('layout_type'), ['multi-column', 'grid']))
                    ->visible(fn ($get) => in_array($get('layout_type'), ['multi-column', 'grid'])),

                Toggle::make('social_links_enabled')
                    ->label(__('filament.pages.manage_footer.social_links_enabled'))
                    ->default(true),

                TextInput::make('copyright_text')
                    ->label(__('filament.pages.manage_footer.copyright_text'))
                    ->helperText(__('filament.pages.manage_footer.copyright_text_helper'))
                    ->placeholder('© {year} {site_name}')
                    ->nullable(),

                Repeater::make('social_links')
                    ->label(__('filament.pages.manage_footer.social_media_links'))
                    ->schema([
                        FileUpload::make('icon')
                            ->label(__('filament.pages.manage_footer.icon'))
                            ->image()
                            ->directory('footer-social-icons')
                            ->disk('public')
                            ->required(),
                        TextInput::make('link')
                            ->label(__('filament.pages.manage_footer.profile_url'))
                            ->url()
                            ->required(),
                        TextInput::make('title')
                            ->label(__('filament.pages.manage_footer.tooltip_text')),
                    ])
                    ->reorderable()
                    ->columns(3)
                    ->extraItemActions([
                        static::getBlockActiveToggleAction(),
                    ]),
            ])
            ->statePath('data')
            ->model($this->record);
    }

    /**
     * Build the Filament form schema for a single footer navigation item.
     *
     * The schema includes a `type` selector (page or manual), a `page_id` selector
     * that lists localized, public pages and auto-fills the item's `label` and
     * `url` when a page is selected, and `label`/`url` text inputs that are shown
     * and required only when the `type` is `manual`.
     *
     * @return array The Filament form fields schema for a navigation item.
     */
    protected function getNavigationItemSchemaForForm(): array
    {
        return [
            Select::make('type')
                ->label(__('filament.pages.manage_footer.item_type'))
                ->options([
                    'page' => __('filament.pages.manage_footer.type_page'),
                    'manual' => __('filament.pages.manage_footer.type_manual'),
                ])
                ->default('page')
                ->required()
                ->reactive(),

            Select::make('page_id')
                ->label(__('filament.pages.manage_footer.page'))
                ->options(function () {
                    $locale = $this->activeLocale ?? app()->getLocale();

                    return PageModel::query()
                        ->where('is_public', true)
                        ->get()
                        ->mapWithKeys(function ($page) use ($locale) {
                            $title = $page->getTranslation('title', $locale, false)
                                ?: $page->getTranslation('title', 'de', false)
                                ?: 'Untitled';

                            return [$page->id => $title];
                        })
                        ->toArray();
                })
                ->searchable()
                ->required(fn ($get) => $get('type') === 'page')
                ->visible(fn ($get) => $get('type') === 'page')
                ->reactive()
                ->afterStateUpdated(function ($state, $set, $get) {
                    if ($state && $get('type') === 'page') {
                        $page = PageModel::find($state);
                        if ($page) {
                            $locale = $this->activeLocale ?? app()->getLocale();

                            $title = $page->getTranslation('title', $locale, false)
                                ?: $page->getTranslation('title', 'de', false)
                                ?: 'Untitled';
                            $url = $page->getUrl(['locale' => $locale]);

                            $set('label', $title);
                            $set('url', $url);
                        }
                    }
                }),

            TextInput::make('label')
                ->label(__('filament.pages.manage_footer.label'))
                ->required(fn ($get) => $get('type') === 'manual')
                ->visible(fn ($get) => $get('type') === 'manual')
                ->reactive(),

            TextInput::make('url')
                ->label(__('filament.pages.manage_footer.url'))
                ->url()
                ->required(fn ($get) => $get('type') === 'manual')
                ->visible(fn ($get) => $get('type') === 'manual')
                ->reactive(),
        ];
    }

    /**
     * Provide the form actions available for the page's form.
     *
     * @return array An array of Filament Action instances representing the form's available actions.
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
     * @return array An array of header action components; includes a LocaleSwitcher action.
     */
    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }

    /**
     * Persist the current form state to the footer navigation record and notify the user of success.
     *
     * Prepares the form state for persistence, fills and saves the bound FooterNavigation model,
     * and dispatches a success notification when saving completes.
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
     * Locales supported for translations.
     *
     * @return string[] An array of locale codes supported by the page (e.g., 'de', 'en').
     */
    public static function getTranslatableLocales(): array
    {
        return ['de', 'en'];
    }

    /**
     * Lists model attributes that should be treated as translatable.
     *
     * @return string[] Array of attribute names that contain locale-keyed translations.
     */
    protected function getTranslatableAttributes(): array
    {
        return ['footer_navigation_items', 'social_links', 'copyright_text'];
    }

    /**
     * Prepare translatable attributes for the active locale so the form can display locale-specific values.
     *
     * Converts `footer_navigation_items`, `social_links` (titles), and `copyright_text` from
     * per-locale structures into values for the current editing locale (uses $this->activeLocale or app locale),
     * falling back to the 'de' locale when the active locale value is missing.
     *
     * @param  array  $data  The raw record data potentially containing locale-keyed attributes.
     * @return array The data array with translatable fields converted to locale-specific shapes for form filling.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $locale = $this->activeLocale ?? app()->getLocale();

        if (isset($data['footer_navigation_items']) && is_array($data['footer_navigation_items'])) {
            $data['footer_navigation_items'] = $this->transformTranslatableRepeaterItems($data['footer_navigation_items'], $locale);
        }

        if (isset($data['social_links']) && is_array($data['social_links'])) {
            $data['social_links'] = array_map(function ($link) use ($locale) {
                if (isset($link['title']) && is_array($link['title'])) {
                    $link['title'] = $link['title'][$locale] ?? $link['title']['de'] ?? '';
                }

                return $link;
            }, $data['social_links']);
        }

        if (isset($data['copyright_text']) && is_array($data['copyright_text'])) {
            $data['copyright_text'] = $data['copyright_text'][$locale] ?? $data['copyright_text']['de'] ?? '';
        }

        return $data;
    }

    /**
     * Prepare form state for persistence by merging the current locale's values into
     * locale-keyed translatable structures and preserving translations for other locales.
     *
     * Transforms:
     * - `footer_navigation_items` into a locale-keyed repeater structure, preserving existing per-locale items.
     * - `social_links` so each item's `title` is an array keyed by locale and merged with existing translations.
     * - `copyright_text` into an array keyed by locale and merged with existing translations.
     *
     * @param  array  $data  Form state to mutate before saving; may contain `footer_navigation_items`, `social_links`, and `copyright_text`.
     * @return array The mutated form state with translatable attributes prepared for persistence.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $locale = $this->activeLocale ?? app()->getLocale();

        $existingTranslations = [];
        foreach (['de', 'en'] as $existingLocale) {
            if ($existingLocale !== $locale) {
                // Check for pending in-session edits first
                $pending = $this->otherLocaleData[$existingLocale] ?? null;
                if ($pending !== null) {
                    $existingTranslations[$existingLocale] = $pending;
                } else {
                    $footerNav = $this->record->getTranslation('footer_navigation_items', $existingLocale, false);
                    $social = $this->record->getTranslation('social_links', $existingLocale, false);
                    $existingTranslations[$existingLocale] = [
                        'footer_navigation_items' => is_array($footerNav) ? $footerNav : [],
                        'social_links' => is_array($social) ? $social : [],
                        'copyright_text' => $this->record->getTranslation('copyright_text', $existingLocale, false),
                    ];
                }
            }
        }

        if (isset($data['footer_navigation_items']) && is_array($data['footer_navigation_items'])) {
            $existingItems = [];
            foreach ($existingTranslations as $existingLocale => $existingData) {
                $existingItems[$existingLocale] = $existingData['footer_navigation_items'] ?? [];
            }
            $data['footer_navigation_items'] = $this->convertRepeaterItemsToTranslatable(
                $data['footer_navigation_items'],
                $locale,
                $existingItems
            );
        }

        if (isset($data['social_links']) && is_array($data['social_links'])) {
            $existingSocialLinks = [];
            foreach ($existingTranslations as $existingLocale => $existingData) {
                $existingSocialLinks[$existingLocale] = $existingData['social_links'] ?? [];
            }
            $data['social_links'] = array_map(function ($link, $index) use ($locale, $existingSocialLinks) {
                foreach ($existingSocialLinks as $existingLocale => $existingLinks) {
                    if (isset($existingLinks[$index]['title']) && is_array($existingLinks[$index]['title'])) {
                        if (! isset($link['title']) || ! is_array($link['title'])) {
                            $link['title'] = $existingLinks[$index]['title'];
                        } else {
                            $link['title'] = array_merge($existingLinks[$index]['title'], $link['title']);
                        }
                    }
                }

                if (isset($link['title']) && ! is_array($link['title'])) {
                    $link['title'] = [$locale => (string) $link['title']];
                } elseif (isset($link['title']) && is_array($link['title'])) {
                    $link['title'][$locale] = (string) ($link['title'][$locale] ?? '');
                }

                return $link;
            }, $data['social_links'], array_keys($data['social_links']));
        }

        $existingCopyrightText = [];
        foreach ($existingTranslations as $existingLocale => $existingData) {
            if (isset($existingData['copyright_text'])) {
                if (is_array($existingData['copyright_text'])) {
                    $existingCopyrightText = array_merge($existingCopyrightText, $existingData['copyright_text']);
                } else {
                    $existingCopyrightText[$existingLocale] = $existingData['copyright_text'];
                }
            }
        }

        if (isset($data['copyright_text'])) {
            if (! is_array($data['copyright_text'])) {
                $data['copyright_text'] = array_merge($existingCopyrightText ?? [], [$locale => (string) $data['copyright_text']]);
            } elseif (is_array($data['copyright_text'])) {
                $data['copyright_text'] = array_merge($existingCopyrightText ?? [], $data['copyright_text']);
                $data['copyright_text'][$locale] = (string) ($data['copyright_text'][$locale] ?? '');
            }
        }

        return $data;
    }

    /**
     * Convert a set of repeater items into a locale-keyed translatable structure.
     *
     * Merges any provided existing per-locale translations for the same item indices, ensures
     * `label` and `url` fields are arrays keyed by locale, sets the current locale value for
     * those fields, and recursively applies the same transformation to nested `children`.
     *
     * @param  array  $items  The repeater items to convert.
     * @param  string  $locale  The target locale key to assign values under.
     * @param  array  $existingTranslations  Optional existing translations organized by locale and item index.
     * @return array The transformed repeater items with `label` and `url` as locale-keyed arrays and translated children.
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
     * Convert repeater items whose `label` and `url` are locale-keyed arrays into locale-specific strings and recurse into children.
     *
     * This replaces `label` and `url` values that are arrays with the entry for the given `$locale` (falling back to `'de'` then to an empty string) and applies the same transformation to any nested `children`.
     *
     * @param  array  $items  Repeater items potentially containing locale-keyed `label` and `url` values and nested `children`.
     * @param  string  $locale  The target locale to extract from locale-keyed values.
     * @return array The transformed repeater items with `label` and `url` as strings for the specified locale and `children` recursively transformed.
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
     * Filter out non-array entries from social links to prevent phantom empty rows.
     */
    private function filterValidSocialLinks(array $links): array
    {
        return array_values(array_filter($links, fn ($link) => is_array($link)));
    }

    /**
     * Remember the current active locale before it changes.
     *
     * Sets {@see $oldActiveLocale} to the current {@see $activeLocale} so the previous locale is available during locale switching.
     */
    public function updatingActiveLocale(): void
    {
        $this->oldActiveLocale = $this->activeLocale;
    }

    /**
     * Handle active locale changes by preserving the current locale's translatable data and loading the form state for the newly selected locale.
     *
     * Saves the translatable attributes of the previously active locale into the local cache, then attempts to restore the new locale's data from that cache or falls back to the persisted record. Translatable repeater items, social link titles, and copyright text are transformed into the shape expected by the form before the form is filled.
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
            $footerItems = $this->record->getTranslation('footer_navigation_items', $this->activeLocale, false);
            $socialLinks = $this->record->getTranslation('social_links', $this->activeLocale, false);
            $copyrightText = $this->record->getTranslation('copyright_text', $this->activeLocale, false);

            // Handle translatable structure (with locale keys) for footer_navigation_items
            if (is_string($footerItems)) {
                $footerItems = json_decode($footerItems, true) ?: [];
            }
            if (is_array($footerItems)) {
                if (isset($footerItems['de']) || isset($footerItems['en'])) {
                    $footerItems = $footerItems[$this->activeLocale] ?? $footerItems['de'] ?? [];
                }
            } else {
                $footerItems = [];
            }

            // Handle translatable structure for social_links
            if (is_string($socialLinks)) {
                $decoded = json_decode($socialLinks, true);
                $socialLinks = is_array($decoded) ? $decoded : [];
            }
            if (is_array($socialLinks)) {
                if (isset($socialLinks['de']) || isset($socialLinks['en'])) {
                    $socialLinks = $socialLinks[$this->activeLocale] ?? $socialLinks['de'] ?? [];
                }
            } else {
                $socialLinks = [];
            }

            // Handle translatable structure for copyright_text
            if (is_array($copyrightText)) {
                if (isset($copyrightText['de']) || isset($copyrightText['en'])) {
                    $copyrightText = $copyrightText[$this->activeLocale] ?? $copyrightText['de'] ?? '';
                }
            }

            $newLocaleData = [
                'footer_navigation_items' => $this->transformTranslatableRepeaterItems(
                    $this->filterValidRepeaterItems($footerItems ?? []),
                    $this->activeLocale
                ),
                'social_links' => array_map(function ($link) {
                    if (isset($link['title']) && is_array($link['title'])) {
                        $link['title'] = $link['title'][$this->activeLocale] ?? $link['title']['de'] ?? '';
                    }

                    return $link;
                }, $this->filterValidSocialLinks($socialLinks ?? [])),
                'copyright_text' => $copyrightText ?? '',
            ];
        } else {
            // Transform data from otherLocaleData
            if (isset($newLocaleData['footer_navigation_items'])) {
                $newLocaleData['footer_navigation_items'] = $this->transformTranslatableRepeaterItems(
                    $newLocaleData['footer_navigation_items'],
                    $this->activeLocale
                );
            }

            if (isset($newLocaleData['social_links'])) {
                $newLocaleData['social_links'] = array_map(function ($link) {
                    if (isset($link['title']) && is_array($link['title'])) {
                        $link['title'] = $link['title'][$this->activeLocale] ?? $link['title']['de'] ?? '';
                    }

                    return $link;
                }, $newLocaleData['social_links']);
            }

            if (isset($newLocaleData['copyright_text']) && is_array($newLocaleData['copyright_text'])) {
                $newLocaleData['copyright_text'] = $newLocaleData['copyright_text'][$this->activeLocale] ?? $newLocaleData['copyright_text']['de'] ?? '';
            }
        }

        $this->form->fill([
            ...Arr::except($this->data, $translatableAttributes),
            ...$newLocaleData,
        ]);

        unset($this->otherLocaleData[$this->activeLocale]);
    }
}
