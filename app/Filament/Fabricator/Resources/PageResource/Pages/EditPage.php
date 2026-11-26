<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Fabricator\Resources\PageResource\Pages;

use App\Filament\Concerns\OpensInLinkedLocale;
use App\Filament\Fabricator\Resources\PageResource;
use Filament\Actions\Action;
use Illuminate\Support\Arr;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;
use Z3d0X\FilamentFabricator\Resources\PageResource\Pages\EditPage as FabricatorEditPage;

class EditPage extends FabricatorEditPage
{
    use OpensInLinkedLocale;
    use Translatable {
        fillForm as fillTranslatableForm;
    }

    protected static string $resource = PageResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->openLinkedLocale();
    }

    protected function fillForm(): void
    {
        $this->fillTranslatableForm();

        // Landing pages get the slug "/" in every locale, so a locale with nothing but that slug
        // would count as an existing translation and block saving the other locale until it is
        // translated. Such a locale only counts once it has content besides the slug.
        $this->otherLocaleData = array_filter(
            $this->otherLocaleData,
            fn (array $data): bool => collect(Arr::except($data, 'slug'))->contains(fn (mixed $value): bool => filled($value)),
        );
    }

    protected function getHeaderActions(): array
    {
        $actions = array_values(array_filter(
            parent::getHeaderActions(),
            fn ($action) => ! method_exists($action, 'getName') || $action->getName() !== 'preview'
        ));

        foreach ($actions as $action) {
            if (! method_exists($action, 'getName')) {
                continue;
            }

            $name = $action->getName();

            if (in_array($name, ['visit', 'view'], true)) {
                $action->label(__('filament.resources.page.actions.view_frontend'));
                $action->url(function () {
                    $page = $this->getRecord();
                    $locale = $this->activeLocale ?? app()->getLocale();

                    return $page->getFrontendUrl(['locale' => $locale]);
                })->openUrlInNewTab();
            }
        }

        return [
            LocaleSwitcher::make(),
            ...$actions,
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label(__('filament-fabricator::page-resource.actions.save'))
            ->action('save')
            ->keyBindings(['mod+s']);
    }
}
