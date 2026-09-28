@extends('duzen.site')

@section('baslik', $yazi->baslik)
@section('aciklama', $yazi->seo_aciklama ?: ($yazi->ozet ?: Str::limit(strip_tags($yazi->icerik), 155)))
@section('og_tur', 'article')
@if ($yazi->gorselUrl())
    @section('og_gorsel', $yazi->gorselUrl())
@endif

@push('yapisal_veri')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $yazi->baslik,
        'datePublished' => $yazi->yayin_tarihi?->toAtomString(),
        'dateModified' => $yazi->updated_at?->toAtomString(),
        'image' => $yazi->gorselUrl(),
        'publisher' => ['@type' => 'Organization', 'name' => ayar('firma_adi')],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('icerik')
    @include('parca.sayfa-basi', [
        'baslik' => $yazi->baslik,
        'kirinti' => ['Bilgi Merkezi' => route('bilgi.index'), Str::limit($yazi->baslik, 40) => null],
    ])

    <section class="bolum">
        <article class="kap yazi-detay">
            <div class="ust-bilgi">
                <span>{{ \App\Models\Yazi::KATEGORILER[$yazi->kategori] ?? '' }}</span>
                <span>{{ tarih_tr($yazi->yayin_tarihi) }}</span>
                <span>{{ $yazi->okumaSuresi() }} dk okuma</span>
            </div>
            @if ($yazi->gorselUrl())
                <x-resim :yol="$yazi->gorsel" :alt="$yazi->gorsel_alt ?: ''" class="kapak" sizes="(min-width: 860px) 820px, 100vw" :oncelikli="true" />
            @endif
            <div class="kutu icerik-metni">{!! \App\Support\Html::temizle($yazi->icerik) !!}</div>
        </article>
    </section>

    @if ($digerleri->isNotEmpty())
        <section class="bolum bolum-beyaz">
            <div class="kap">
                <div class="bolum-baslik"><h2>Diğer yazılar</h2></div>
                <div class="yazi-izgara">
                    @foreach ($digerleri as $d)
                        <article class="yazi-kart">
                            @if ($d->gorsel)<x-resim :yol="$d->gorsel" sizes="(min-width: 1100px) 400px, (min-width: 640px) 50vw, 100vw" />@endif
                            <div class="govde">
                                <div class="ust-bilgi">{{ tarih_tr($d->yayin_tarihi) }}</div>
                                <h3><a href="{{ route('bilgi.detay', $d) }}">{{ $d->baslik }}</a></h3>
                                <p>{{ $d->ozet }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
