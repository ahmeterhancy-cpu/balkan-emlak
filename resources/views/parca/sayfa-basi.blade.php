{{-- İç sayfa başlığı. Beklenen: $baslik; isteğe bağlı: $aciklama, $kirinti (etiket => url|null), $gorsel ([yol, kök] — site_gorsel_kaynak) --}}
@php $arka = $gorsel ?? site_gorsel_kaynak('cta_gorsel', 'varsayilan-sayfa.jpg'); @endphp
<section class="sayfa-basi">
    <x-resim :yol="$arka[0]" :kok="$arka[1]" class="arka-resim" sizes="100vw" :oncelikli="true" />
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
