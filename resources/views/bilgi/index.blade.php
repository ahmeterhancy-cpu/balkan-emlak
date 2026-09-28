@extends('duzen.site')

@section('baslik', 'Bilgi Merkezi')
@section('aciklama', 'Emlak alım-satım, kiralama ve site yönetimi üzerine rehber yazılar.')

@section('icerik')
    @include('parca.sayfa-basi', [
        'baslik' => 'Bilgi Merkezi',
        'aciklama' => 'Emlak ve site yönetimi üzerine rehber yazılar.',
        'kirinti' => ['Bilgi Merkezi' => null],
    ])

    <section class="bolum">
        <div class="kap">
            <nav class="etkin-suzgecler" aria-label="Kategoriler" style="margin:0 0 28px;justify-content:center">
                <a href="{{ route('bilgi.index') }}" @if (! $kategori) aria-current="page" style="background:var(--yesil-koyu);color:#fff" @endif>Tümü</a>
                @foreach (\App\Models\Yazi::KATEGORILER as $anahtar => $ad)
                    <a href="{{ route('bilgi.index', ['kategori' => $anahtar]) }}" @if ($kategori === $anahtar) aria-current="page" style="background:var(--yesil-koyu);color:#fff" @endif>{{ $ad }}</a>
                @endforeach
            </nav>

            @if ($yazilar->isEmpty())
                <div class="bos-durum"><x-simge ad="belge" /><h2>Henüz yazı yok</h2><p>Bu kategoride yayımlanmış yazı bulunmuyor.</p></div>
            @else
                <div class="yazi-izgara">
                    @foreach ($yazilar as $yazi)
                        <article class="yazi-kart">
                            @if ($yazi->gorselUrl())
                                <img src="{{ $yazi->gorselUrl() }}" alt="" loading="lazy" width="800" height="450">
                            @endif
                            <div class="govde">
                                <div class="ust-bilgi">{{ \App\Models\Yazi::KATEGORILER[$yazi->kategori] ?? '' }} · {{ tarih_tr($yazi->yayin_tarihi) }}</div>
                                <h3><a href="{{ route('bilgi.detay', $yazi) }}">{{ $yazi->baslik }}</a></h3>
                                <p>{{ $yazi->ozet }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
                {{ $yazilar->links() }}
            @endif
        </div>
    </section>
@endsection
