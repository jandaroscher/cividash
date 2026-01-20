<?php

namespace App\Filament\Resources\TileResource\Pages;

use App\Filament\Resources\TileResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Arr;

class EditTile extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = TileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Action::make('save_header')
                ->label(__('filament.actions.save'))
                ->action('save')
                ->keyBindings(['mod+s']),
            Actions\Action::make('view_frontend')
                ->label(__('filament.resources.tile.actions.view_frontend'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('success')
                ->url(function () {
                    $locale = $this->activeLocale ?? app()->getLocale();
                    return $this->record->getUrl(['locale' => $locale]);
                })
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }

    public function updatedActiveLocale(): void
    {
        if (blank($this->oldActiveLocale)) {
            return;
        }

        $this->resetValidation();

        $translatableAttributes = static::getResource()::getTranslatableAttributes();

        $this->otherLocaleData[$this->oldActiveLocale] = Arr::only($this->data, $translatableAttributes);

        // Fix repeater does not work with translations - see: https://github.com/filamentphp/filament/issues/8328#issuecomment-2060787867
        $this->form->fill([
            ...Arr::except($this->data, $translatableAttributes),
            ...$this->otherLocaleData[$this->activeLocale] ?? [],
        ]);
        // Fix repeater does not work with translations - End

        unset($this->otherLocaleData[$this->activeLocale]);
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
            ->label(__('filament.actions.save'))
            ->action('save')
            ->keyBindings(['mod+s']);
    }
}
