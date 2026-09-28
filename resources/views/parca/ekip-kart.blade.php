{{-- Ekip kartı. Beklenen: $kisi (User, ekipte_goster açık) --}}
<article class="ekip-kart">
    <div class="ekip-foto">
        @if ($kisi->fotoUrl())
            <img src="{{ $kisi->fotoUrl() }}" alt="{{ $kisi->name }}" loading="lazy" width="400" height="400">
        @else
            <span aria-hidden="true">{{ $kisi->basHarfler() }}</span>
        @endif
    </div>
    <div class="govde">
        <h3>{{ $kisi->name }}</h3>
        @if ($kisi->gorev)
            <span class="gorev">{{ $kisi->gorev }}</span>
        @endif
        @if ($kisi->telefon)
            <a class="tel" href="{{ tel_baglanti($kisi->telefon) }}"><x-simge ad="telefon" /> {{ $kisi->telefon }}</a>
        @endif
    </div>
</article>
