<?php

namespace App\Filament\Resources\ThemeResource\Pages;

use App\Filament\Resources\ThemeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTheme extends EditRecord
{
    protected static string $resource = ThemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->disabled(fn () => $this->record->tenants()->exists())
                ->tooltip(fn () => $this->record->tenants()->exists()
                    ? __('filament.resources.theme.delete_blocked_tooltip')
                    : null),
        ];
    }
}
