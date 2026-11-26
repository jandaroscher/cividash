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
use Awcodes\Curator\Resources\Media\Schemas\MediaForm as CuratorMediaForm;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\HtmlString;

/** Curator's media form plus a copyright field and the list of places that use the file. */
class MediaForm extends CuratorMediaForm
{
    public static function getAdditionalInformationFormSchema(): array
    {
        $fields = parent::getAdditionalInformationFormSchema();

        array_splice($fields, 3, 0, [
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
