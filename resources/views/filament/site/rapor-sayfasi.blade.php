<x-filament-panels::page>
    {{ $this->form }}

    <x-filament::section>
        @include('filament.site.rapor-icerik', ['rapor' => $this->rapor()])
    </x-filament::section>
</x-filament-panels::page>
