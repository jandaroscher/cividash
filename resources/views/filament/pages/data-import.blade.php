<x-filament-panels::page>
    <form>
        {{ $this->form }}

        <div class="fi-form-actions mt-6 flex flex-wrap items-center gap-3">
            <x-filament::button
                wire:click="dryRun"
                wire:loading.attr="disabled"
                color="gray"
                icon="heroicon-o-eye"
            >
                {{ __('filament.pages.data_import.actions.dry_run') }}
            </x-filament::button>

            <x-filament::button
                wire:click="commit"
                wire:loading.attr="disabled"
                color="primary"
                icon="heroicon-o-check"
                wire:confirm="{{ __('filament.pages.data_import.actions.commit_confirm') }}"
            >
                {{ __('filament.pages.data_import.actions.commit') }}
            </x-filament::button>
        </div>
    </form>

    @if ($lastResult)
        <x-filament::section
            :heading="$lastResult['status'] === 'success'
                ? __('filament.pages.data_import.result.heading_success', ['mode' => $lastResultMode])
                : __('filament.pages.data_import.result.heading_failed')"
            :description="__('filament.pages.data_import.result.duration', ['ms' => $lastResult['duration_ms']])"
            :icon="$lastResult['status'] === 'success' ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-circle'"
            :icon-color="$lastResult['status'] === 'success' ? 'success' : 'danger'"
        >
            <div class="space-y-6">
                @if (! empty($lastResult['diff']))
                    <div>
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white mb-3">
                            {{ __('filament.pages.data_import.result.diff_heading') }}
                        </h3>
                        <div class="overflow-hidden rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10 text-sm">
                                <thead class="bg-gray-50 dark:bg-white/5">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left font-semibold text-gray-950 dark:text-white">
                                            {{ __('filament.pages.data_import.result.entity') }}
                                        </th>
                                        <th class="px-4 py-2.5 text-right font-semibold text-success-600 dark:text-success-400">
                                            {{ __('filament.pages.data_import.result.create') }}
                                        </th>
                                        <th class="px-4 py-2.5 text-right font-semibold text-warning-600 dark:text-warning-400">
                                            {{ __('filament.pages.data_import.result.update') }}
                                        </th>
                                        <th class="px-4 py-2.5 text-right font-semibold text-danger-600 dark:text-danger-400">
                                            {{ __('filament.pages.data_import.result.delete') }}
                                        </th>
                                        <th class="px-4 py-2.5 text-right font-semibold text-gray-500 dark:text-gray-400">
                                            {{ __('filament.pages.data_import.result.unchanged') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-white/5 bg-white dark:bg-white/5">
                                    @foreach ($lastResult['diff'] as $entity => $counts)
                                        <tr>
                                            <td class="px-4 py-2.5 font-mono text-xs text-gray-950 dark:text-white">{{ $entity }}</td>
                                            <td class="px-4 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $counts['create'] ?? 0 }}</td>
                                            <td class="px-4 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $counts['update'] ?? 0 }}</td>
                                            <td class="px-4 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $counts['delete'] ?? 0 }}</td>
                                            <td class="px-4 py-2.5 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ $counts['unchanged'] ?? 0 }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if (! empty($lastResult['errors']))
                    <div>
                        <h3 class="text-sm font-semibold text-danger-600 dark:text-danger-400 mb-3">
                            {{ __('filament.pages.data_import.result.errors_heading', ['count' => count($lastResult['errors'])]) }}
                        </h3>
                        <ul class="divide-y divide-danger-600/10 dark:divide-danger-400/10 rounded-lg bg-danger-50 dark:bg-danger-500/10 ring-1 ring-danger-600/20 dark:ring-danger-400/20">
                            @foreach (array_slice($lastResult['errors'], 0, 20) as $error)
                                <li class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        @if (isset($error['row']) && $error['row'] !== null)
                                            <span class="inline-flex items-center rounded bg-danger-600/10 dark:bg-danger-400/15 px-2 py-0.5 text-xs font-mono font-semibold text-danger-700 dark:text-danger-200">Row {{ $error['row'] }}</span>
                                        @endif
                                        <span class="font-mono text-xs font-semibold text-danger-700 dark:text-danger-200">{{ $error['code'] }}</span>
                                        <span class="font-mono text-xs text-gray-600 dark:text-gray-300">@ {{ $error['path'] }}</span>
                                    </div>
                                    <p class="text-sm text-gray-950 dark:text-white">{{ $error['message'] }}</p>
                                </li>
                            @endforeach
                        </ul>
                        @if (count($lastResult['errors']) > 20)
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                {{ __('filament.pages.data_import.result.more_errors', ['count' => count($lastResult['errors']) - 20]) }}
                            </p>
                        @endif
                    </div>
                @endif

                @if (! empty($lastResult['warnings']))
                    <div>
                        <h3 class="text-sm font-semibold text-warning-600 dark:text-warning-400 mb-3">
                            {{ __('filament.pages.data_import.result.warnings_heading', ['count' => count($lastResult['warnings'])]) }}
                        </h3>
                        <ul class="divide-y divide-warning-600/10 dark:divide-warning-400/10 rounded-lg bg-warning-50 dark:bg-warning-500/10 ring-1 ring-warning-600/20 dark:ring-warning-400/20">
                            @foreach ($lastResult['warnings'] as $warning)
                                <li class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <span class="font-mono text-xs font-semibold text-warning-700 dark:text-warning-200">{{ $warning['code'] }}</span>
                                        <span class="font-mono text-xs text-gray-600 dark:text-gray-300">@ {{ $warning['path'] }}</span>
                                    </div>
                                    <p class="text-sm text-gray-950 dark:text-white">{{ $warning['message'] }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </x-filament::section>
    @endif

    <x-filament::section
        :heading="__('filament.pages.data_import.history.heading')"
        icon="heroicon-o-clock"
        collapsible
    >
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
