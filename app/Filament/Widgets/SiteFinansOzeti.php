<?php

namespace App\Filament\Widgets;

use App\Filament\Destek\SiteGostergeSuzgeci;
use App\Filament\Pages\GostergePaneli;
use App\Filament\Resources\Borclar\BorcResource;
use App\Filament\Resources\Hesaplar\HesapResource;
use App\Filament\Resources\Tahsilatlar\TahsilatResource;
use App\Models\Site;
use App\Services\SiteFinans\SiteRaporu;
use App\Support\Donem;
use App\Support\Para;
use App\Support\Yetki;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Site finans kartları (şartname 6.2): seçili dönemin tahakkuku, tahsilatı
 * ve tahsilat oranı, açık borç, onaylı gider, kasa/banka toplamı. Her para
 * birimi ayrı kart; farklı birimler toplanmaz. Oran payda 0 iken "—".
 */
class SiteFinansOzeti extends StatsOverviewWidget
{
    use InteractsWithPageFilters, SiteGostergeSuzgeci;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return Yetki::var('finans.goruntule') && GostergePaneli::siteGostergeleri();
    }

    protected function getHeading(): ?string
    {
        return 'Site finansı — '.$this->siteEtiketi().' · '.Donem::ad($this->donem());
    }

    protected function getDescription(): ?string
    {
        return 'Site paraları (şirket komisyon gelirleri hariç). Açık borç ve kasa/banka bugünkü değerdir; diğerleri seçili dönem içindir.';
    }

    /**
     * Seçili sitelerin toplamları, para birimine göre.
     *
     * @return array<string, array<string, int>>
     */
    public function toplamlar(): array
    {
        $idler = $this->siteIdleri();
        $servis = app(SiteRaporu::class);
        $t = [];

        foreach (Site::whereKey($idler)->get() as $site) {
            foreach ($servis->donemOzeti($site, $this->donem()) as $pb => $o) {
                foreach (['tahakkuk_kurus', 'doneme_dagitilan_kurus', 'gecmis_donem_tahsilati_kurus', 'aydaki_tahsilat_kurus', 'onayli_gider_kurus'] as $k) {
                    $t[$pb][$k] = ($t[$pb][$k] ?? 0) + (int) $o[$k];
                }
            }
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

    protected function getStats(): array
    {
        $kartlar = [];

        foreach ($this->toplamlar() as $pb => $d) {
            $tahakkuk = $d['tahakkuk_kurus'] ?? 0;
            $dagitilan = $d['doneme_dagitilan_kurus'] ?? 0;
            $oran = $tahakkuk > 0 ? $dagitilan / $tahakkuk : null;

            $kartlar[] = Stat::make('Tahakkuk · '.$pb, Para::bicimle($tahakkuk, $pb))
                ->description('Seçili dönemin borçları')
                ->icon('heroicon-o-document-duplicate');
            $kartlar[] = Stat::make('Tahsilat oranı · '.$pb, $oran === null ? '—' : '%'.number_format($oran * 100, 1, ',', '.'))
                ->description('Döneme dağıtılan '.Para::bicimle($dagitilan, $pb).' · geçmiş dönemlere '.Para::bicimle($d['gecmis_donem_tahsilati_kurus'] ?? 0, $pb))
                ->icon('heroicon-o-chart-pie')
                ->color($oran === null ? 'gray' : ($oran >= 0.8 ? 'success' : 'warning'));
            $kartlar[] = Stat::make('Ay içindeki tahsilat · '.$pb, Para::bicimle($d['aydaki_tahsilat_kurus'] ?? 0, $pb))
                ->description('Doğrulanmış, iadeler düşülmüş')
                ->icon('heroicon-o-banknotes')
                ->url(TahsilatResource::getUrl('index'));
            $kartlar[] = Stat::make('Açık borç · '.$pb, Para::bicimle($d['acik_borc_kurus'] ?? 0, $pb))
                ->description('Tüm dönemler, bugün')
                ->icon('heroicon-o-exclamation-circle')
                ->color('danger')
                ->url(BorcResource::getUrl('index'));
            $kartlar[] = Stat::make('Onaylı gider · '.$pb, Para::bicimle($d['onayli_gider_kurus'] ?? 0, $pb))
                ->description('Dönemi seçili ay olan')
                ->icon('heroicon-o-receipt-percent');
            $kartlar[] = Stat::make('Kasa / banka · '.$pb, Para::bicimle($d['kasa_banka_kurus'] ?? 0, $pb))
                ->description('Aktif hesapların bakiyesi')
                ->icon('heroicon-o-building-library')
                ->url(HesapResource::getUrl('index'));
        }

        return $kartlar ?: [Stat::make('Site finansı', '—')->description('Seçili sitede mali kayıt yok')];
    }
}
