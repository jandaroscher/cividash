<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Resources\TileResource\Pages;

use App\Filament\Concerns\OpensInLinkedLocale;
use App\Filament\Resources\TileResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditTile extends EditRecord
{
    use OpensInLinkedLocale;
    use Translatable;

    protected static string $resource = TileResource::class;

    protected array $categoryGroupState = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->openLinkedLocale();
    }

    /****
     * Assemble the header action buttons for the edit page.
     *
     * Provides actions for switching locale, saving the record, opening the frontend view
     * for the current locale in a new tab, and deleting the record.
     *
     * @return array An array of action objects to display in the page header.
     */
    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
            Action::make('view_frontend')
                ->label(__('filament.resources.tile.actions.view_frontend'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('success')
                ->url(function () {
                    $locale = $this->activeLocale ?? app()->getLocale();

                    return $this->record->getFrontendUrl(['locale' => $locale]);
                })
                ->openUrlInNewTab(),
            Action::make('publishToCore')
                ->label(__('filament.resources.tile.actions.publish'))
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('primary')
                ->visible(fn (): bool => TileResource::canPublishToCore())
                // Server-side gate: visible() is render-only, so authorize()
                // also blocks direct Livewire invocation by non-admins.
                ->authorize(fn (): bool => TileResource::canUserPublishToCore())
                ->requiresConfirmation()
                ->modalHeading(__('filament.resources.tile.actions.publish_confirm_heading'))
                ->modalDescription(__('filament.resources.tile.actions.publish_confirm_description'))
                ->modalSubmitActionLabel(__('filament.resources.tile.actions.publish_confirm_submit'))
                ->action(fn () => TileResource::handlePublishToCore($this->record)),
            Action::make('save_header')
                ->label(__('filament.actions.save'))
                ->action('save'),
        ];
    }

    public function getFormMaxWidth(): ?string
    {
        return '7xl';
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

    /**
     * Extracts category group selection state from submitted form data and returns the form data with that state removed.
     *
     * Stores extracted category group state in $this->categoryGroupState for use after the record is saved.
     *
     * @param  array  $data  The incoming form data submitted for the record.
     * @return array The form data with category group state stripped out.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->categoryGroupState = TileResource::extractCategoryGroupState($data);

        return TileResource::stripCategoryGroupState($data);
    }

    /**
     * Synchronizes category group selections on the current record using the stored category group state.
     *
     * Persists the category-group relationships extracted from the form into the saved record.
     */
    protected function afterSave(): void
    {
        TileResource::syncCategoryGroupSelections($this->record, $this->categoryGroupState);
    }
}
