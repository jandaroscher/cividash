<?php

namespace Tests\Feature\Models;

use App\Models\Tenant;
use App\Settings\TenantAwareDatabaseSettingsRepository;
use Filament\Facades\Filament;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Mein Dashboard', 'slug' => 'mein-dashboard']);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null, isQuiet: true);
        parent::tearDown();
    }

    public function test_tenant_implements_has_avatar(): void
    {
        $this->assertInstanceOf(HasAvatar::class, $this->tenant);
    }

    public function test_avatar_url_uses_default_color_when_no_branding(): void
    {
        $url = $this->tenant->getFilamentAvatarUrl();

        $this->assertStringNotContainsString('ui-avatars.com', $url);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $url);
        $this->assertStringContainsString('fill="#0d47a1"', $this->decodeSvg($url));
    }

    public function test_avatar_url_uses_accent_color_when_set(): void
    {
        $this->setSettingForTenant($this->tenant->id, 'accent_color', '#e91e63');
        $this->setSettingForTenant($this->tenant->id, 'primary_color', '#1a237e');

        $url = $this->tenant->getFilamentAvatarUrl();

        $this->assertStringContainsString('fill="#e91e63"', $this->decodeSvg($url));
    }

    public function test_avatar_url_falls_back_to_primary_color(): void
    {
        $this->setSettingForTenant($this->tenant->id, 'primary_color', '#4caf50');

        $url = $this->tenant->getFilamentAvatarUrl();

        $this->assertStringContainsString('fill="#4caf50"', $this->decodeSvg($url));
    }

    public function test_avatar_url_falls_back_to_global_accent(): void
    {
        $globalId = TenantAwareDatabaseSettingsRepository::GLOBAL_TENANT_ID;
        $this->setSettingForTenant($globalId, 'accent_color', '#ff5722');

        $url = $this->tenant->getFilamentAvatarUrl();

        $this->assertStringContainsString('fill="#ff5722"', $this->decodeSvg($url));
    }

    public function test_bulk_load_avatar_colors(): void
    {
        $tenantA = Tenant::create(['name' => 'Alpha', 'slug' => 'alpha']);
        $tenantB = Tenant::create(['name' => 'Beta', 'slug' => 'beta']);

        $this->setSettingForTenant($tenantA->id, 'accent_color', '#ff0000');
        $this->setSettingForTenant($tenantB->id, 'primary_color', '#00ff00');

        Tenant::loadAvatarColors([$tenantA, $tenantB]);

        $this->assertEquals('#ff0000', $tenantA->avatarColor);
        $this->assertEquals('#00ff00', $tenantB->avatarColor);
    }

    public function test_bulk_load_uses_global_fallback(): void
    {
        $tenantA = Tenant::create(['name' => 'Alpha', 'slug' => 'alpha']);
        $tenantB = Tenant::create(['name' => 'Beta', 'slug' => 'beta']);

        $globalId = TenantAwareDatabaseSettingsRepository::GLOBAL_TENANT_ID;
        $this->setSettingForTenant($globalId, 'accent_color', '#9c27b0');
        $this->setSettingForTenant($tenantA->id, 'accent_color', '#ff0000');

        Tenant::loadAvatarColors([$tenantA, $tenantB]);

        $this->assertEquals('#ff0000', $tenantA->avatarColor);
        $this->assertEquals('#9c27b0', $tenantB->avatarColor);
    }

    public function test_avatar_initials_derived_from_name(): void
    {
        $url = $this->tenant->getFilamentAvatarUrl();
        $this->assertStringContainsString('>MD<', $this->decodeSvg($url));

        $single = Tenant::create(['name' => 'Alpha', 'slug' => 'alpha-init']);
        $singleUrl = $single->getFilamentAvatarUrl();
        $this->assertStringContainsString('>AL<', $this->decodeSvg($singleUrl));
    }

    public function test_avatar_svg_is_valid_xml(): void
    {
        $url = $this->tenant->getFilamentAvatarUrl();

        $svg = new \SimpleXMLElement($this->decodeSvg($url));
        $this->assertSame('svg', $svg->getName());
    }

    private function decodeSvg(string $dataUri): string
    {
        return base64_decode(substr($dataUri, strlen('data:image/svg+xml;base64,')));
    }

    private function setSettingForTenant(int $tenantId, string $name, string $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['group' => 'branding', 'name' => $name, 'tenant_id' => $tenantId],
            ['payload' => json_encode($value), 'locked' => false],
        );
    }
}
