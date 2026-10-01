<?php

namespace App\Filament\Widgets;

use App\Filament\Destek\SiteGostergeSuzgeci;
use App\Filament\Pages\GostergePaneli;
use App\Filament\Resources\Borclar\BorcResource;
use App\Filament\Resources\Giderler\GiderResource;
use App\Filament\Resources\Hesaplar\HesapResource;
use App\Filament\Resources\Tahsilatlar\TahsilatResource;
use App\Models\Site;
use App\Services\SiteFinans\SiteRaporu;
use App\Support\Donem;
use App\Support\Para;
use App\Support\Yetki;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Site finans özeti (şartname 6.2): seçili dönemin tahakkuku ve tahsilat
 * oranı (halka), ay içi tahsilat, açık borç, onaylı gider, kasa/banka.
 * Her para birimi ayrı satır; farklı birimler toplanmaz. Oran payda 0
 * iken "—". Ay içi tahsilat ve gider bir önceki ayla karşılaştırılır.
 */
class SiteFinansOzeti extends Widget
{
    use InteractsWithPageFilters, SiteGostergeSuzgeci;

    /** Sayfanın üstünde: tembel yüklemede boş kutu görünmesin. */
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.site-finans-ozeti';

    public static function canView(): bool
    {
        return Yetki::var('finans.goruntule') && GostergePaneli::siteGostergeleri();
    }

    /**
     * Seçili sitelerin toplamları, para birimine göre.
     *
     * @return array<string, array<string, int>>
     */
    public function toplamlar(?string $donem = null, bool $bugunkuDegerler = true): array
    {
        $idler = $this->siteIdleri();
        $servis = app(SiteRaporu::class);
        $donem ??= $this->donem();
        $t = [];

        foreach (Site::whereKey($idler)->get() as $site) {
            foreach ($servis->donemOzeti($site, $donem) as $pb => $o) {
                foreach (['tahakkuk_kurus', 'doneme_dagitilan_kurus', 'gecmis_donem_tahsilati_kurus', 'aydaki_tahsilat_kurus', 'onayli_gider_kurus'] as $k) {
                    $t[$pb][$k] = ($t[$pb][$k] ?? 0) + (int) $o[$k];
                }
            }
        }

        if (! $bugunkuDegerler) {
            return $t;
        }

        // Açık borç: borç (düzeltmeler dahil) − dağıtılan tahsilat, tüm dönemler
        $borc = DB::table('borclar')->whereIn('site_id', $idler)
            ->selectRaw('para_birimi, SUM(tutar_kurus) AS t')->groupBy('para_birimi')->pluck('t', 'para_birimi');
        $odenen = DB::table('tahsilat_dagitimlari as td')->join('borclar as b', 'b.id', '=', 'td.borc_id')->whereIn('b.site_id', $idler)
            ->selectRaw('b.para_birimi, SUM(td.tutar_kurus) AS t')->groupBy('b.para_birimi')->pluck('t', 'para_birimi');
        foreach ($borc as $pb => $toplam) {
            $t[$pb]['acik_borc_kurus'] = (int) $toplam - (int) ($odenen[$pb] ?? 0);
        }

        $kasa = DB::table('hesap_hareketleri as h')->join('hesaplar as a', 'a.id', '=', 'h.hesap_id')
            ->whereIn('h.site_id', $idler)->where('a.aktif', true)
            ->selectRaw('a.para_birimi, SUM(h.tutar_kurus) AS t')->groupBy('a.para_birimi')->pluck('t', 'para_birimi');
        foreach ($kasa as $pb => $toplam) {
            $t[$pb]['kasa_banka_kurus'] = (int) $toplam;
        }

        ksort($t);

        return $t;
    }

    /** Görünüm verisi: her para birimi için halka + dört kart. */
    protected function getViewData(): array
    {
        $donem = $this->donem();
        $onceki = Carbon::createFromFormat('Y-m-d', $donem.'-01')->subMonth()->format('Y-m');
        $simdi = $this->toplamlar();
        $gecen = $this->toplamlar($onceki, bugunkuDegerler: false);

        $satirlar = [];
        foreach ($simdi as $pb => $d) {
            $tahakkuk = $d['tahakkuk_kurus'] ?? 0;
            $dagitilan = $d['doneme_dagitilan_kurus'] ?? 0;
            $oran = $tahakkuk > 0 ? min(1, $dagitilan / $tahakkuk) : null;

            $satirlar[] = [
                'pb' => $pb,
                'oran' => $oran,
                'oran_metni' => $oran === null ? '—' : '%'.number_format($oran * 100, 1, ',', '.'),
                'durum' => $oran === null ? 'notr' : ($oran >= 0.8 ? 'iyi' : ($oran >= 0.5 ? 'orta' : 'zayif')),
                'tahakkuk' => Para::bicimle($tahakkuk, $pb),
                'tahsil' => Para::bicimle($dagitilan, $pb),
                'kalan' => Para::bicimle(max(0, $tahakkuk - $dagitilan), $pb),
                'gecmis' => Para::bicimle($d['gecmis_donem_tahsilati_kurus'] ?? 0, $pb),
                'kartlar' => [
                    $this->kart('Ay içi tahsilat', $d['aydaki_tahsilat_kurus'] ?? 0, $gecen[$pb]['aydaki_tahsilat_kurus'] ?? 0, $pb,
                        'Doğrulanmış, iadeler düşülmüş', 'heroicon-o-banknotes', 'yesil', TahsilatResource::getUrl('index'), artisIyi: true),
                    $this->kart('Açık borç', $d['acik_borc_kurus'] ?? 0, null, $pb,
                        'Tüm dönemler, bugün itibarıyla', 'heroicon-o-exclamation-triangle', 'kirmizi', BorcResource::getUrl('index')),
                    $this->kart('Onaylı gider', $d['onayli_gider_kurus'] ?? 0, $gecen[$pb]['onayli_gider_kurus'] ?? 0, $pb,
                        'Dönemi seçili ay olan', 'heroicon-o-receipt-percent', 'turuncu', GiderResource::getUrl('index'), artisIyi: false),
                    $this->kart('Kasa / banka', $d['kasa_banka_kurus'] ?? 0, null, $pb,
                        'Aktif hesapların bakiyesi', 'heroicon-o-building-library', 'lacivert', HesapResource::getUrl('index')),
                ],
            ];
        }

        return [
            'satirlar' => $satirlar,
            'baslik' => $this->siteEtiketi(),
            'donemAdi' => Donem::ad($donem),
            'oncekiAdi' => Donem::ad($onceki),
            'tekBirim' => count($satirlar) === 1,
        ];
    }

    /** @return array<string, mixed> */
    private function kart(string $etiket, int $kurus, ?int $onceki, string $pb, string $alt, string $simge, string $ton, string $url, bool $artisIyi = true): array
    {
        $degisim = null;
        if ($onceki !== null && $onceki > 0) {
            $oran = ($kurus - $onceki) / $onceki;
            $degisim = [
                'metin' => ($oran >= 0 ? '+' : '−').'%'.number_format(abs($oran) * 100, 0, ',', '.'),
                'yon' => $oran >= 0 ? 'yukari' : 'asagi',
                'iyi' => $oran == 0 ? null : (($oran > 0) === $artisIyi),
            ];
        }

        return compact('etiket', 'alt', 'simge', 'ton', 'url', 'degisim') + ['deger' => Para::bicimle($kurus, $pb)];
    }
}
