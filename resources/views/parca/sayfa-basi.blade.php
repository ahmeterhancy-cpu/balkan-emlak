{{-- İç sayfa başlığı. Beklenen: $baslik; isteğe bağlı: $aciklama, $kirinti (etiket => url|null), $gorsel --}}
<section class="sayfa-basi" style="background-image:url('{{ $gorsel ?? site_gorsel('cta_gorsel', 'varsayilan-sayfa.jpg') }}')">
    <div class="kap">
        <nav aria-label="Konumunuz">
            <ol class="kirinti">
                <li><a href="{{ route('anasayfa') }}">Ana Sayfa</a></li>
                @foreach (($kirinti ?? []) as $etiket => $adres)
                    <li>
                        @if ($adres)
                            <a href="{{ $adres }}">{{ $etiket }}</a>
                        @else
                            <span aria-current="page">{{ $etiket }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
        <h1>{{ $baslik }}</h1>
        @isset($aciklama)
            <p>{{ $aciklama }}</p>
        @endisset
    </div>
</section>
