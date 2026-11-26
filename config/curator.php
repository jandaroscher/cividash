<?php

use App\Filament\Media\MediaForm;
use App\Models\Media;
use App\Support\Media\ResizedUrlProvider;
use App\Support\Media\TenantPathGenerator;
use Awcodes\Curator\Enums\CurationFormats;
use Awcodes\Curator\Resources\Media\MediaResource;
use Awcodes\Curator\Resources\Media\Pages\CreateMedia;
use Awcodes\Curator\Resources\Media\Pages\EditMedia;
use Awcodes\Curator\Resources\Media\Pages\ListMedia;
use Awcodes\Curator\Resources\Media\Tables\MediaTable;

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

// Media library (awcodes/filament-curator). Uploads go to the 'public' disk, which is
// either the local volume or object storage, and are served through the app.
return [
    'curation_formats' => CurationFormats::toArray(),
    'curation_max_dimension' => 8192,
    'default_disk' => 'public',
    'default_directory' => null,
    'default_visibility' => 'public',
    'features' => [
        'curations' => false,
        'file_swap' => false,
        'directory_restriction' => false,
        'preserve_file_names' => false,
        'tenancy' => [
            'enabled' => true,
            'relationship_name' => 'tenant',
        ],
    ],
    'glide_token' => env('CURATOR_GLIDE_TOKEN'),
    'model' => Media::class,
    'path_generator' => TenantPathGenerator::class,
    'resource' => [
        'label' => 'Media',
        'plural_label' => 'Media',
        'default_layout' => 'grid',
        'navigation' => [
            'group' => null,
            'icon' => 'heroicon-o-photo',
            'sort' => null,
            'should_register' => true,
            'should_show_badge' => false,
        ],
        'resource' => MediaResource::class,
        'pages' => [
            'create' => CreateMedia::class,
            'edit' => EditMedia::class,
            'index' => ListMedia::class,
        ],
        'schemas' => [
            'form' => MediaForm::class,
        ],
        'tables' => [
            'table' => MediaTable::class,
        ],
    ],
    'temporary_url_expiration' => 5,
    'url_provider' => ResizedUrlProvider::class,
];
