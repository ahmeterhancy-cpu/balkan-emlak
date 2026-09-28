@if ($paginator->hasPages())
    <nav class="sayfalama" aria-label="Sayfalar">
        @if ($paginator->onFirstPage())
            <span class="pasif" aria-hidden="true"><x-simge ad="sol" /></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Önceki sayfa"><x-simge ad="sol" /></a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="pasif">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $sayfa => $adres)
                    @if ($sayfa == $paginator->currentPage())
                        <span aria-current="page">{{ $sayfa }}</span>
                    @else
                        <a href="{{ $adres }}" aria-label="Sayfa {{ $sayfa }}">{{ $sayfa }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Sonraki sayfa"><x-simge ad="sag" /></a>
        @else
            <span class="pasif" aria-hidden="true"><x-simge ad="sag" /></span>
        @endif
    </nav>
@endif
