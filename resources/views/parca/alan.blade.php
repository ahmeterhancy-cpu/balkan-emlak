{{--
    Metin / e-posta / telefon / sayı / tarih alanı.
    Beklenen: $ad, $etiket, $hatalar (MessageBag), $on (id öneki)
    İsteğe bağlı: $tur (text), $zorunlu (false), $ipucu, $otomatik (autocomplete), $sinif, $ek (ek öznitelikler dizisi)

    DİKKAT: @include üst sayfanın TÜM değişkenlerini devralır. Buraya "deger"
    gibi genel adlı parametre eklemeyin — ilan sayfasındaki özellik döngüsünün
    $deger'i (ör. "Hayır") formdaki ad/telefon alanlarına sızmıştı.
--}}
@php
    $kimlik = ($on ?? 'f').'-'.str_replace(['[', ']'], '', $ad);
    $hata = $hatalar->first($ad);
    $aciklamaKimlik = trim(($hata ? $kimlik.'-hata ' : '').(isset($ipucu) ? $kimlik.'-ipucu' : ''));
@endphp
<div class="alan {{ $sinif ?? '' }}">
    <label for="{{ $kimlik }}">{{ $etiket }} @unless ($zorunlu ?? false)<span class="istege-bagli">(isteğe bağlı)</span>@endunless</label>
    <input class="girdi" type="{{ $tur ?? 'text' }}" id="{{ $kimlik }}" name="{{ $ad }}"
           value="{{ old($ad) }}"
           @if ($zorunlu ?? false) required @endif
           @isset($otomatik) autocomplete="{{ $otomatik }}" @endisset
           @if ($hata) aria-invalid="true" @endif
           @if ($aciklamaKimlik) aria-describedby="{{ $aciklamaKimlik }}" @endif
           @foreach (($ek ?? []) as $oz => $val) {{ $oz }}="{{ $val }}" @endforeach>
    @isset($ipucu)
        <span class="alan-ipucu" id="{{ $kimlik }}-ipucu">{{ $ipucu }}</span>
    @endisset
    @if ($hata)
        <span class="alan-hata" id="{{ $kimlik }}-hata">{{ $hata }}</span>
    @endif
</div>
