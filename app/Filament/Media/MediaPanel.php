<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace App\Filament\Media;

use App\Models\Media;
use Awcodes\Curator\Components\Modals\CuratorPanel;
use Filament\Actions\Action;

/**
 * Curator's picker panel, whose "edit item" modal uses our per-language media fields
 * and whose search reads alt text and title per language instead of their raw JSON.
 */
class MediaPanel extends CuratorPanel
{
    /** @return list<string> JSON paths of a translatable column, one per content language */
    public static function translatedColumns(string $column): array
    {
        return array_map(fn (string $locale) => "{$column}->{$locale}", config('app.available_locales'));
    }

    public function updatedSearch(): void
    {
        $terms = $this->getSearchTerms();

        if ($terms === []) {
            parent::updatedSearch();

            return;
        }

        $columns = ['name', ...static::translatedColumns('title'), ...static::translatedColumns('alt'), 'caption', 'description'];

        // Same matching as the package: every term has to match, in any of the columns
        $this->files = $this->getMediaScope()->query()
            ->where(function ($query) use ($terms, $columns): void {
                foreach ($terms as $term) {
                    $query->where(function ($query) use ($term, $columns): void {
                        foreach ($columns as $column) {
                            $query->orWhereRaw($query->getGrammar()->wrap($column)." like ? escape '~'", ['%'.$term.'%']);
                        }
                    });
                }
            })
            ->orderBy('created_at', $this->defaultSort)
            ->limit(50)
            ->get()
            ->toArray();

        $this->currentPage = $this->lastPage;
    }

    public function editItemAction(): Action
    {
        return parent::editItemAction()
            ->schema(MediaForm::getAdditionalInformationFormSchema())
            ->fillForm(function (array $arguments): array {
                $record = $this->resolveAuthorizedMedia($arguments, 'update');

                return $record instanceof Media
                    ? [...$record->toArray(), ...collect($record->getTranslatableAttributes())->mapWithKeys(fn (string $field) => [$field => $record->getTranslations($field)])]
                    : [];
            });
    }
}
