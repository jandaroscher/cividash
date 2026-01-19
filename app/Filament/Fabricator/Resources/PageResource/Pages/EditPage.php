<?php

namespace App\Filament\Fabricator\Resources\PageResource\Pages;

use App\Filament\Fabricator\Resources\PageResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Illuminate\Support\Arr;
use Z3d0X\FilamentFabricator\Resources\PageResource\Pages\EditPage as FabricatorEditPage;

class EditPage extends FabricatorEditPage
{
    use Translatable;

    protected static string $resource = PageResource::class;

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
            }
        }

        return [
            Actions\LocaleSwitcher::make(),
            ...$actions,
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
            ->label(__('filament-fabricator::page-resource.actions.save'))
            ->action('save')
            ->keyBindings(['mod+s']);
    }
}

