<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Settings\BrandingSettings;
use App\Settings\FooterSettings;
use App\Settings\GeneralSettings;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ConfigController extends Controller
{
    public function branding(): JsonResource
    {
        $settings = app(BrandingSettings::class);

        return new JsonResource([
            'primary_color'   => $settings->primary_color,
            'secondary_color' => $settings->secondary_color,
            // Convert the stored path into a public URL:
            'logo_url'        => $settings->logo_url
                ? Storage::disk('public')->url($settings->logo_url)
                : null,
        ]);
    }

    public function general(): JsonResource
    {
        $settings = app(GeneralSettings::class);

        return new JsonResource([
            'site_name'   => $settings->site_name,
            'site_active' => $settings->site_active,
        ]);
    }

    public function footer(): JsonResource
    {
        $settings = app(FooterSettings::class);

        return new JsonResource([
            'footer_links' => $settings->footer_links,
            'footer_logos' => $settings->footer_logos,
            'social_links' => $settings->social_links,
        ]);
    }
}
