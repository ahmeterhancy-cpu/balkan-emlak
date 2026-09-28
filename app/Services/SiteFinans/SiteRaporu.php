<?php

namespace App\Services\SiteFinans;

use App\Models\BagimsizBolum;
use App\Models\Borc;
use App\Models\Gider;
use App\Models\Hesap;
use App\Models\HesapHareketi;
use App\Models\Site;
use App\Models\Tahsilat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Site yönetimi raporları (şartname 6.2 ve 11). Tümü tek siteye ve para
 * birimine göre ayrıdır; farklı para birimleri tek toplamda birleşmez.
 * Transferler gelir/gider sayılmaz. Şirketin emlak komisyonları burada yok.
 */
class SiteRaporu
{
    /**
     * Dönem özeti.
     *
     * Tahsilat oranı = seçili dönem tahakkuklarına dağıtılmış tahsilat /
     * seçili dönem tahakkuk toplamı. Payda 0 ise oran null (gösterilmez).
     * Geçmiş dönem borçlarına bu ay yapılan tahsilat ayrıca verilir.
     *
     * @return array<string, array<string, int|float|null>> para birimi => değerler
     */
    public function donemOzeti(Site $site, string $donem): array
    {
        [$bas, $bit] = $this->ayAraligi($donem);

        $tahakkuk = DB::table('borclar')->where('site_id', $site->id)->where('donem', $donem)
            ->selectRaw('para_birimi, SUM(tutar_kurus) AS t')->groupBy('para_birimi')->pluck('t', 'para_birimi');

        $donemeDagitilan = DB::table('tahsilat_dagitimlari as td')
            ->join('borclar as b', 'b.id', '=', 'td.borc_id')
            ->where('b.site_id', $site->id)->where('b.donem', $donem)
            ->selectRaw('b.para_birimi, SUM(td.tutar_kurus) AS t')->groupBy('b.para_birimi')->pluck('t', 'para_birimi');

        $gecmisDonemTahsilati = DB::table('tahsilat_dagitimlari as td')
            ->join('borclar as b', 'b.id', '=', 'td.borc_id')
            ->where('b.site_id', $site->id)->where('b.donem', '<', $donem)
            ->whereBetween('td.tarih', [$bas, $bit])
            ->selectRaw('b.para_birimi, SUM(td.tutar_kurus) AS t')->groupBy('b.para_birimi')->pluck('t', 'para_birimi');

        $aydakiTahsilat = DB::table('tahsilatlar')->where('site_id', $site->id)->where('dogrulandi', true)
            ->whereBetween('tarih', [$bas, $bit])
            ->selectRaw('para_birimi, SUM(tutar_kurus) AS t')->groupBy('para_birimi')->pluck('t', 'para_birimi');

        $gider = DB::table('giderler')->where('site_id', $site->id)->where('donem', $donem)->where('onay_durumu', 'onaylandi')
            ->selectRaw('para_birimi, SUM(tutar_kurus) AS t')->groupBy('para_birimi')->pluck('t', 'para_birimi');

        $birimler = collect([$tahakkuk, $donemeDagitilan, $gecmisDonemTahsilati, $aydakiTahsilat, $gider])
            ->flatMap(fn ($c) => $c->keys())->unique()->values();

        $sonuc = [];
        foreach ($birimler as $pb) {
            $t = (int) ($tahakkuk[$pb] ?? 0);
            $d = (int) ($donemeDagitilan[$pb] ?? 0);
            $sonuc[$pb] = [
                'tahakkuk_kurus' => $t,
                'doneme_dagitilan_kurus' => $d,
                'kalan_kurus' => $t - $d,
                'tahsilat_orani' => $t > 0 ? round($d / $t, 4) : null,
                'gecmis_donem_tahsilati_kurus' => (int) ($gecmisDonemTahsilati[$pb] ?? 0),
                'aydaki_tahsilat_kurus' => (int) ($aydakiTahsilat[$pb] ?? 0),
                'onayli_gider_kurus' => (int) ($gider[$pb] ?? 0),
            ];
        }

        return $sonuc;
    }

    /**
     * Bölüm hesap ekstresi: borç (+), tahsilat (−), yürüyen bakiye.
     * Eksi bakiye = avans (fazla ödeme).
     *
     * @return Collection<int, array{tarih: string, tur: string, aciklama: string, borc_kurus: int, alacak_kurus: int, bakiye_kurus: int, para_birimi: string}>
     */
    public function bolumEkstresi(BagimsizBolum $bolum, ?string $bas = null, ?string $bit = null): Collection
    {
        $satirlar = collect();

        Borc::where('bagimsiz_bolum_id', $bolum->id)->get()->each(function (Borc $b) use ($satirlar) {
            $satirlar->push([
                'tarih' => $b->duzeltilen_borc_id ? $b->created_at->toDateString() : $b->vade->toDateString(),
                'sira' => $b->id,
                'tur' => $b->duzeltilen_borc_id ? 'Düzeltme' : (Borc::TURLER[$b->tur] ?? $b->tur),
                'aciklama' => $b->aciklama.' ('.$b->donem.')'.($b->gerekce && $b->duzeltilen_borc_id ? ' — '.$b->gerekce : ''),
                'borc_kurus' => max(0, $b->tutar_kurus),
                'alacak_kurus' => max(0, -$b->tutar_kurus),
                'para_birimi' => $b->para_birimi,
            ]);
        });

        Tahsilat::where('bagimsiz_bolum_id', $bolum->id)->where('dogrulandi', true)->get()->each(function (Tahsilat $t) use ($satirlar) {
            $satirlar->push([
                'tarih' => $t->tarih->toDateString(),
                'sira' => 1_000_000_000 + $t->id,
                'tur' => $t->tur === 'iade' ? 'İade' : 'Tahsilat',
                'aciklama' => (Tahsilat::YONTEMLER[$t->yontem] ?? $t->yontem).($t->aciklama ? ' — '.$t->aciklama : ''),
                'borc_kurus' => max(0, -$t->tutar_kurus),
                'alacak_kurus' => max(0, $t->tutar_kurus),
                'para_birimi' => $t->para_birimi,
            ]);
        });

        $bakiye = [];

        return $satirlar->sortBy([['tarih', 'asc'], ['sira', 'asc']])->values()
            ->map(function ($s) use (&$bakiye) {
                $bakiye[$s['para_birimi']] = ($bakiye[$s['para_birimi']] ?? 0) + $s['borc_kurus'] - $s['alacak_kurus'];
                unset($s['sira']);

                return $s + ['bakiye_kurus' => $bakiye[$s['para_birimi']]];
            })
            ->filter(fn ($s) => (! $bas || $s['tarih'] >= $bas) && (! $bit || $s['tarih'] <= $bit))
            ->values();
    }

    /** @return Collection<int, Borc> kalanı olan, vadesi geçmiş asıl borçlar */
    public function vadesiGecmis(Site $site, ?string $gun = null): Collection
    {
        $gun ??= now()->toDateString();

        return Borc::query()->where('site_id', $site->id)->where('vade', '<', $gun)
            ->acik()->bakiyeli()->with(['bolum.blok', 'kisi'])
            ->orderBy('vade')->get();
    }

    /** @return Collection<int, object{kategori:string, para_birimi:string, onayli_kurus:int, odenen_kurus:int}> */
    public function giderOzeti(Site $site, string $basDonem, string $bitDonem): Collection
    {
        return DB::table('giderler as g')
            ->where('g.site_id', $site->id)->where('g.onay_durumu', 'onaylandi')
            ->whereBetween('g.donem', [$basDonem, $bitDonem])
            ->selectRaw('g.kategori, g.para_birimi, SUM(g.tutar_kurus) AS onayli_kurus,
                COALESCE(SUM((SELECT SUM(o.tutar_kurus) FROM gider_odemeleri o WHERE o.gider_id = g.id)), 0) AS odenen_kurus')
            ->groupBy('g.kategori', 'g.para_birimi')
            ->orderByDesc('onayli_kurus')
            ->get();
    }

    /** Tedarikçiye borç: onaylı gider − ödenen, tedarikçi ve para birimi bazında. */
    public function tedarikciBorclari(Site $site): Collection
    {
        return Gider::query()->where('site_id', $site->id)->where('onay_durumu', 'onaylandi')->with('tedarikci')->get()
            ->filter(fn (Gider $g) => $g->kalanKurus() > 0)
            ->groupBy(fn (Gider $g) => ($g->tedarikci?->ad ?? 'Tedarikçisiz').'|'.$g->para_birimi)
            ->map(fn ($grup, $anahtar) => [
                'tedarikci' => explode('|', $anahtar)[0],
                'para_birimi' => explode('|', $anahtar)[1],
                'kalan_kurus' => $grup->sum(fn (Gider $g) => $g->kalanKurus()),
                'gider_sayisi' => $grup->count(),
            ])->values();
    }

    /**
     * Hesap dökümü: dönem başı bakiyesi, hareketler, dönem sonu bakiyesi.
     *
     * @return array{acilis_kurus:int, hareketler: Collection<int, HesapHareketi>, kapanis_kurus:int, giris_kurus:int, cikis_kurus:int, transfer_haric_giris_kurus:int, transfer_haric_cikis_kurus:int}
     */
    public function hesapDokumu(Hesap $hesap, string $bas, string $bit): array
    {
        $acilis = (int) $hesap->hareketler()->where('tarih', '<', $bas)->sum('tutar_kurus');
        $hareketler = $hesap->hareketler()->whereBetween('tarih', [$bas, $bit])->orderBy('tarih')->orderBy('id')->get();

        $gelirGider = $hareketler->reject(fn ($h) => $h->tur === 'transfer' || $h->tur === 'acilis');

        return [
            'acilis_kurus' => $acilis,
            'hareketler' => $hareketler,
            'kapanis_kurus' => $acilis + (int) $hareketler->sum('tutar_kurus'),
            'giris_kurus' => (int) $hareketler->where('tutar_kurus', '>', 0)->sum('tutar_kurus'),
            'cikis_kurus' => (int) -$hareketler->where('tutar_kurus', '<', 0)->sum('tutar_kurus'),
            'transfer_haric_giris_kurus' => (int) $gelirGider->where('tutar_kurus', '>', 0)->sum('tutar_kurus'),
            'transfer_haric_cikis_kurus' => (int) -$gelirGider->where('tutar_kurus', '<', 0)->sum('tutar_kurus'),
        ];
    }

    /** Site kasa/banka bakiyeleri (hesap bazında; para birimi ayrı). */
    public function bakiyeler(Site $site): Collection
    {
        return Hesap::where('site_id', $site->id)->where('aktif', true)->get()
            ->map(fn (Hesap $h) => ['hesap' => $h, 'bakiye_kurus' => $h->bakiyeKurus(), 'mutabakatsiz' => $h->hareketler()->where('mutabik', false)->count()]);
    }

    /** Bölüm bazında açık bakiye ve avans (borç sorumlusu ile). */
    public function bolumBakiyeleri(Site $site): Collection
    {
        $borc = DB::table('borclar')->where('site_id', $site->id)
            ->selectRaw('bagimsiz_bolum_id, para_birimi, SUM(tutar_kurus) AS t')->groupBy('bagimsiz_bolum_id', 'para_birimi')->get();
        $tahsilat = DB::table('tahsilatlar')->where('site_id', $site->id)->where('dogrulandi', true)
            ->selectRaw('bagimsiz_bolum_id, para_birimi, SUM(tutar_kurus) AS t')->groupBy('bagimsiz_bolum_id', 'para_birimi')->get();

        $bolumler = BagimsizBolum::where('site_id', $site->id)->with('blok')->get()->keyBy('id');
        $sonuc = [];

        foreach ($borc as $r) {
            $sonuc[$r->bagimsiz_bolum_id.'|'.$r->para_birimi]['borc'] = (int) $r->t;
        }
        foreach ($tahsilat as $r) {
            $sonuc[$r->bagimsiz_bolum_id.'|'.$r->para_birimi]['tahsilat'] = (int) $r->t;
        }

        return collect($sonuc)->map(function ($v, $anahtar) use ($bolumler) {
            [$id, $pb] = explode('|', $anahtar);
            $bakiye = ($v['borc'] ?? 0) - ($v['tahsilat'] ?? 0);

            return [
                'bolum' => $bolumler[(int) $id] ?? null,
                'para_birimi' => $pb,
                'borc_kurus' => $v['borc'] ?? 0,
                'tahsilat_kurus' => $v['tahsilat'] ?? 0,
                'bakiye_kurus' => max(0, $bakiye),
                'avans_kurus' => max(0, -$bakiye),
            ];
        })->values();
    }

    /** @return array{0:string,1:string} */
    private function ayAraligi(string $donem): array
    {
        $bas = Carbon::createFromFormat('Y-m-d', $donem.'-01');

        return [$bas->toDateString(), $bas->copy()->endOfMonth()->toDateString()];
    }
}
