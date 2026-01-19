{{-- One-time Token Reveal with Copy-to-Clipboard --}}
<div 
    x-data="{ 
        copied: false,
        copyToken() {
            navigator.clipboard.writeText(@js($token)).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            });
        }
    }"
    class="rounded-xl border border-success-500 bg-success-50 dark:bg-success-950/50 p-6"
>
    <div class="flex items-start gap-4">
        <div class="flex-shrink-0">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-success-100 dark:bg-success-900">
                <x-heroicon-o-check-circle class="h-6 w-6 text-success-600 dark:text-success-400" />
            </div>
        </div>
        
        <div class="flex-1 min-w-0">
            <h3 class="text-lg font-semibold text-success-800 dark:text-success-200">
                {{ __('filament.pages.manage_api_keys.token_created_title') }}
            </h3>
            
            <p class="mt-1 text-sm text-success-700 dark:text-success-300">
                {{ __('filament.pages.manage_api_keys.your_token_intro') }} <strong>"{{ $tokenName }}"</strong> {{ __('filament.pages.manage_api_keys.token_created_for') }}
            </p>
            
            <div class="mt-4">
                <label class="block text-sm font-medium text-success-800 dark:text-success-200 mb-2">
                    {{ __('filament.pages.manage_api_keys.your_api_token') }}
                </label>
                
                <div class="flex gap-2">
                    <div class="flex-1 relative">
                        <input 
                            type="text" 
                            value="{{ $token }}" 
                            readonly 
                            class="w-full rounded-lg border-success-300 dark:border-success-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 font-mono text-sm pr-10 focus:border-success-500 focus:ring-success-500"
                        />
                    </div>
                    
                    <button 
                        type="button"
                        @click="copyToken()"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-success-300 dark:border-success-700 bg-white dark:bg-gray-900 text-success-700 dark:text-success-300 hover:bg-success-50 dark:hover:bg-success-950 transition-colors"
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
            
            <div class="mt-4 p-3 rounded-lg bg-warning-50 dark:bg-warning-950/50 border border-warning-200 dark:border-warning-800">
                <div class="flex gap-3">
                    <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-warning-600 dark:text-warning-400 flex-shrink-0 mt-0.5" />
                    <p class="text-sm text-warning-800 dark:text-warning-200">
                        <strong>{{ __('filament.pages.manage_api_keys.token_warning') }}</strong>
                    </p>
                </div>
            </div>
            
            <div class="mt-4">
                <button 
                    type="button"
                    wire:click="clearTokenDisplay"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-success-600 text-white hover:bg-success-700 transition-colors"
                >
                    <x-heroicon-o-check class="h-5 w-5" />
                    {{ __('filament.pages.manage_api_keys.token_copied_button') }}
                </button>
            </div>
        </div>
    </div>
</div>
