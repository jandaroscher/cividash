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
        // Single record on this page: query the guard condition once and
        // reuse it, instead of re-querying it for disabled() and tooltip().
        $isInUse = $this->record->tenants()->exists();

        return [
            Actions\DeleteAction::make()
                ->disabled($isInUse)
                ->tooltip($isInUse ? __('filament.resources.theme.delete_blocked_tooltip') : null),
        ];
    }
}
