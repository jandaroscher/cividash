<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Tile;
use App\Models\TileYear;
use App\Settings\DashboardSettings;
use Filament\Pages\Dashboard as BaseDashboard;
use PDO;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.pages.dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    public function getTitle(): string
    {
        return __('filament.pages.dashboard_overview.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.pages.dashboard_overview.navigation_label');
    }

    /**
     * Assembles the associative view data required by the dashboard page.
     *
     * @return array{
     *   openSourceDocsUrl: string|null,
     *   userManualUrl: string|null,
     *   contactName: string|null,
     *   contactEmail: string|null,
     *   contactUrl: string|null,
     *   showServerTime: bool,
     *   serverTime: string|null,
     *   tileCount: int,
     *   tileYearCount: int,
     *   categoryCount: int,
     *   madeWithText: string|null,
     *   serverInfo: array<string, mixed>
     * } An associative array containing configuration values from DashboardSettings, counts for tiles and tile years, optionally the current server time, and a `serverInfo` map with server-related metadata (PHP version, Laravel version, DB info, app environment, timezone, and server_time). */
    protected function getViewData(): array
    {
        $settings = app(DashboardSettings::class);
        $tileCount = Tile::query()->count();
        $tileYearCount = TileYear::query()->count();
        $categoryCount = Category::query()->count();
        $serverInfo = $this->getServerInfo();

        return [
            'openSourceDocsUrl' => $settings->open_source_docs_url,
            'userManualUrl' => $settings->user_manual_url,
            'contactName' => $settings->contact_name,
            'contactEmail' => $settings->contact_email,
            'contactUrl' => $settings->contact_url,
            'showServerTime' => $settings->show_server_time,
            'serverTime' => $settings->show_server_time ? $serverInfo['server_time'] : null,
            'tileCount' => $tileCount,
            'tileYearCount' => $tileYearCount,
            'categoryCount' => $categoryCount,
            'madeWithText' => $settings->made_with_text,
            'serverInfo' => $serverInfo,
        ];
    }

    /**
     * Collects server and application environment information for the dashboard view.
     *
     * Returns an associative array with the following keys:
     * - `server_time`: current UTC date-time string.
     * - `local_time`: current date-time in the configured app timezone.
     * - `local_timezone`: configured timezone name (from `config('app.timezone')`).
     * - `php_version`: PHP version string or `'n/a'` if unavailable.
     * - `laravel_version`: current Laravel application version.
     * - `db_info`: database driver name followed by the server version.
     * - `app_env`: application environment (from `config('app.env')`).
     *
     * @return array<string,string> Associative array of server and environment info.
     */
    protected function getServerInfo(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $serverVersion = $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
        $dbInfo = trim($driver.' '.$serverVersion);

        return [
            'server_time' => now()->utc()->format('Y-m-d H:i:s'),
            'local_time' => now()->format('Y-m-d H:i:s'),
            'local_timezone' => (string) config('app.timezone'),
            'php_version' => phpversion() ?: 'n/a',
            'laravel_version' => app()->version(),
            'db_info' => $dbInfo,
            'app_env' => (string) config('app.env'),
        ];
    }
}
