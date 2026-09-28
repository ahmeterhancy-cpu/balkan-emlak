<?php

namespace App\Filament\Destek;

use App\Models\BagimsizBolum;
use App\Models\BakimPlani;
use App\Models\Gider;
use App\Models\GiderOdemesi;
use App\Models\Gorev;
use App\Models\Hesap;
use App\Models\HesapHareketi;
use App\Models\IsEmri;
use App\Models\Site;
use App\Services\SiteFinans\SiteRaporu;
use App\Support\Donem;
use App\Support\Kapsam;
use App\Support\Para;
use App\Support\Yetki;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Site yönetimi raporları (şartname 11): ekran, CSV ve yazdırma AYNI veri
 * yapısından üretilir; böylece dışa aktarılan toplamlar ekrandakiyle
 * birebir aynıdır (şartname 17).
 *
 * Yapı: ['baslik', 'site', 'filtreler' => [etiket => değer], 'notlar' => [...],
 *        'tablolar' => [['baslik', 'not', 'sutunlar', 'sagaYasli', 'satirlar', 'altlar']]]
 * Hücre: düz değer | ['para' => kuruş, 'pb' => 'TRY'] | ['oran' => ?float]
 *
 * Tutarlar hiçbir yerde para birimleri arasında toplanmaz; toplamlar para
 * birimine göre ayrı satırlardır. Şirketin emlak komisyonları bu raporlarda yoktur.
 */
class SiteRaporlari
{
    public const RAPORLAR = [
        'donem-ozeti' => 'Dönem özeti',
        'bolum-bakiyeleri' => 'Bölüm bakiyeleri ve vadesi geçmiş borçlar',
        'bolum-ekstresi' => 'Bölüm hesap ekstresi',
        'gider-ozeti' => 'Gider özeti ve tedarikçi borçları',
        'hesap-dokumu' => 'Kasa / banka dökümü',
        'operasyon' => 'İş emri, bakım ve görev raporu',
    ];

    public const SITE_PARASI_NOTU = 'Site paraları — şirketin emlak komisyon gelirleri bu raporda yer almaz. Farklı para birimleri tek toplamda birleştirilmez.';

    public const TAHSILAT_ORANI_TANIMI = '"Tahsilat oranı", seçili dönem tahakkuklarına dağıtılmış tahsilat / seçili dönem tahakkuk toplamı olarak hesaplanır; payda sıfırken oran gösterilmez. Geçmiş dönem tahsilatları ayrıca gösterilir.';

    public static function izinli(string $rapor): bool
    {
        if (! array_key_exists($rapor, self::RAPORLAR)) {
            return false;
        }

        return $rapor === 'operasyon'
            ? Yetki::var('site_rapor') || Yetki::var('operasyon.yonet')
            : Yetki::var('site_rapor') || Yetki::var('finans.goruntule');
    }

    /** Eksik girdileri makul varsayılanlarla tamamla. */
    public static function varsayilanlar(array $g): array
    {
        return [
            'site_id' => $g['site_id'] ?? SiteAlanlari::tekSiteId() ?? array_key_first(SiteAlanlari::siteler()),
            'donem' => $g['donem'] ?? now()->format('Y-m'),
            'bas_donem' => $g['bas_donem'] ?? now()->startOfYear()->format('Y-m'),
            'bit_donem' => $g['bit_donem'] ?? now()->format('Y-m'),
            'bas' => $g['bas'] ?? now()->startOfMonth()->toDateString(),
            'bit' => $g['bit'] ?? now()->toDateString(),
            'gun' => $g['gun'] ?? now()->toDateString(),
            'bolum_id' => $g['bolum_id'] ?? null,
            'hesap_id' => $g['hesap_id'] ?? null,
        ];
    }

    /**
     * Raporu üret. Site erişimi burada da denetlenir (doğrudan adres / Livewire çağrısı).
     */
    public static function uret(string $rapor, array $girdi): array
    {
        abort_unless(static::izinli($rapor), 403);

        $g = static::varsayilanlar($girdi);
        if (! filled($g['site_id'])) {
            return static::bos($rapor, 'Erişebildiğiniz site yok.');
        }
        $site = SiteAlanlari::erisimDenetle($g['site_id']);

        return match ($rapor) {
            'donem-ozeti' => static::donemOzeti($site, (string) $g['donem']),
            'bolum-bakiyeleri' => static::bolumBakiyeleri($site, (string) $g['gun']),
            'bolum-ekstresi' => static::bolumEkstresi($site, $g['bolum_id'], $g['bas'] ?: null, $g['bit'] ?: null),
            'gider-ozeti' => static::giderOzeti($site, (string) $g['bas_donem'], (string) $g['bit_donem']),
            'hesap-dokumu' => static::hesapDokumu($site, $g['hesap_id'], (string) $g['bas'], (string) $g['bit']),
            'operasyon' => static::operasyon($site, (string) $g['bas'], (string) $g['bit']),
        };
    }

    public static function bos(string $rapor, string $mesaj, ?Site $site = null): array
    {
        return ['anahtar' => $rapor, 'baslik' => self::RAPORLAR[$rapor] ?? $rapor, 'site' => $site?->ad, 'filtreler' => [], 'notlar' => [$mesaj], 'tablolar' => []];
    }

    private static function para(int $kurus, string $pb): array
    {
        return ['para' => $kurus, 'pb' => $pb];
    }

    /** Ekranda / yazdırmada gösterilecek metin. */
    public static function metin(mixed $h): string
    {
        if (is_array($h) && array_key_exists('para', $h)) {
            return Para::bicimle((int) $h['para'], $h['pb']);
        }
        if (is_array($h) && array_key_exists('oran', $h)) {
            return $h['oran'] === null ? '—' : '%'.number_format($h['oran'] * 100, 1, ',', '.');
        }
        if ($h === null) {
            return '';
        }

        return is_bool($h) ? ($h ? 'Evet' : 'Hayır') : (string) $h;
    }

    /** CSV'ye yazılacak değer (tutar sayı olarak, sembolsüz). */
    public static function csvDegeri(mixed $h): mixed
    {
        if (is_array($h) && array_key_exists('para', $h)) {
            return Para::formaDegeri((int) $h['para']);
        }
        if (is_array($h) && array_key_exists('oran', $h)) {
            return $h['oran'] === null ? '—' : round($h['oran'] * 100, 1);
        }

        return $h;
    }

    /**
     * Para birimine göre ara toplam satırları.
     *
     * @param  Collection<int, array>  $kaynak
     * @param  array<int, string>  $alanlar  sütun sırası => kaynak anahtarı (kuruş)
     */
    private static function birimToplamlari(Collection $kaynak, int $sutunSayisi, array $alanlar, int $etiketSutunu = 0): array
    {
        return $kaynak->groupBy('para_birimi')->sortKeys()->map(function (Collection $grup, string $pb) use ($sutunSayisi, $alanlar, $etiketSutunu) {
            $satir = array_fill(0, $sutunSayisi, '');
            $satir[$etiketSutunu] = 'Toplam ('.$pb.')';
            foreach ($alanlar as $sira => $anahtar) {
                $satir[$sira] = static::para((int) $grup->sum($anahtar), $pb);
            }

            return $satir;
        })->values()->all();
    }

    /* ======================= Raporlar ======================= */

    public static function donemOzeti(Site $site, string $donem): array
    {
        if (! Donem::gecerli($donem)) {
            return static::bos('donem-ozeti', 'Dönem YYYY-AA biçiminde olmalı.', $site);
        }

        $servis = app(SiteRaporu::class);
        $ozet = $servis->donemOzeti($site, $donem);
        // Hiç kayıt yoksa sitenin para biriminde sıfır satırı (oran gösterilmez)
        $ozet = $ozet ?: [$site->para_birimi => [
            'tahakkuk_kurus' => 0, 'doneme_dagitilan_kurus' => 0, 'kalan_kurus' => 0, 'tahsilat_orani' => null,
            'gecmis_donem_tahsilati_kurus' => 0, 'aydaki_tahsilat_kurus' => 0, 'onayli_gider_kurus' => 0,
        ]];
        ksort($ozet);

        $satirlar = [];
        foreach ($ozet as $pb => $o) {
            $satirlar[] = [
                $pb,
                static::para($o['tahakkuk_kurus'], $pb),
                static::para($o['doneme_dagitilan_kurus'], $pb),
                static::para($o['kalan_kurus'], $pb),
                ['oran' => $o['tahsilat_orani']],
                static::para($o['gecmis_donem_tahsilati_kurus'], $pb),
                static::para($o['aydaki_tahsilat_kurus'], $pb),
                static::para($o['onayli_gider_kurus'], $pb),
            ];
        }

        $bakiyeler = $servis->bakiyeler($site)->map(fn ($b) => [
            'hesap' => $b['hesap'], 'para_birimi' => $b['hesap']->para_birimi, 'bakiye_kurus' => $b['bakiye_kurus'], 'mutabakatsiz' => $b['mutabakatsiz'],
        ]);

        return [
            'anahtar' => 'donem-ozeti',
            'baslik' => 'Dönem özeti — '.Donem::ad($donem),
            'site' => $site->ad,
            'filtreler' => ['Site' => $site->ad, 'Dönem' => Donem::ad($donem).' ('.$donem.')'],
            'notlar' => [self::SITE_PARASI_NOTU, self::TAHSILAT_ORANI_TANIMI],
            'tablolar' => [
                [
                    'baslik' => 'Tahakkuk, tahsilat ve gider',
                    'not' => 'Tahakkuk = dönemin borçları (düzeltme ve iptaller dahil). Aydaki tahsilat = dönem içindeki doğrulanmış tahsilatlar (iadeler düşülmüş), hangi döneme ait olduğundan bağımsız. Onaylı gider = dönemi bu ay olan onaylı giderler.',
                    'sutunlar' => ['Para birimi', 'Tahakkuk', 'Döneme dağıtılan tahsilat', 'Kalan', 'Tahsilat oranı', 'Geçmiş dönem borçlarına tahsilat', 'Aydaki tahsilat', 'Onaylı gider'],
                    'sagaYasli' => [1, 2, 3, 4, 5, 6, 7],
                    'satirlar' => $satirlar,
                    'altlar' => [],
                ],
                [
                    'baslik' => 'Kasa / banka bakiyeleri (bugün)',
                    'sutunlar' => ['Hesap', 'Tür', 'Para birimi', 'Bakiye', 'Mutabakatsız hareket'],
                    'sagaYasli' => [3, 4],
                    'satirlar' => $bakiyeler->map(fn ($b) => [
                        $b['hesap']->ad, Hesap::TURLER[$b['hesap']->tur] ?? $b['hesap']->tur, $b['para_birimi'],
                        static::para($b['bakiye_kurus'], $b['para_birimi']), $b['mutabakatsiz'],
                    ])->all(),
                    'altlar' => static::birimToplamlari($bakiyeler, 5, [3 => 'bakiye_kurus']),
                ],
            ],
        ];
    }

    public static function bolumBakiyeleri(Site $site, string $gun): array
    {
        $servis = app(SiteRaporu::class);
        $bakiyeler = $servis->bolumBakiyeleri($site)
            ->filter(fn ($b) => $b['bolum'] !== null)
            ->sortBy(fn ($b) => [$b['bolum']->blok_id ?? 0, strlen($b['bolum']->no), $b['bolum']->no])
            ->values();
        $gecmis = $servis->vadesiGecmis($site, $gun)->map(fn ($b) => [
            'borc' => $b, 'para_birimi' => $b->para_birimi, 'kalan_kurus' => $b->kalanKurus(),
        ]);

        return [
            'anahtar' => 'bolum-bakiyeleri',
            'baslik' => 'Bölüm bakiyeleri ve vadesi geçmiş borçlar',
            'site' => $site->ad,
            'filtreler' => ['Site' => $site->ad, 'Vade referans tarihi' => Carbon::parse($gun)->format('d.m.Y')],
            'notlar' => [self::SITE_PARASI_NOTU, 'Bakiye = toplam borç − doğrulanmış tahsilat; eksiye düşen kısım avans olarak ayrı gösterilir.'],
            'tablolar' => [
                [
                    'baslik' => 'Bölüm bakiyeleri',
                    'sutunlar' => ['Bölüm', 'Para birimi', 'Toplam borç', 'Tahsilat', 'Açık bakiye', 'Avans'],
                    'sagaYasli' => [2, 3, 4, 5],
                    'satirlar' => $bakiyeler->map(fn ($b) => [
                        $b['bolum']->etiket(), $b['para_birimi'], static::para($b['borc_kurus'], $b['para_birimi']),
                        static::para($b['tahsilat_kurus'], $b['para_birimi']), static::para($b['bakiye_kurus'], $b['para_birimi']),
                        static::para($b['avans_kurus'], $b['para_birimi']),
                    ])->all(),
                    'altlar' => static::birimToplamlari($bakiyeler, 6, [2 => 'borc_kurus', 3 => 'tahsilat_kurus', 4 => 'bakiye_kurus', 5 => 'avans_kurus']),
                ],
                [
                    'baslik' => 'Vadesi geçmiş borçlar',
                    'sutunlar' => ['Bölüm', 'Sorumlu kişi', 'Dönem', 'Açıklama', 'Vade', 'Gecikme (gün)', 'Kalan'],
                    'sagaYasli' => [5, 6],
                    'satirlar' => $gecmis->map(fn ($s) => [
                        $s['borc']->bolum?->etiket(), $s['borc']->kisi?->ad_soyad ?? '—', $s['borc']->donem, $s['borc']->aciklama,
                        $s['borc']->vade->format('d.m.Y'), (int) $s['borc']->vade->diffInDays(Carbon::parse($gun)),
                        static::para($s['kalan_kurus'], $s['para_birimi']),
                    ])->all(),
                    'altlar' => static::birimToplamlari($gecmis, 7, [6 => 'kalan_kurus']),
                ],
            ],
        ];
    }

    public static function bolumEkstresi(Site $site, mixed $bolumId, ?string $bas, ?string $bit): array
    {
        $bolum = filled($bolumId) ? BagimsizBolum::with('blok')->find((int) $bolumId) : null;
        if (! $bolum || (int) $bolum->site_id !== (int) $site->id) {
            return static::bos('bolum-ekstresi', 'Ekstre için bu sitenin bir bağımsız bölümünü seçin.', $site);
        }

        $satirlar = app(SiteRaporu::class)->bolumEkstresi($bolum, $bas, $bit);
        $tablolar = [];
        foreach ($satirlar->groupBy('para_birimi')->sortKeys() as $pb => $liste) {
            $tablolar[] = [
                'baslik' => 'Para birimi: '.$pb,
                'sutunlar' => ['Tarih', 'Tür', 'Açıklama', 'Borç', 'Alacak', 'Bakiye'],
                'sagaYasli' => [3, 4, 5],
                'satirlar' => $liste->map(fn ($s) => [
                    Carbon::parse($s['tarih'])->format('d.m.Y'), $s['tur'], $s['aciklama'],
                    static::para($s['borc_kurus'], $pb), static::para($s['alacak_kurus'], $pb), static::para($s['bakiye_kurus'], $pb),
                ])->all(),
                'altlar' => [['Toplam', '', 'Dönem sonu bakiye (eksi = avans)', static::para((int) $liste->sum('borc_kurus'), $pb),
                    static::para((int) $liste->sum('alacak_kurus'), $pb), static::para((int) $liste->last()['bakiye_kurus'], $pb)]],
            ];
        }

        return [
            'anahtar' => 'bolum-ekstresi',
            'baslik' => 'Hesap ekstresi — '.$bolum->etiket(),
            'site' => $site->ad,
            'filtreler' => ['Site' => $site->ad, 'Bölüm' => $bolum->etiket(),
                'Tarih aralığı' => ($bas ? Carbon::parse($bas)->format('d.m.Y') : 'Başlangıç').' – '.($bit ? Carbon::parse($bit)->format('d.m.Y') : 'Bugün')],
            'notlar' => ['Borç (+), tahsilat (−); yürüyen bakiye tüm geçmiş üzerinden hesaplanır. Doğrulanmamış dekontlar dahil değildir.'],
            'tablolar' => $tablolar ?: [['baslik' => null, 'sutunlar' => ['Tarih', 'Tür', 'Açıklama', 'Borç', 'Alacak', 'Bakiye'], 'sagaYasli' => [], 'satirlar' => [], 'altlar' => []]],
        ];
    }

    public static function giderOzeti(Site $site, string $basDonem, string $bitDonem): array
    {
        if (! Donem::gecerli($basDonem) || ! Donem::gecerli($bitDonem)) {
            return static::bos('gider-ozeti', 'Dönemler YYYY-AA biçiminde olmalı.', $site);
        }

        $servis = app(SiteRaporu::class);
        $kategoriler = $servis->giderOzeti($site, $basDonem, $bitDonem)->map(fn ($r) => [
            'kategori' => $r->kategori, 'para_birimi' => $r->para_birimi, 'onayli_kurus' => (int) $r->onayli_kurus,
            'odenen_kurus' => (int) $r->odenen_kurus, 'kalan_kurus' => (int) $r->onayli_kurus - (int) $r->odenen_kurus,
        ]);
        $tedarikci = $servis->tedarikciBorclari($site);

        $bas = $basDonem.'-01';
        $bit = Carbon::createFromFormat('Y-m-d', $bitDonem.'-01')->endOfMonth()->toDateString();
        $odemeler = GiderOdemesi::query()
            ->whereHas('gider', fn ($q) => $q->where('site_id', $site->id))
            ->whereBetween('tarih', [$bas, $bit])
            ->with(['gider.tedarikci', 'hesap'])->orderBy('tarih')->orderBy('id')->get()
            ->map(fn (GiderOdemesi $o) => ['o' => $o, 'para_birimi' => $o->gider->para_birimi, 'tutar_kurus' => $o->tutar_kurus]);

        return [
            'anahtar' => 'gider-ozeti',
            'baslik' => 'Gider özeti — '.Donem::ad($basDonem).' / '.Donem::ad($bitDonem),
            'site' => $site->ad,
            'filtreler' => ['Site' => $site->ad, 'Gider dönemi' => $basDonem.' – '.$bitDonem],
            'notlar' => [self::SITE_PARASI_NOTU, 'Yalnız onaylanmış giderler. Tedarikçi borcu = onaylı gider − ödenen (tüm dönemler).'],
            'tablolar' => [
                [
                    'baslik' => 'Kategorilere göre onaylı giderler',
                    'sutunlar' => ['Kategori', 'Para birimi', 'Onaylı gider', 'Ödenen', 'Kalan'],
                    'sagaYasli' => [2, 3, 4],
                    'satirlar' => $kategoriler->map(fn ($r) => [
                        Gider::KATEGORILER[$r['kategori']] ?? $r['kategori'], $r['para_birimi'],
                        static::para($r['onayli_kurus'], $r['para_birimi']), static::para($r['odenen_kurus'], $r['para_birimi']), static::para($r['kalan_kurus'], $r['para_birimi']),
                    ])->all(),
                    'altlar' => static::birimToplamlari($kategoriler, 5, [2 => 'onayli_kurus', 3 => 'odenen_kurus', 4 => 'kalan_kurus']),
                ],
                [
                    'baslik' => 'Tedarikçi borçları (bugün)',
                    'sutunlar' => ['Tedarikçi', 'Para birimi', 'Açık gider sayısı', 'Kalan borç'],
                    'sagaYasli' => [2, 3],
                    'satirlar' => $tedarikci->map(fn ($r) => [$r['tedarikci'], $r['para_birimi'], $r['gider_sayisi'], static::para((int) $r['kalan_kurus'], $r['para_birimi'])])->all(),
                    'altlar' => static::birimToplamlari($tedarikci, 4, [3 => 'kalan_kurus']),
                ],
                [
                    'baslik' => 'Gider ödemeleri ('.Carbon::parse($bas)->format('d.m.Y').' – '.Carbon::parse($bit)->format('d.m.Y').')',
                    'not' => 'Geri alınan ödemeler eksi satırla görünür.',
                    'sutunlar' => ['Tarih', 'Tedarikçi', 'Gider', 'Hesap', 'Tutar'],
                    'sagaYasli' => [4],
                    'satirlar' => $odemeler->map(fn ($r) => [
                        $r['o']->tarih->format('d.m.Y'), $r['o']->gider->tedarikci?->ad ?? '—', $r['o']->gider->aciklama, $r['o']->hesap?->ad,
                        static::para($r['tutar_kurus'], $r['para_birimi']),
                    ])->all(),
                    'altlar' => static::birimToplamlari($odemeler, 5, [4 => 'tutar_kurus']),
                ],
            ],
        ];
    }

    public static function hesapDokumu(Site $site, mixed $hesapId, string $bas, string $bit): array
    {
        $hesaplar = Hesap::where('site_id', $site->id)
            ->when(filled($hesapId), fn ($q) => $q->whereKey((int) $hesapId))
            ->orderBy('ad')->get();

        $servis = app(SiteRaporu::class);
        $ozet = [];
        $tablolar = [];
        foreach ($hesaplar as $h) {
            $d = $servis->hesapDokumu($h, $bas, $bit);
            $ozet[] = [
                $h->ad, $h->para_birimi,
                static::para($d['acilis_kurus'], $h->para_birimi), static::para($d['giris_kurus'], $h->para_birimi),
                static::para($d['cikis_kurus'], $h->para_birimi), static::para($d['transfer_haric_giris_kurus'], $h->para_birimi),
                static::para($d['transfer_haric_cikis_kurus'], $h->para_birimi), static::para($d['kapanis_kurus'], $h->para_birimi),
            ];
            $tablolar[] = [
                'baslik' => $h->ad.' ('.$h->para_birimi.') — hareketler',
                'sutunlar' => ['Tarih', 'Tür', 'Açıklama', 'Tutar', 'Mutabık'],
                'sagaYasli' => [3],
                'satirlar' => $d['hareketler']->map(fn (HesapHareketi $x) => [
                    $x->tarih->format('d.m.Y'), HesapHareketi::TURLER[$x->tur] ?? $x->tur, trim(($x->aciklama ?? '').($x->dayanak ? ' — '.$x->dayanak : '')),
                    static::para($x->tutar_kurus, $h->para_birimi), $x->mutabik ? 'Evet' : 'Hayır',
                ])->all(),
                'altlar' => [
                    ['Dönem başı bakiye', '', '', static::para($d['acilis_kurus'], $h->para_birimi), ''],
                    ['Dönem sonu bakiye', '', '', static::para($d['kapanis_kurus'], $h->para_birimi), ''],
                ],
            ];
        }

        return [
            'anahtar' => 'hesap-dokumu',
            'baslik' => 'Kasa / banka dökümü',
            'site' => $site->ad,
            'filtreler' => ['Site' => $site->ad, 'Hesap' => filled($hesapId) ? ($hesaplar->first()?->ad ?? '—') : 'Tüm hesaplar',
                'Tarih aralığı' => Carbon::parse($bas)->format('d.m.Y').' – '.Carbon::parse($bit)->format('d.m.Y')],
            'notlar' => [self::SITE_PARASI_NOTU, 'Transferler hesaplar arası para hareketidir; "transfer hariç" sütunları gelir/gider niteliğindeki giriş ve çıkışları (açılış bakiyesi hariç) gösterir.'],
            'tablolar' => [
                [
                    'baslik' => 'Özet',
                    'sutunlar' => ['Hesap', 'Para birimi', 'Dönem başı', 'Toplam giriş', 'Toplam çıkış', 'Transfer hariç giriş', 'Transfer hariç çıkış', 'Dönem sonu'],
                    'sagaYasli' => [2, 3, 4, 5, 6, 7],
                    'satirlar' => $ozet,
                    'altlar' => [],
                ],
                ...$tablolar,
            ],
        ];
    }

    public static function operasyon(Site $site, string $bas, string $bit): array
    {
        $durumlar = IsEmri::where('site_id', $site->id)->selectRaw('durum, COUNT(*) AS adet')->groupBy('durum')->pluck('adet', 'durum');
        $aralik = [$bas.' 00:00:00', $bit.' 23:59:59'];
        $acilan = IsEmri::where('site_id', $site->id)->whereBetween('created_at', $aralik)->count();
        $tamamlanan = IsEmri::where('site_id', $site->id)->whereBetween('tamamlanma_zamani', $aralik)->count();

        $geciken = BakimPlani::where('site_id', $site->id)->where('aktif', true)->where('sonraki_bakim', '<=', Carbon::parse($bit)->addDays(14)->toDateString())
            ->with(['varlik', 'tedarikci', 'sorumlu'])->orderBy('sonraki_bakim')->get();

        $gorevler = Gorev::where('site_id', $site->id)->whereBetween('tarih', [$bas, $bit])->with('personel')->get()
            ->groupBy('user_id');

        $bugun = now()->toDateString();

        return [
            'anahtar' => 'operasyon',
            'baslik' => 'İş emri, bakım ve görev raporu',
            'site' => $site->ad,
            'filtreler' => ['Site' => $site->ad, 'Tarih aralığı' => Carbon::parse($bas)->format('d.m.Y').' – '.Carbon::parse($bit)->format('d.m.Y')],
            'notlar' => ['İş emri durum dağılımı bugünkü durumdur; açılan / tamamlanan sayıları seçilen aralıktadır.'],
            'tablolar' => [
                [
                    'baslik' => 'İş emirleri — durum dağılımı',
                    'sutunlar' => ['Durum', 'Adet'],
                    'sagaYasli' => [1],
                    'satirlar' => collect(IsEmri::DURUMLAR)->map(fn ($etiket, $d) => [$etiket, (int) ($durumlar[$d] ?? 0)])->values()->all(),
                    'altlar' => [
                        ['Açık (tamamlanmamış)', (int) collect($durumlar)->except(['tamamlandi', 'kapatildi'])->sum()],
                        ['Aralıkta açılan', $acilan],
                        ['Aralıkta tamamlanan', $tamamlanan],
                    ],
                ],
                [
                    'baslik' => 'Bakım takvimi — gecikmiş ve 14 gün içinde yaklaşan',
                    'sutunlar' => ['Bakım planı', 'Varlık', 'Sonraki bakım', 'Durum', 'Tedarikçi / sorumlu'],
                    'sagaYasli' => [],
                    'satirlar' => $geciken->map(fn (BakimPlani $p) => [
                        $p->ad, $p->varlik?->ad ?? '—', $p->sonraki_bakim->format('d.m.Y'),
                        $p->sonraki_bakim->toDateString() < $bugun ? 'Gecikti ('.(int) $p->sonraki_bakim->diffInDays(now()).' gün)' : 'Yaklaşıyor',
                        $p->tedarikci?->ad ?? $p->sorumlu?->name ?? '—',
                    ])->all(),
                    'altlar' => [],
                ],
                [
                    'baslik' => 'Personel görevleri',
                    'sutunlar' => ['Personel', 'Bekleyen', 'Tamamlanan', 'İptal', 'Toplam'],
                    'sagaYasli' => [1, 2, 3, 4],
                    'satirlar' => $gorevler->map(fn (Collection $g) => [
                        $g->first()->personel?->name ?? '—', $g->where('durum', 'bekliyor')->count(), $g->where('durum', 'tamamlandi')->count(),
                        $g->where('durum', 'iptal')->count(), $g->count(),
                    ])->values()->all(),
                    'altlar' => [],
                ],
            ],
        ];
    }

    /* ======================= CSV ======================= */

    /** Raporu CSV satırlarına çevir (her tablo başlığı, sütunları, satırları ve toplamlarıyla). @return array{0: array, 1: list<array>} */
    public static function csvSatirlari(array $rapor): array
    {
        $baslik = [$rapor['baslik'], $rapor['site'] ?? ''];
        $satirlar = [];
        foreach ($rapor['filtreler'] as $etiket => $deger) {
            $satirlar[] = [$etiket, $deger];
        }
        foreach ($rapor['notlar'] as $not) {
            $satirlar[] = ['Not', $not];
        }
        foreach ($rapor['tablolar'] as $t) {
            $satirlar[] = [];
            if (! empty($t['baslik'])) {
                $satirlar[] = [$t['baslik']];
            }
            $satirlar[] = $t['sutunlar'];
            foreach ([...$t['satirlar'], ...($t['altlar'] ?? [])] as $s) {
                $satirlar[] = array_map([static::class, 'csvDegeri'], array_values($s));
            }
        }

        return [$baslik, $satirlar];
    }

    /** Kullanıcının erişebildiği sitelerin bölüm / hesap seçenekleri (rapor formu). */
    public static function kapsamliSite(mixed $siteId): ?int
    {
        return filled($siteId) && Kapsam::siteErisimi((int) $siteId) ? (int) $siteId : null;
    }
}
