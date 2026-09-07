<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class TenantSeeder extends Seeder
{
    /**
     * Seed tenants with domains and associate users.
     */
    public function run(): void
    {
        $demoUser = User::where('email', 'demo@example.com')->first()
            ?? User::factory()->admin()->create([
                'first_name' => 'Demo',
                'last_name' => 'Admin',
                'email' => 'demo@example.com',
            ]);

        $testUser = User::where('email', 'test@example.com')->first();

        // Derive base domain from APP_URL so the seeder works in any environment
        $baseDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';

        // Default tenant — set the main domain
        $defaultTenant = Tenant::where('slug', 'default')->first();
        if ($defaultTenant) {
            $defaultTenant->update(['domain' => $baseDomain]);
        }

        // Stadt Regensburg
        $regensburg = Tenant::firstOrCreate(
            ['slug' => 'stadt-regensburg'],
            ['name' => 'Stadt Regensburg']
        );
        $regensburg->update(['domain' => "regensburg.{$baseDomain}"]);
        $regensburg->users()->syncWithoutDetaching($demoUser->id);
        if ($testUser) {
            $regensburg->users()->syncWithoutDetaching($testUser->id);
        }

        // Demo City
        $demoCity = Tenant::firstOrCreate(
            ['slug' => 'demo-city'],
            ['name' => 'Demo City']
        );
        $demoCity->update(['domain' => "demo-city.{$baseDomain}"]);
        $demoCity->users()->syncWithoutDetaching($demoUser->id);
        if ($testUser) {
            $demoCity->users()->syncWithoutDetaching($testUser->id);
        }

        $demoCityTheme = Theme::firstOrCreate(
            ['slug' => 'demo-city'],
            ['name' => 'Demo City', 'settings' => ['branding' => self::demoCityBrandingTokens()]]
        );
        if ($demoCity->theme_id === null) {
            $demoCity->update(['theme_id' => $demoCityTheme->id]);
        }

        // Always set demo user's default tenant to Regensburg
        $demoUser->forceFill(['default_tenant_id' => $regensburg->id])->save();

        Artisan::call('tenancy:backfill');
    }

    /**
     * Demo City theme palette: a blue demo palette, every color token
     * BrandingSettings defines set to a value consistent with it so no field
     * silently falls back to the Regensburg-red global default. Angular form
     * language: square cards, no shadow.
     *
     * @return array<string, mixed>
     */
    public static function demoCityBrandingTokens(): array
    {
        return [
            'primary_color' => '#1465A4',
            'secondary_color' => '#0D3F66',
            'accent_color' => '#1465A4',
            'slider_colors' => [
                'rail' => '#C9D6E0',
                'handle' => '#1465A4',
                'handleBorder' => '#0D3F66',
            ],
            'background_color' => '#F5F7FA',
            'card_background_color' => '#FFFFFF',
            'hero_background_color' => '#1465A4',
            'overlay_background_color' => 'rgba(20, 101, 164, 0.4)',
            'header_background_color' => '#FFFFFF',
            'footer_background_color' => '#E8EEF3',
            'text_primary_color' => '#1A1A1A',
            'text_secondary_color' => '#4B5563',
            'text_inverse_color' => '#FFFFFF',
            'link_color' => '#1465A4',
            'link_hover_color' => '#0D3F66',
            'border_color' => '#C9D6E0',
            'divider_color' => '#DCE3E9',
            'shadow_color' => '#F5F7FA',
            'nav_text_color' => '#1A1A1A',
            'nav_text_color_inactive' => '#9CA3AF',
            'nav_hover_color' => '#1465A4',
            // Angular demo form language: square corners, no shadow.
            'card_radius' => '0',
            'card_border_width' => '1px',
            'card_border_color' => '#1465A4',
        ];
    }
}
