<x-filament-panels::page>
    {{-- Token Reveal Section (shown only after token creation) --}}
    @if($this->newTokenPlainText)
        <div class="mb-6">
            @include('filament.pages.api-keys.token-reveal', [
                'token' => $this->newTokenPlainText,
                'tokenName' => $this->newTokenName,
            ])
        </div>
    @endif

    {{-- API Keys Table --}}
    {{ $this->table }}
</x-filament-panels::page>
