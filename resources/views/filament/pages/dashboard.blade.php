<x-filament-panels::page>
    @php
        $isSafeHttpUrl = function (?string $url): ?string {
            if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
                return null;
            }

            $scheme = parse_url($url, PHP_URL_SCHEME);

            return in_array($scheme, ['http', 'https'], true) ? $url : null;
        };

        $safeOpenSourceDocsUrl = $isSafeHttpUrl($openSourceDocsUrl);
        $safeUserManualUrl = $isSafeHttpUrl($userManualUrl);
        $safeContactUrl = $isSafeHttpUrl($contactUrl);
        $safeContactEmail = $contactEmail && filter_var($contactEmail, FILTER_VALIDATE_EMAIL)
            ? $contactEmail
            : null;
    @endphp

    <div data-testid="dashboard-grid" class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <x-filament::section :heading="__('filament.pages.dashboard_overview.content_links')">
            <ul class="space-y-2">
                @if($safeOpenSourceDocsUrl)
                    <li>
                        <a
                            href="{{ $safeOpenSourceDocsUrl }}"
                            class="text-primary-600 underline"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ __('filament.pages.dashboard_overview.open_source_docs') }}
                        </a>
                    </li>
                @endif

                @if($safeUserManualUrl)
                    <li>
                        <a
                            href="{{ $safeUserManualUrl }}"
                            class="text-primary-600 underline"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ __('filament.pages.dashboard_overview.user_manual') }}
                        </a>
                    </li>
                @endif
            </ul>

            @if(! $safeOpenSourceDocsUrl && ! $safeUserManualUrl)
                <p class="dashboard-muted text-sm text-gray-500">
                    {{ __('filament.pages.dashboard_overview.no_content_links') }}
                </p>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('filament.pages.dashboard_overview.stats_heading')">
            <ul class="space-y-2">
                <li>
                    {{ __('filament.pages.dashboard_overview.active_tiles', ['count' => $tileCount]) }}
                </li>
                <li>
                    {{ __('filament.pages.dashboard_overview.period_data', ['count' => $timePeriodCount]) }}
                </li>
                <li>
                    {{ __('filament.pages.dashboard_overview.active_categories', ['count' => $categoryCount]) }}
                </li>
            </ul>
        </x-filament::section>

        @if($contactName || $safeContactEmail || $safeContactUrl || $madeWithText)
            <x-filament::section :heading="__('filament.pages.dashboard_overview.contact_heading')">
                <ul class="space-y-2">
                    @if($contactName)
                        <li>{{ $contactName }}</li>
                    @endif

                    @if($safeContactEmail)
                        <li>
                            <a href="mailto:{{ $safeContactEmail }}" class="text-primary-600 underline">
                                {{ $safeContactEmail }}
                            </a>
                        </li>
                    @endif

                    @if($safeContactUrl)
                        <li>
                            <a
                                href="{{ $safeContactUrl }}"
                                class="text-primary-600 underline"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ $safeContactUrl }}
                            </a>
                        </li>
                    @endif
                </ul>

                @if($madeWithText)
                    <p class="dashboard-body mt-3 text-sm text-gray-700">{{ $madeWithText }}</p>
                @endif
            </x-filament::section>
        @endif

        <x-filament::section :heading="__('filament.pages.dashboard_overview.server_time_heading')">
            <dl class="dashboard-body grid grid-cols-1 gap-3 text-sm text-gray-700">
                <div class="flex items-center justify-between">
                    <dt class="dashboard-label font-medium text-gray-600">{{ __('filament.pages.dashboard_overview.server_app_env') }}</dt>
                    <dd class="text-right">{{ $serverInfo['app_env'] ?? 'n/a' }}</dd>
                </div>
                @if($showServerTime && $serverInfo['server_time'])
                    <div class="flex items-center justify-between">
                        <dt class="dashboard-label font-medium text-gray-600">{{ __('filament.pages.dashboard_overview.server_time_label') }}</dt>
                        <dd class="text-right">UTC {{ $serverInfo['server_time'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="dashboard-label font-medium text-gray-600">{{ __('filament.pages.dashboard_overview.local_time_label') }}</dt>
                        <dd class="text-right">{{ $serverInfo['local_timezone'] }} {{ $serverInfo['local_time'] }}</dd>
                    </div>
                @endif
                <div class="flex items-center justify-between">
                    <dt class="dashboard-label font-medium text-gray-600">{{ __('filament.pages.dashboard_overview.server_php_version') }}</dt>
                    <dd class="text-right">{{ $serverInfo['php_version'] ?? 'n/a' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="dashboard-label font-medium text-gray-600">{{ __('filament.pages.dashboard_overview.server_laravel_version') }}</dt>
                    <dd class="text-right">{{ $serverInfo['laravel_version'] ?? 'n/a' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="dashboard-label font-medium text-gray-600">{{ __('filament.pages.dashboard_overview.server_db_info') }}</dt>
                    <dd class="text-right">{{ $serverInfo['db_info'] ?? 'n/a' }}</dd>
                </div>
            </dl>
        </x-filament::section>
    </div>
</x-filament-panels::page>
