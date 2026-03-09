<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Tenant;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\EditTenantProfile as BaseEditTenantProfile;

class EditTenantProfile extends BaseEditTenantProfile
{
    protected static ?string $slug = 'dashboard-configuration';
    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->is_admin;
    }

    /**
     * Page label displayed for the tenant profile page.
     *
     * @return string The translated label for the tenant profile page.
     */
    public static function getLabel(): string
    {
        return __('filament.pages.edit_dashboard_config.title');
    }

    /**
     * Provides the label shown in the navigation for this page.
     *
     * @return string The navigation label.
     */
    public static function getNavigationLabel(): string
    {
        return static::getLabel();
    }

    /**
     * Configure and return the form schema used to edit a tenant's profile.
     *
     * The schema contains sections for basic information (name, slug) and domain/frontend settings
     * (domain, frontend_base_url). The domain field is normalized and validated for format and uniqueness.
     *
     * @param  Form  $form  The form instance to configure.
     * @return Form The configured form instance.
     */
    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('filament.pages.edit_dashboard_config.form.sections.basic_information'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('filament.pages.edit_dashboard_config.form.fields.name.label'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('description')
                        ->label(__('filament.pages.edit_dashboard_config.form.fields.description.label'))
                        ->helperText(__('filament.pages.edit_dashboard_config.form.fields.description.helper'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('slug')
                        ->label(__('filament.pages.edit_dashboard_config.form.fields.slug.label'))
                        ->helperText(__('filament.pages.edit_dashboard_config.form.fields.slug.helper'))
                        ->disabled()
                        ->dehydrated(false),
                ]),

            Forms\Components\Section::make(__('filament.pages.edit_dashboard_config.form.sections.domain_frontend'))
                ->description(__('filament.pages.edit_dashboard_config.form.sections.domain_frontend_description'))
                ->schema([
                    Forms\Components\TextInput::make('domain')
                        ->label(__('filament.pages.edit_dashboard_config.form.fields.domain.label'))
                        ->helperText(__('filament.pages.edit_dashboard_config.form.fields.domain.helper'))
                        ->placeholder(__('filament.pages.edit_dashboard_config.form.fields.domain.placeholder'))
                        ->nullable()
                        ->maxLength(255)
                        ->dehydrateStateUsing(fn (?string $state): ?string => $this->normalizeDomain($state))
                        ->rules([
                            fn (): Closure => function (string $attribute, $value, Closure $fail) {
                                if (empty($value)) {
                                    return;
                                }

                                $normalized = $this->normalizeDomain($value);

                                // Validate domain format (no scheme, no path, valid hostname)
                                if (! $this->isValidDomain($normalized)) {
                                    $fail(__('filament.pages.edit_dashboard_config.form.fields.domain.validation.invalid_format'));
                                }

                                // Check uniqueness against normalized value
                                $existingTenant = Tenant::where('domain', $normalized)
                                    ->where('id', '!=', $this->resolveRecord()?->id)
                                    ->first();

                                if ($existingTenant) {
                                    $fail(__('filament.pages.edit_dashboard_config.form.fields.domain.validation.not_unique'));
                                }
                            },
                        ]),
                    Forms\Components\TextInput::make('frontend_base_url')
                        ->label(__('filament.pages.edit_dashboard_config.form.fields.frontend_base_url.label'))
                        ->helperText(__('filament.pages.edit_dashboard_config.form.fields.frontend_base_url.helper'))
                        ->placeholder(__('filament.pages.edit_dashboard_config.form.fields.frontend_base_url.placeholder'))
                        ->nullable()
                        ->maxLength(255)
                        ->url(),
                ]),
        ]);
    }

    /**
     * Retrieve the current tenant model for the active Filament tenant context.
     *
     * @return Tenant The Tenant model instance for the current tenant.
     */
    protected function resolveRecord(): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();

        return $tenant;
    }

    /**
     * Normalize a domain string to the form used by the tenancy middleware.
     *
     * Trims surrounding whitespace, converts to lowercase, and removes a leading
     * "www." prefix. Empty or null input returns null.
     *
     * @param  string|null  $domain  The raw domain input.
     * @return string|null The normalized domain, or null if the input is null or empty after normalization.
     */
    protected function normalizeDomain(?string $domain): ?string
    {
        if ($domain === null || trim($domain) === '') {
            return null;
        }

        $normalized = strtolower(trim($domain));

        // Strip www. prefix (consistent with ResolveTenantFromRequest middleware)
        if (str_starts_with($normalized, 'www.')) {
            $normalized = substr($normalized, 4);
        }

        return $normalized ?: null;
    }

    /**
     * Determine whether a normalized domain string is a valid hostname without a scheme, path, or port.
     *
     * Allows standard hostnames (labels with letters, digits, hyphens, and dots) and "localhost".
     *
     * @param  string|null  $domain  The normalized domain to validate (trimmed and lowercased, or null).
     * @return bool `true` if the domain is a valid hostname with no scheme, path, or port; `false` otherwise.
     */
    protected function isValidDomain(?string $domain): bool
    {
        if ($domain === null || $domain === '') {
            return false;
        }

        // Reject if contains scheme
        if (preg_match('#^https?://#i', $domain)) {
            return false;
        }

        // Reject if contains path (/)
        if (str_contains($domain, '/')) {
            return false;
        }

        // Reject if contains port (:)
        if (str_contains($domain, ':')) {
            return false;
        }

        // Validate hostname pattern:
        // - Allows alphanumeric, hyphens, dots
        // - Allows "localhost" for development
        // - Standard domain format: label.label.tld
        $pattern = '/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/';

        return (bool) preg_match($pattern, $domain);
    }
}
