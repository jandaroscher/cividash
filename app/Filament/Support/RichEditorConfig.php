<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Support;

use Filament\Forms\Components\RichEditor;

class RichEditorConfig
{
    /**
     * Standard toolbar - unified for all RTE fields.
     * Excluded: attachFiles, blockquote, codeBlock
     */
    public const STANDARD_TOOLBAR = [
        'bold',
        'italic',
        'underline',
        'strike',
        'link',
        'h2',
        'h3',
        'bulletList',
        'orderedList',
        'undo',
        'redo',
    ];

    /**
     * Create a RichEditor field configured with the standard toolbar.
     *
     * @param  string  $name  The field name for the RichEditor.
     * @return RichEditor The RichEditor instance with the standard toolbar applied.
     */
    public static function make(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->toolbarButtons(self::STANDARD_TOOLBAR)
            // Trix sets an empty aria-label when no <label for> points at the editor.
            ->extraInputAttributes(fn (RichEditor $component): array => [
                'aria-label' => $component->getLabel(),
            ]);
    }
}
