<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Media;

use App\Filament\Fabricator\Resources\PageResource;
use App\Filament\Pages\ManageBranding;
use App\Filament\Pages\ManageSiteSettings;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\TileResource;
use App\Models\Media;
use App\Models\MetricDefinition;
use App\Services\Media\MediaLibrary;
use Awcodes\Curator\Components\Forms\Uploader;
use Awcodes\Curator\Resources\Media\Schemas\MediaForm as CuratorMediaForm;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Curator's media form with a copyright field, the uploader and the list of places that use the file.
 * Curations and file swap are off, so the preview is a plain section instead of a single tab.
 */
class MediaForm extends CuratorMediaForm
{
    public static function configure(Schema $schema): Schema
    {
        $record = fn (ViewField $component, $record) => $component->state($record);

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make(trans('curator::forms.sections.file'))
                            ->hiddenOn('edit')
                            ->schema([static::getUploaderField()->required()]),
                        Section::make(trans('curator::forms.sections.preview'))
                            ->hiddenOn('create')
                            ->schema([
                                ViewField::make('preview')
                                    ->view('curator::components.forms.preview')
                                    ->hiddenLabel()
                                    ->dehydrated(false)
                                    ->afterStateHydrated($record),
                            ]),
                        Section::make(trans('curator::forms.sections.details'))
                            ->schema([
                                ViewField::make('details')
                                    ->view('filament.media.details')
                                    ->hiddenLabel()
                                    ->dehydrated(false)
                                    ->columnSpan('full')
                                    ->afterStateHydrated($record),
                            ]),
                    ])
                    ->columnSpan(['md' => 'full', 'lg' => 2]),
                Group::make()
                    ->schema([
                        Section::make(trans('curator::forms.sections.meta'))
                            ->schema(static::getAdditionalInformationFormSchema()),
                    ])
                    ->columnSpan(['md' => 'full', 'lg' => 1]),
            ])
            ->columns(['lg' => 3]);
    }

    /** Hidden label, still read out by screen readers: without one it falls back to the English field name. */
    public static function getUploaderField(): Uploader
    {
        return parent::getUploaderField()->label(trans('curator::forms.sections.file'));
    }

    public static function getAdditionalInformationFormSchema(): array
    {
        // The package's own alt text and title fields are single-value; ours are per language
        $fields = array_values(array_filter(
            parent::getAdditionalInformationFormSchema(),
            fn ($field) => ! in_array($field->getName(), ['alt', 'title'], true),
        ));

        array_splice($fields, 1, 0, [
            static::translatedFields(),
            TextInput::make('copyright')
                ->label(__('filament.media.copyright'))
                ->maxLength(255),
        ]);

        $fields[] = TextEntry::make('usages')
            ->label(__('filament.media.usages'))
            ->hiddenOn('create')
            ->state(fn (?Media $record): HtmlString => static::usageList($record));

        return $fields;
    }

    /** Alt text and title, one tab per content language. Empty values fall back to the default language. */
    protected static function translatedFields(): Tabs
    {
        $default = config('app.default_content_locale');
        $locales = collect(config('app.available_locales'))->sortBy(fn (string $locale) => $locale !== $default)->values();
        $fallbackHint = fn (string $locale) => fn (?string $state): ?string => $locale !== $default && blank($state)
            ? __('filament.media.uses_default_locale', ['locale' => strtoupper($default)])
            : null;

        return Tabs::make('translations')
            ->tabs($locales->map(fn (string $locale) => Tab::make(strtoupper($locale))->schema([
                TextInput::make("alt.{$locale}")
                    ->label(trans('curator::forms.fields.alt'))
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->helperText($fallbackHint($locale)),
                TextInput::make("title.{$locale}")
                    ->label(trans('curator::forms.fields.title'))
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->helperText($fallbackHint($locale)),
            ]))->all());
    }

    protected static function usageList(?Media $record): HtmlString
    {
        $usages = $record ? app(MediaLibrary::class)->usages($record) : [];

        if ($usages === []) {
            return new HtmlString(e(__('filament.media.no_usages')));
        }

        $items = array_map(function (array $usage): string {
            $url = static::usageUrl($usage);
            $label = e($usage['label']);

            return '<li>'.($url ? '<a class="fi-link" href="'.e($url).'">'.$label.'</a>' : $label).'</li>';
        }, $usages);

        return new HtmlString('<ul class="list-disc ps-4">'.implode('', $items).'</ul>');
    }

    /** @param array{type: string, id: int, label: string} $usage */
    protected static function usageUrl(array $usage): ?string
    {
        return rescue(fn (): ?string => match ($usage['type']) {
            'pages' => PageResource::getUrl('edit', ['record' => $usage['id']]),
            'tiles' => TileResource::getUrl('edit', ['record' => $usage['id']]),
            'metric_definitions' => TileResource::getUrl('edit', ['record' => MetricDefinition::withoutGlobalScopes()->find($usage['id'])?->tile_id]),
            'categories' => CategoryResource::getUrl('edit', ['record' => $usage['id']]),
            'footer_navigations' => ManageSiteSettings::getUrl(),
            'settings' => ManageBranding::getUrl(),
            default => null,
        }, null, false);
    }
}
