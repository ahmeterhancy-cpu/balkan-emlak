<x-filament-widgets::widget class="bp-operasyon">
    <section class="bp-kart bp-liste-kart">
        <header class="bp-liste-ust">
            <h3>Site operasyonu</h3>
            <p>{{ $baslik }} · dikkat isteyen işler</p>
        </header>
        <ul class="bp-liste">
            @foreach ($satirlar as $s)
                <li>
                    <a href="{{ $s['url'] }}" @class(['bp-liste-satir', 'bp-liste-uyari' => $s['uyari']])>
                        <span class="bp-simge {{ $s['uyari'] ? 'bp-ton-turuncu' : 'bp-ton-lacivert' }}">{{ \Filament\Support\generate_icon_html($s['simge']) }}</span>
                        <span class="bp-liste-metin">
                            <span class="bp-liste-etiket">{{ $s['etiket'] }}</span>
                            <span class="bp-liste-alt">{{ $s['alt'] }}</span>
                        </span>
                        <span class="bp-liste-sayi">{{ $s['sayi'] }}</span>
                        <span class="bp-git" aria-hidden="true">{{ \Filament\Support\generate_icon_html('heroicon-m-chevron-right') }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
</x-filament-widgets::widget>
