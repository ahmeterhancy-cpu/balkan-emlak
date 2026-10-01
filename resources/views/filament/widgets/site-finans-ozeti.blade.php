<x-filament-widgets::widget class="bp-finans">
    <div class="bp-baslik">
        <div>
            <h2>Site finansı</h2>
            <p>{{ $baslik }} · {{ $donemAdi }} · şirket komisyon gelirleri hariç</p>
        </div>
    </div>

    @forelse ($satirlar as $s)
        <div class="bp-finans-satir">
            {{-- Dönem tahsilatı: halka + üç rakam --}}
            <section class="bp-kart bp-oran bp-oran-{{ $s['durum'] }}">
                <header class="bp-kart-ust">
                    <span class="bp-etiket">Dönem tahsilatı @unless ($tekBirim)<b class="bp-pb">{{ $s['pb'] }}</b>@endunless</span>
                </header>
                <div class="bp-oran-govde">
                    @php $cevre = 2 * M_PI * 52; @endphp
                    <svg class="bp-halka" viewBox="0 0 120 120" role="img" aria-label="Tahsilat oranı {{ $s['oran_metni'] }}">
                        <circle cx="60" cy="60" r="52" class="bp-halka-zemin" />
                        <circle cx="60" cy="60" r="52" class="bp-halka-dolu"
                            stroke-dasharray="{{ round($cevre * ($s['oran'] ?? 0), 2) }} {{ round($cevre, 2) }}" transform="rotate(-90 60 60)" />
                        <text x="60" y="58" text-anchor="middle" class="bp-halka-deger">{{ $s['oran_metni'] }}</text>
                        <text x="60" y="76" text-anchor="middle" class="bp-halka-alt">Tahsilat oranı</text>
                    </svg>
                    <dl class="bp-oran-liste">
                        <div><dt><i class="bp-nokta bp-nokta-lacivert"></i>Tahakkuk</dt><dd>{{ $s['tahakkuk'] }}</dd></div>
                        <div><dt><i class="bp-nokta bp-nokta-yesil"></i>Tahsil edilen</dt><dd>{{ $s['tahsil'] }}</dd></div>
                        <div><dt><i class="bp-nokta bp-nokta-gri"></i>Kalan</dt><dd>{{ $s['kalan'] }}</dd></div>
                    </dl>
                </div>
                <footer class="bp-kart-alt">Geçmiş dönem borçlarına bu ay ödenen: <b>{{ $s['gecmis'] }}</b></footer>
            </section>

            {{-- Dört temel gösterge --}}
            <div class="bp-izgara">
                @foreach ($s['kartlar'] as $k)
                    <a href="{{ $k['url'] }}" class="bp-kart bp-kpi">
                        <header class="bp-kart-ust">
                            <span class="bp-simge bp-ton-{{ $k['ton'] }}">{{ \Filament\Support\generate_icon_html($k['simge']) }}</span>
                            <span class="bp-etiket">{{ $k['etiket'] }} @unless ($tekBirim)<b class="bp-pb">{{ $s['pb'] }}</b>@endunless</span>
                            <span class="bp-git" aria-hidden="true">{{ \Filament\Support\generate_icon_html('heroicon-m-arrow-up-right') }}</span>
                        </header>
                        <strong class="bp-deger @if ($k['ton'] === 'kirmizi') bp-deger-kirmizi @endif">{{ $k['deger'] }}</strong>
                        <footer class="bp-kart-alt">
                            @if ($k['degisim'])
                                <span @class(['bp-degisim', 'bp-degisim-iyi' => $k['degisim']['iyi'] === true, 'bp-degisim-kotu' => $k['degisim']['iyi'] === false])
                                    title="{{ $oncekiAdi }} ile karşılaştırma">{{ $k['degisim']['yon'] === 'yukari' ? '▲' : '▼' }} {{ $k['degisim']['metin'] }}</span>
                            @endif
                            <span>{{ $k['alt'] }}</span>
                        </footer>
                    </a>
                @endforeach
            </div>
        </div>
    @empty
        <div class="bp-kart bp-bos">Seçili sitede bu döneme ait mali kayıt yok.</div>
    @endforelse
</x-filament-widgets::widget>
