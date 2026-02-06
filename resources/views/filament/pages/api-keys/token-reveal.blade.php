{{-- One-time Token Reveal with Copy-to-Clipboard --}}
<div
    x-data="{
        copied: false,
        copyToken() {
            navigator.clipboard.writeText(@js($token)).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            }).catch(() => {
                this.copied = false;
            });
        }
    }"
    class="space-y-4"
>
    {{-- Success Alert (wrapper provides CSS variable fallback for production view-cache compatibility) --}}
    @php
        $successTitle = __('filament.pages.manage_api_keys.token_created_title');
        $successDescription = __('filament.pages.manage_api_keys.your_token_intro') . ' "' . $tokenName . '" ' . __('filament.pages.manage_api_keys.token_created_for');
        $warningTitle = __('filament.pages.manage_api_keys.token_warning');
    @endphp
    <div style="--c-50:var(--success-50);--c-100:var(--success-100);--c-400:var(--success-400);--c-500:var(--success-500);--c-700:var(--success-700);--c-800:var(--success-800)">
        <x-filament-simple-alert::simple-alert
            color="success"
            icon="heroicon-o-check-circle"
            :title="$successTitle"
            :description="$successDescription"
            border
        />
    </div>

    {{-- Token Input Section --}}
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 space-y-3">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('filament.pages.manage_api_keys.your_api_token') }}
        </label>

        <div class="flex gap-3">
            <input
                type="text"
                value="{{ $token }}"
                readonly
                class="flex-1 h-10 rounded-lg border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 font-mono text-sm focus:border-primary-500 focus:ring-primary-500"
            />

            <button
                type="button"
                @click="copyToken()"
                class="inline-flex items-center gap-2 px-4 h-10 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            >
                <template x-if="!copied">
                    <span class="flex items-center gap-2">
                        <x-heroicon-o-clipboard-document class="h-5 w-5" />
                        {{ __('filament.pages.manage_api_keys.copy') }}
                    </span>
                </template>
                <template x-if="copied">
                    <span class="flex items-center gap-2 text-success-600 dark:text-success-400">
                        <x-heroicon-o-check class="h-5 w-5" />
                        {{ __('filament.pages.manage_api_keys.copied') }}
                    </span>
                </template>
            </button>
        </div>
    </div>

    {{-- Warning Alert (wrapper provides CSS variable fallback for production view-cache compatibility) --}}
    <div style="--c-50:var(--warning-50);--c-100:var(--warning-100);--c-400:var(--warning-400);--c-500:var(--warning-500);--c-700:var(--warning-700);--c-800:var(--warning-800)">
        <x-filament-simple-alert::simple-alert
            color="warning"
            icon="heroicon-o-exclamation-triangle"
            :title="$warningTitle"
            border
        />
    </div>

    {{-- Confirm Button --}}
    <div>
        <button
            type="button"
            wire:click="clearTokenDisplay"
            class="inline-flex items-center gap-2 px-4 h-10 rounded-lg bg-primary-600 text-white hover:bg-primary-700 transition-colors font-medium"
        >
            <x-heroicon-o-check class="h-5 w-5" />
            {{ __('filament.pages.manage_api_keys.token_copied_button') }}
        </button>
    </div>
</div>
