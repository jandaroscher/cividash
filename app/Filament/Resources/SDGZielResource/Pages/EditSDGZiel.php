<?php

namespace App\Filament\Resources\SDGZielResource\Pages;

use App\Filament\Resources\SDGZielResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;

class EditSDGZiel extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = SDGZielResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
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

        // Fix FileUpload with translatable - ensure icon is properly handled
        $this->form->fill([
            ...Arr::except($this->data, $translatableAttributes),
            ...$this->otherLocaleData[$this->activeLocale] ?? [],
        ]);

        unset($this->otherLocaleData[$this->activeLocale]);
    }
}
