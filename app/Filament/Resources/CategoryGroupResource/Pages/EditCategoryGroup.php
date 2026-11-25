<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Resources\CategoryGroupResource\Pages;

use App\Filament\Resources\CategoryGroupResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditCategoryGroup extends EditRecord
{
    use Translatable;

    protected static string $resource = CategoryGroupResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $locale = request()->query('activeLocale');

        if ($locale && in_array($locale, $this->getTranslatableLocales()) && $locale !== $this->activeLocale) {
            $this->setActiveLocale($locale);
        }
    }

    /**
     * Form actions go below the categories table so editors save after editing both.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save'),
            $this->getRelationManagersContentComponent(),
            $this->getFormActionsContentComponent(),
        ]);
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
}
