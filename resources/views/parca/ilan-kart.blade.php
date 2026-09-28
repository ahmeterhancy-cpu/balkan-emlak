{{-- İlan kartı. Beklenen: $ilan (il, ilce, mahalle, kapak, danisman yüklü; fotograflar_count) --}}
@php
    $pasif = ! $ilan->talepAlabilir();
    $kiralik = $ilan->islem->value === 'kiralik';
@endphp
<article class="ilan-kart {{ $pasif ? 'pasif' : '' }}">
    <div class="ilan-kart-ust">
    <a href="{{ route('ilan.detay', $ilan) }}" class="ilan-kart-gorsel" tabindex="-1" aria-hidden="true">
        @if ($ilan->kapak)
            <img src="{{ $ilan->kapak->kucukUrl() }}" alt="" loading="lazy" decoding="async" width="800" height="550">
        @endif
    </a>

    <div class="ilan-kart-rozetler">
        @if (($ilan->fotograflar_count ?? 0) > 0)
            <span class="rozet rozet-acik"><x-simge ad="kamera" /> {{ $ilan->fotograflar_count }}</span>
        @endif
        @if ($ilan->operasyon_durumu->value !== 'aktif')
            <span class="rozet {{ $ilan->operasyon_durumu->value === 'rezerve' ? 'rozet-uyari' : 'rozet-gri' }}">{{ $ilan->operasyon_durumu->getLabel() }}</span>
        @endif
        @if ($ilan->one_cikan)
            <span class="rozet rozet-yesil sag"><x-simge ad="yildiz" /> Öne çıkan</span>
        @endif
    </div>

    @if ($ilan->danisman)
        <div class="ilan-kart-danisman">
            <span class="avatar">
                @if ($ilan->danisman->fotoUrl())
                    <img src="{{ $ilan->danisman->fotoUrl() }}" alt="" width="42" height="42">
                @else
                    {{ $ilan->danisman->basHarfler() }}
                @endif
            </span>
            <span><strong>{{ $ilan->danisman->name }}</strong><span>Danışman</span></span>
        </div>
    @endif
    </div>

    <div class="ilan-kart-govde">
        <h3><a href="{{ route('ilan.detay', $ilan) }}">{{ $ilan->baslik }}</a></h3>
        <p class="ilan-konum"><x-simge ad="konum" /> {{ $ilan->konumMetni() }}</p>
        @if ($ilan->yayin_tarihi)
            <p class="ilan-tarih">Eklendi: {{ tarih_tr($ilan->yayin_tarihi) }}</p>
        @endif

        <ul class="ilan-ozet">
            @if ($ilan->kategori->odaAlani() && $ilan->oda)
                <li>Oda <b><x-simge ad="yatak" /> {{ $ilan->oda }}</b></li>
            @endif
            @if ($ilan->kategori->yapiAlanlari() && $ilan->banyo)
                <li>Banyo <b><x-simge ad="banyo" /> {{ $ilan->banyo }}</b></li>
            @endif
            @if ($ilan->alanMetni())
                <li>Alan <b><x-simge ad="alan" /> {{ $ilan->alanMetni() }}</b></li>
            @endif
            @if (! $ilan->kategori->odaAlani())
                <li>Tür <b>{{ $ilan->tipEtiketi() }}</b></li>
            @endif
        </ul>

        <div class="ilan-kart-alt">
            <div>
                <span class="ilan-islem">{{ $ilan->islem->getLabel() }}</span>
                <span class="fiyat">{{ $ilan->fiyatMetni() }}@if ($kiralik)<small> / ay</small>@endif</span>
            </div>
            <span class="ilan-no">{{ $ilan->portfoy_no }}</span>
        </div>
    </div>
</article>
