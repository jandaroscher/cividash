<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alt text and title of library items become per language. An existing value is the
 * text editors wrote so far, so it becomes the value of the default content language.
 */
return new class extends Migration
{
    private const COLUMNS = ['alt', 'title'];

    public function up(): void
    {
        Schema::table('curator', function (Blueprint $table) {
            $table->text('alt')->nullable()->change();
            $table->text('title')->nullable()->change();
        });

        $locale = config('app.default_content_locale');
        $this->rewrite(fn (string $value) => $this->isTranslated($value)
            ? $value
            : json_encode([$locale => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function down(): void
    {
        $locale = config('app.default_content_locale');
        $this->rewrite(function (string $value) use ($locale) {
            if (! $this->isTranslated($value)) {
                return $value;
            }
            $translations = json_decode($value, true);

            return mb_substr((string) ($translations[$locale] ?? collect($translations)->filter()->first() ?? ''), 0, 255);
        });

        Schema::table('curator', function (Blueprint $table) {
            $table->string('alt')->nullable()->change();
            $table->string('title')->nullable()->change();
        });
    }

    /**
     * Already converted: a JSON object keyed only by content languages with text values,
     * so running up twice changes nothing. Any other text, JSON or not, is a legacy value.
     */
    private function isTranslated(string $value): bool
    {
        $decoded = json_decode($value, true);

        return is_array($decoded) && $decoded !== [] && ! array_is_list($decoded)
            && array_diff(array_keys($decoded), config('app.available_locales')) === []
            && collect($decoded)->every(fn ($text) => $text === null || is_string($text));
    }

    /** @param  Closure(string): string  $convert */
    private function rewrite(Closure $convert): void
    {
        DB::table('curator')->select(['id', ...self::COLUMNS])->orderBy('id')->each(function ($row) use ($convert) {
            $values = [];
            foreach (self::COLUMNS as $column) {
                if ($row->{$column} !== null && $row->{$column} !== '') {
                    $values[$column] = $convert($row->{$column});
                }
            }
            if ($values !== []) {
                DB::table('curator')->where('id', $row->id)->update($values);
            }
        });
    }
};
