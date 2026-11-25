<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditCategory extends EditRecord
{
    use Translatable;

    protected static string $resource = CategoryResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $locale = request()->query('activeLocale');

        if ($locale && in_array($locale, $this->getTranslatableLocales()) && $locale !== $this->activeLocale) {
            $this->setActiveLocale($locale);
        }
    }

    /**
     * Assemble the header action buttons for the edit page.
     *
     * Provides actions for switching locale, saving the record, and deleting the record.
     *
     * @return array An array of action objects to display in the page header.
     */
    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
            Action::make('save_header')
                ->label(__('filament.actions.save'))
                ->action('save'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    /**
     * Build the form's save action with a localized label, the 'save' action name, and the Mod+S keyboard shortcut.
     *
     * @return Action The configured save Action instance.
     */
    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label(__('filament.actions.save'))
            ->action('save')
            ->keyBindings(['mod+s']);
    }

    public function getFormMaxWidth(): ?string
    {
        return '7xl';
    }
}
