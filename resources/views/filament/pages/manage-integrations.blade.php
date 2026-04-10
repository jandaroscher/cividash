<x-filament-panels::page>
    <x-filament-panels::form id="form" wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    <x-filament-panels::page.unsaved-data-changes-alert />

    {{-- Sync Status Section --}}
    <x-filament::section :heading="__('filament.pages.manage_integrations.status_heading')">
        <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                <dt class="font-medium text-gray-600 dark:text-gray-400">{{ __('filament.pages.manage_integrations.configuration_status') }}</dt>
                <dd class="text-right">
                    @if($syncStatus->isConfigured)
                        <x-filament::badge color="success">
                            {{ __('filament.pages.manage_integrations.configured') }}
                        </x-filament::badge>
                    @else
                        <x-filament::badge color="warning">
                            {{ __('filament.pages.manage_integrations.not_configured_status') }}
                        </x-filament::badge>
                    @endif
                </dd>
            </div>

            <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                <dt class="font-medium text-gray-600 dark:text-gray-400">{{ __('filament.pages.manage_integrations.connection_status') }}</dt>
                <dd class="text-right">
                    @if($syncStatus->isConnected)
                        <x-filament::badge color="success">
                            {{ __('filament.pages.manage_integrations.connected') }}
                        </x-filament::badge>
                    @else
                        <x-filament::badge color="danger">
                            {{ __('filament.pages.manage_integrations.not_connected') }}
                        </x-filament::badge>
                    @endif
                </dd>
            </div>

            <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                <dt class="font-medium text-gray-600 dark:text-gray-400">{{ __('filament.pages.manage_integrations.last_sync') }}</dt>
                <dd class="text-right">
                    @if($syncStatus->hasEverSynced())
                        <span class="text-gray-900 dark:text-white">{{ $syncStatus->lastSyncedAt->format(__('filament.date_time_format')) }}</span>
                    @else
                        <span class="text-gray-400 dark:text-gray-500">{{ __('filament.pages.manage_integrations.never_synced') }}</span>
                    @endif
                </dd>
            </div>

            @if($syncStatus->lastResult)
                <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5 sm:col-span-2">
                    <dt class="mb-2 font-medium text-gray-600 dark:text-gray-400">{{ __('filament.pages.manage_integrations.sync_result') }}</dt>
                    <dd>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div class="text-center">
                                <div class="text-lg font-semibold text-success-600">{{ $syncStatus->lastResult->created }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('filament.pages.manage_integrations.created') }}</div>
                            </div>
                            <div class="text-center">
                                <div class="text-lg font-semibold text-primary-600">{{ $syncStatus->lastResult->updated }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('filament.pages.manage_integrations.updated') }}</div>
                            </div>
                            <div class="text-center">
                                <div class="text-lg font-semibold text-gray-500">{{ $syncStatus->lastResult->skipped }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('filament.pages.manage_integrations.skipped') }}</div>
                            </div>
                            <div class="text-center">
                                <div class="text-lg font-semibold {{ $syncStatus->lastResult->failed > 0 ? 'text-danger-600' : 'text-gray-500' }}">{{ $syncStatus->lastResult->failed }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('filament.pages.manage_integrations.failed') }}</div>
                            </div>
                        </div>
                    </dd>
                </div>

                @if($syncStatus->lastResult->errors)
                    <div class="rounded-lg bg-danger-50 px-3 py-2 dark:bg-danger-400/10 sm:col-span-2">
                        <dt class="mb-1 font-medium text-danger-600 dark:text-danger-400">{{ __('filament.pages.manage_integrations.errors') }}</dt>
                        <dd>
                            <ul class="list-inside list-disc text-xs text-danger-600 dark:text-danger-400">
                                @foreach($syncStatus->lastResult->errors as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </dd>
                    </div>
                @endif
            @endif
        </dl>
    </x-filament::section>

    {{-- Information --}}
    <x-filament::section :heading="__('filament.pages.manage_integrations.info_heading')">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('filament.pages.manage_integrations.info_text') }}
        </p>
    </x-filament::section>
</x-filament-panels::page>
