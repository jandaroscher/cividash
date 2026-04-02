<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasBlockActiveToggleAction;
use App\Filament\Concerns\HasNavigationItemSchema;
use App\Models\FooterNavigation;
use App\Models\Navigation;
use App\Models\Page as PageModel;
use App\Settings\GeneralSettings;
use Filament\Actions\Action;
use Filament\Actions\LocaleSwitcher;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
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
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;

class ManageSiteSettings extends Page implements HasForms
{
    use HasBlockActiveToggleAction;
    use HasNavigationItemSchema;
    use HasUnsavedDataChangesAlert;
    use InteractsWithFormActions;
    use InteractsWithForms;
    use Translatable;

    public ?string $activeLocale = null;

    protected ?string $oldActiveLocale = null;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.manage-site-settings';

    protected static ?string $slug = 'site-settings';

    public ?array $data = [];

    public ?Navigation $navigationRecord = null;

    public ?FooterNavigation $footerRecord = null;

    public array $otherLocaleData = [];

    public function getTitle(): string|Htmlable
    {
        return __('filament.pages.manage_site_settings.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.pages.manage_site_settings.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.settings');
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->is_admin;
    }

    public function mount(): void
    {
        $settings = app(GeneralSettings::class);

        $this->navigationRecord = Navigation::getOrCreateInstance();
        $this->footerRecord = FooterNavigation::getOrCreateInstance();

        if (blank($this->activeLocale)) {
            $this->activeLocale = app()->getLocale();
        }

        $locale = $this->activeLocale;

        // General settings (non-translatable)
        $data = [
            'site_name' => $settings->site_name,
            'english_translation_active' => $settings->english_translation_active,
        ];

        // Navigation data
        $navigationItems = $this->extractTranslatableValue(
            $this->navigationRecord->getTranslation('navigation_items', $locale, false),
            $locale
        );
        $data['navigation_items'] = is_array($navigationItems) ? $this->filterValidRepeaterItems($navigationItems) : [];
        // Footer data
        $footerItems = $this->extractTranslatableValue(
            $this->footerRecord->getTranslation('footer_navigation_items', $locale, false),
            $locale
        );
        $data['footer_navigation_items'] = is_array($footerItems) ? $this->filterValidRepeaterItems($footerItems) : [];

        $socialLinks = $this->extractTranslatableValue(
            $this->footerRecord->getTranslation('social_links', $locale, false),
            $locale
        );
        $data['social_links'] = is_array($socialLinks) ? $this->filterValidSocialLinks($socialLinks) : [];

        $copyrightText = $this->extractTranslatableValue(
            $this->footerRecord->getTranslation('copyright_text', $locale, false),
            $locale,
            ''
        );
        $data['copyright_text'] = $copyrightText ?? '';

        $sponsors = $this->extractTranslatableValue(
            $this->footerRecord->getTranslation('sponsors', $locale, false),
            $locale
        );
        $data['sponsors'] = is_array($sponsors) ? $this->filterValidRepeaterItems($sponsors) : [];

        $data['columns'] = $this->footerRecord->layout_type === 'single-row'
            ? 1
            : ($this->footerRecord->columns ?? 1);
        $data['social_links_enabled'] = $this->footerRecord->social_links_enabled;

        $data = $this->mutateFormDataBeforeFill($data);

        $this->form->fill($data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('filament.pages.manage_site_settings.section_basic'))
                    ->schema([
                        TextInput::make('site_name')
                            ->label(__('filament.pages.manage_general.site_name'))
                            ->required(),
                        Toggle::make('english_translation_active')
                            ->label(__('filament.pages.manage_site_settings.english_translation_active'))
                            ->helperText(__('filament.pages.manage_site_settings.english_translation_active_helper'))
                            ->default(true),
                    ]),

                Section::make(__('filament.pages.manage_site_settings.section_header'))
                    ->schema([
                        Repeater::make('navigation_items')
                            ->label(__('filament.pages.manage_header.navigation_items'))
                            ->schema([
                                ...$this->navigationItemSchema(),

                                Repeater::make('children')
                                    ->label(__('filament.pages.manage_header.children'))
                                    ->addActionLabel(__('filament.actions.add_to_children'))
                                    ->schema($this->navigationItemSchema())
                                    ->defaultItems(0)
                                    ->collapsible()
                                    ->collapsed()
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
                            ->collapsed()
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

                    ]),

                Section::make(__('filament.pages.manage_site_settings.section_footer'))
                    ->schema([
                        Section::make(__('filament.pages.manage_site_settings.section_footer_navigation'))
                            ->schema([
                                Repeater::make('footer_navigation_items')
                                    ->label(__('filament.pages.manage_footer.footer_navigation_items'))
                                    ->schema($this->getFooterNavigationItemSchema())
                                    ->reorderable()
                                    ->collapsible()
                                    ->collapsed()
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

                                Select::make('columns')
                                    ->label(__('filament.pages.manage_footer.column_count'))
                                    ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'])
                                    ->default(1)
                                    ->required(),
                            ]),

                        Section::make(__('filament.pages.manage_site_settings.section_social_media'))
                            ->schema([
                                Toggle::make('social_links_enabled')
                                    ->label(__('filament.pages.manage_footer.social_links_enabled'))
                                    ->default(true),

                                Repeater::make('social_links')
                                    ->label(__('filament.pages.manage_footer.social_media_links'))
                                    ->addActionLabel(__('filament.actions.add_to_social_media_links'))
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
                                    ->collapsible()
                                    ->collapsed()
                                    ->itemLabel(fn (array $state): ?string => $state['link'] ?? null)
                                    ->extraItemActions([
                                        static::getBlockActiveToggleAction(),
                                    ]),
                            ]),

                        Section::make(__('filament.pages.manage_site_settings.section_sponsors'))
                            ->schema([
                                Repeater::make('sponsors')
                                    ->label(__('filament.pages.manage_footer.sponsors'))
                                    ->addActionLabel(__('filament.actions.add_to_sponsors'))
                                    ->schema([
                                        FileUpload::make('image')
                                            ->label(__('filament.pages.manage_footer.sponsor_image'))
                                            ->image()
                                            ->directory('footer-sponsors')
                                            ->disk('public')
                                            ->required(),
                                        TextInput::make('url')
                                            ->label(__('filament.pages.manage_footer.sponsor_url'))
                                            ->url()
                                            ->nullable(),
                                        TextInput::make('name')
                                            ->label(__('filament.pages.manage_footer.sponsor_name'))
                                            ->nullable(),
                                    ])
                                    ->reorderable()
                                    ->collapsible()
                                    ->collapsed()
                                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['url'] ?? null),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFooterNavigationItemSchema(): array
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
                ->live(),

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
                ->live()
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
                ->live(),

            TextInput::make('url')
                ->label(__('filament.pages.manage_footer.url'))
                ->url()
                ->required(fn ($get) => $get('type') === 'manual')
                ->visible(fn ($get) => $get('type') === 'manual')
                ->live(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->submit('save'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            Action::make('saveFromHeader')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->submit('form')
                ->formId('form'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data = $this->mutateFormDataBeforeSave($data);

        // Save GeneralSettings
        $settings = app(GeneralSettings::class);
        $settings->site_name = $data['site_name'];
        $settings->english_translation_active = $data['english_translation_active'] ?? true;
        $settings->save();

        // Save Navigation
        $this->navigationRecord->fill([
            'navigation_items' => $data['navigation_items'],
            'dropdown_enabled' => true, // Always true — children always visible
        ]);
        $this->navigationRecord->save();

        // Save FooterNavigation
        $this->footerRecord->fill([
            'footer_navigation_items' => $data['footer_navigation_items'],
            'social_links' => $data['social_links'] ?? [],
            'layout_type' => 'multi-column',
            'columns' => $data['columns'] ?? 4,
            'social_links_enabled' => $data['social_links_enabled'] ?? true,
            'sponsors' => $data['sponsors'] ?? [],
        ]);
        $this->footerRecord->save();

        Notification::make()
            ->success()
            ->title(__('filament-panels::resources/pages/edit-record.notifications.saved.title'))
            ->send();
    }

    public static function getTranslatableLocales(): array
    {
        return ['de', 'en'];
    }

    protected function getTranslatableAttributes(): array
    {
        return ['navigation_items', 'footer_navigation_items', 'social_links', 'copyright_text', 'sponsors'];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $locale = $this->activeLocale ?? app()->getLocale();

        if (isset($data['navigation_items']) && is_array($data['navigation_items'])) {
            $data['navigation_items'] = $this->transformTranslatableRepeaterItems($data['navigation_items'], $locale);
        }

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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $locale = $this->activeLocale ?? app()->getLocale();

        // Build existing translations for navigation_items
        $existingNavTranslations = [];
        foreach (static::getTranslatableLocales() as $existingLocale) {
            if ($existingLocale === $locale) {
                continue;
            }
            $pending = $this->otherLocaleData[$existingLocale]['navigation_items'] ?? null;
            if ($pending !== null) {
                $existingNavTranslations[$existingLocale] = $this->convertRepeaterItemsToTranslatable($pending, $existingLocale);
            } else {
                $navItems = $this->navigationRecord->getTranslation('navigation_items', $existingLocale, false);
                $existingNavTranslations[$existingLocale] = is_array($navItems) ? $navItems : [];
            }
        }

        if (isset($data['navigation_items']) && is_array($data['navigation_items'])) {
            $data['navigation_items'] = $this->convertRepeaterItemsToTranslatable(
                $data['navigation_items'],
                $locale,
                $existingNavTranslations
            );
        }

        // Build existing translations for footer fields
        $existingFooterTranslations = [];
        foreach (['de', 'en'] as $existingLocale) {
            if ($existingLocale !== $locale) {
                $pending = $this->otherLocaleData[$existingLocale] ?? null;
                if ($pending !== null) {
                    $existingFooterTranslations[$existingLocale] = $pending;
                } else {
                    $footerNav = $this->footerRecord->getTranslation('footer_navigation_items', $existingLocale, false);
                    $social = $this->footerRecord->getTranslation('social_links', $existingLocale, false);
                    $sponsorsVal = $this->footerRecord->getTranslation('sponsors', $existingLocale, false);
                    $existingFooterTranslations[$existingLocale] = [
                        'footer_navigation_items' => is_array($footerNav) ? $footerNav : [],
                        'social_links' => is_array($social) ? $social : [],
                        'copyright_text' => $this->footerRecord->getTranslation('copyright_text', $existingLocale, false),
                        'sponsors' => is_array($sponsorsVal) ? $sponsorsVal : [],
                    ];
                }
            }
        }

        if (isset($data['footer_navigation_items']) && is_array($data['footer_navigation_items'])) {
            $existingItems = [];
            foreach ($existingFooterTranslations as $existingLocale => $existingData) {
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
            foreach ($existingFooterTranslations as $existingLocale => $existingData) {
                $existingSocialLinks[$existingLocale] = $existingData['social_links'] ?? [];
            }
            $data['social_links'] = array_map(function ($link) use ($locale, $existingSocialLinks) {
                $key = ($link['icon'] ?? '').'|'.($link['link'] ?? '');

                foreach ($existingSocialLinks as $existingLocale => $existingLinks) {
                    foreach ($existingLinks as $existingLink) {
                        $existingKey = ($existingLink['icon'] ?? '').'|'.($existingLink['link'] ?? '');
                        if ($existingKey === $key && isset($existingLink['title']) && is_array($existingLink['title'])) {
                            if (! isset($link['title']) || ! is_array($link['title'])) {
                                $link['title'] = $existingLink['title'];
                            } else {
                                $link['title'] = array_merge($existingLink['title'], $link['title']);
                            }
                            break;
                        }
                    }
                }

                if (isset($link['title']) && ! is_array($link['title'])) {
                    $link['title'] = [$locale => (string) $link['title']];
                } elseif (isset($link['title']) && is_array($link['title'])) {
                    $link['title'][$locale] = (string) ($link['title'][$locale] ?? '');
                }

                return $link;
            }, $data['social_links']);
        }

        $existingCopyrightText = [];
        foreach ($existingFooterTranslations as $existingLocale => $existingData) {
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
                $data['copyright_text'] = array_merge($existingCopyrightText, [$locale => (string) $data['copyright_text']]);
            } else {
                $data['copyright_text'] = array_merge($existingCopyrightText, $data['copyright_text']);
                $data['copyright_text'][$locale] = (string) ($data['copyright_text'][$locale] ?? '');
            }
        }

        // Wrap sponsors per locale (sponsors are not text-translatable, just locale-scoped arrays)
        if (isset($data['sponsors']) && is_array($data['sponsors'])) {
            $existingSponsors = [];
            foreach ($existingFooterTranslations as $existingLocale => $existingData) {
                $existingSponsors[$existingLocale] = $existingData['sponsors'] ?? [];
            }
            $existingSponsors[$locale] = $data['sponsors'];
            $data['sponsors'] = $existingSponsors;
        }

        return $data;
    }

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

    private function filterValidRepeaterItems(array $items): array
    {
        return array_values(array_filter($items, fn ($item) => is_array($item)));
    }

    private function filterValidSocialLinks(array $links): array
    {
        return array_values(array_filter($links, fn ($link) => is_array($link)));
    }

    private function extractTranslatableValue(mixed $raw, string $locale, mixed $default = []): mixed
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: $default;
        }

        if (is_array($raw) && (isset($raw['de']) || isset($raw['en']))) {
            return $raw[$locale] ?? $raw['de'] ?? $default;
        }

        return is_array($raw) ? $raw : $default;
    }

    public function updatingActiveLocale(): void
    {
        $this->oldActiveLocale = $this->activeLocale;
    }

    public function updatedActiveLocale(): void
    {
        if (blank($this->oldActiveLocale)) {
            return;
        }

        $this->resetValidation();

        $translatableAttributes = $this->getTranslatableAttributes();

        // Save current locale data
        $this->otherLocaleData[$this->oldActiveLocale] = Arr::only($this->data, $translatableAttributes);

        // Try to get data from otherLocaleData first, otherwise load from records
        $newLocaleData = $this->otherLocaleData[$this->activeLocale] ?? null;

        if ($newLocaleData === null) {
            $newLocaleData = $this->loadLocaleDataFromRecords($this->activeLocale);
        } else {
            $newLocaleData = $this->transformCachedLocaleData($newLocaleData);
        }

        $this->form->fill([
            ...Arr::except($this->data, $translatableAttributes),
            ...$newLocaleData,
        ]);

        unset($this->otherLocaleData[$this->activeLocale]);
    }

    private function loadLocaleDataFromRecords(string $locale): array
    {
        $navigationItems = $this->extractTranslatableValue(
            $this->navigationRecord->getTranslation('navigation_items', $locale, false),
            $locale
        );

        $footerItems = $this->extractTranslatableValue(
            $this->footerRecord->getTranslation('footer_navigation_items', $locale, false),
            $locale
        );

        $socialLinks = $this->extractTranslatableValue(
            $this->footerRecord->getTranslation('social_links', $locale, false),
            $locale
        );

        $copyrightText = $this->extractTranslatableValue(
            $this->footerRecord->getTranslation('copyright_text', $locale, false),
            $locale,
            ''
        );

        $sponsors = $this->extractTranslatableValue(
            $this->footerRecord->getTranslation('sponsors', $locale, false),
            $locale
        );

        return [
            'navigation_items' => $this->transformTranslatableRepeaterItems(
                $this->filterValidRepeaterItems($navigationItems),
                $locale
            ),
            'footer_navigation_items' => $this->transformTranslatableRepeaterItems(
                $this->filterValidRepeaterItems($footerItems),
                $locale
            ),
            'social_links' => array_map(function ($link) use ($locale) {
                if (isset($link['title']) && is_array($link['title'])) {
                    $link['title'] = $link['title'][$locale] ?? $link['title']['de'] ?? '';
                }

                return $link;
            }, $this->filterValidSocialLinks($socialLinks)),
            'copyright_text' => $copyrightText ?? '',
            'sponsors' => is_array($sponsors) ? $this->filterValidRepeaterItems($sponsors) : [],
        ];
    }

    private function transformCachedLocaleData(array $data): array
    {
        $locale = $this->activeLocale;

        if (isset($data['navigation_items'])) {
            $data['navigation_items'] = $this->transformTranslatableRepeaterItems(
                $data['navigation_items'],
                $locale
            );
        }

        if (isset($data['footer_navigation_items'])) {
            $data['footer_navigation_items'] = $this->transformTranslatableRepeaterItems(
                $data['footer_navigation_items'],
                $locale
            );
        }

        if (isset($data['social_links'])) {
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
}
