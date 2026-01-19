<?php

namespace App\Filament\Pages;

use App\Models\Tile;
use App\Models\TileYear;
use App\Settings\DashboardSettings;
use Filament\Pages\Dashboard as BaseDashboard;
use PDO;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.pages.dashboard';

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
     *   madeWithText: string|null,
     *   serverInfo: array<string, mixed>
     * } An associative array containing configuration values from DashboardSettings, counts for tiles and tile years, optionally the current server time, and a `serverInfo` map with server-related metadata (PHP version, Laravel version, DB info, app environment, timezone, and server_time). */
    protected function getViewData(): array
    {
        $settings = app(DashboardSettings::class);
        $tileCount = Tile::query()->count();
        $tileYearCount = TileYear::query()->count();
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
            'madeWithText' => $settings->made_with_text,
            'serverInfo' => $serverInfo,
        ];
    }

    /**
     * Collects server and application environment information for the dashboard view.
     *
     * Returns an associative array with the following keys:
     * - `server_time`: current server date-time string.
     * - `php_version`: PHP version string or `'n/a'` if unavailable.
     * - `laravel_version`: current Laravel application version.
     * - `db_info`: database driver name followed by the server version.
     * - `app_env`: application environment (from `config('app.env')`).
     * - `timezone`: application timezone (from `config('app.timezone')`).
     *
     * @return array<string,string> Associative array of server and environment info.
     */
    protected function getServerInfo(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $serverVersion = $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
        $dbInfo = trim($driver . ' ' . $serverVersion);

        return [
            'server_time' => now()->toDateTimeString(),
            'php_version' => phpversion() ?: 'n/a',
            'laravel_version' => app()->version(),
            'db_info' => $dbInfo,
            'app_env' => (string) config('app.env'),
            'timezone' => (string) config('app.timezone'),
        ];
    }
}