<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Filament page for managing external integrations (CIVITAS/CORE).
 *
 * This stub provides the page scaffold. The full implementation
 * (connection form, sync status, manual trigger) will be built
 * when is worked on.
 */
class ManageIntegrations extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?int $navigationSort = 30;

    protected static string $view = 'filament.pages.manage-integrations';

    protected static ?string $slug = 'integrations';

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.pages.manage_integrations.navigation_label');
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament.pages.manage_integrations.title');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('integrations.civitas.enabled');
    }
}
