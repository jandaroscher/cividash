<x-filament-panels::page>
    {{-- Token Reveal Section (shown only after token creation) --}}
    @if($this->newTokenPlainText)
        <div class="mb-6" wire:key="token-reveal">
            @include('filament.pages.api-keys.token-reveal', [
                'token' => $this->newTokenPlainText,
                'tokenName' => $this->newTokenName,
            ])
        </div>
    @endif

    {{-- API Keys Table --}}
    <div wire:key="api-keys-table">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
