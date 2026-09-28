<?php

namespace App\Filament\Destek;

use App\Support\Donem;
use App\Support\Kapsam;

/**
 * Gösterge paneli site widget'larının ortak süzgeci: sayfanın üstündeki
 * site seçici ve dönem (InteractsWithPageFilters ile gelir). Seçilen site
 * yine Kapsam ile denetlenir.
 */
trait SiteGostergeSuzgeci
{
    /** @return list<int> */
    protected function siteIdleri(): array
    {
        $secili = $this->pageFilters['site_id'] ?? null;
        if (filled($secili) && Kapsam::siteErisimi((int) $secili)) {
            return [(int) $secili];
        }

        return array_map('intval', array_keys(SiteAlanlari::siteler()));
    }

    protected function donem(): string
    {
        $d = (string) ($this->pageFilters['donem'] ?? '');

        return Donem::gecerli($d) ? $d : now()->format('Y-m');
    }

    protected function siteEtiketi(): string
    {
        $idler = $this->siteIdleri();

        return count($idler) === 1 ? SiteAlanlari::siteAdi($idler[0]) : count($idler).' site';
    }
}
