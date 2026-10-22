<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;

class ImageMetadataFields
{
    /**
     * Group an image upload with its title, alt text and copyright fields.
     *
     * The metadata keys derive from the upload's name (`image` → `image_title`,
     * `image_alt`, `image_copyright`), so existing `*_alt` values keep working.
     * The fieldset is layout only and does not nest the state. It carries the
     * upload's label; the upload hides its own but keeps it for validation messages.
     */
    public static function make(FileUpload $upload): Fieldset
    {
        $name = $upload->getName();

        return Fieldset::make($upload->getLabel())
            ->schema([
                $upload->hiddenLabel()->columnSpanFull(),
                TextInput::make("{$name}_title")
                    ->label(__('filament.blocks.image_metadata.title'))
                    ->helperText(__('filament.blocks.image_metadata.title_helper'))
                    ->maxLength(255),
                TextInput::make("{$name}_alt")
                    ->label(__('filament.blocks.image_metadata.alt'))
                    ->helperText(__('filament.blocks.image_metadata.alt_helper'))
                    ->maxLength(255),
                TextInput::make("{$name}_copyright")
                    ->label(__('filament.blocks.image_metadata.copyright'))
                    ->helperText(__('filament.blocks.image_metadata.copyright_helper'))
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }
}
