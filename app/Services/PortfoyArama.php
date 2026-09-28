<?php

namespace App\Services;

use App\Enums\Islem;
use App\Enums\Kategori;
use App\Models\Konum;
use App\Models\Portfoy;
use App\Support\Para;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Vitrin portföy araması. Süzgeçler sorgu dizesinde durur, böylece
 * sonuç bağlantısı paylaşılabilir (şartname 5.3).
 *
 * Yalnız `yayinda` kayıtlar aranır. Geçersiz değerler sessizce yok
 * sayılır (ör. ?islem=xyz) — 500 ya da boş liste yerine süzgeçsiz sonuç.
 */
class PortfoyArama
{
    public const SIRALAMALAR = [
        'yeni' => 'En yeni',
        'fiyat_artan' => 'Fiyat (artan)',
        'fiyat_azalan' => 'Fiyat (azalan)',
        'm2_artan' => 'Metrekare (artan)',
        'm2_azalan' => 'Metrekare (azalan)',
    ];

    public const ODALAR = ['1+0', '1+1', '2+1', '3+1', '4+1', '5+1'];

    /** @var array<string, mixed> doğrulanmış süzgeçler */
    public array $suzgec = [];

    public function __construct(Request $istek)
    {
        $s = [];

        if ($islem = Islem::tryFrom((string) $istek->query('islem'))) {
            $s['islem'] = $islem->value;
        }

        // "konut" ya da "konut:daire" (ana sayfa arama kutusu tek seçimle gönderir)
        $tur = (string) $istek->query('kategori');
        if (str_contains($tur, ':')) {
            [$tur, $tip] = explode(':', $tur, 2);
        }
        $tip ??= (string) $istek->query('tip');

        if ($kategori = Kategori::tryFrom($tur)) {
            $s['kategori'] = $kategori->value;
            if ($tip !== '' && isset($kategori->tipler()[$tip])) {
                $s['tip'] = $tip;
            }
        }

        foreach (['il', 'ilce', 'mahalle'] as $alan) {
            $id = (int) $istek->query($alan);
            if ($id > 0) {
                $s[$alan] = $id;
            }
        }

        // Ana sayfadaki birleşik konum seçimi: "ilce-5" / "il-1"
        $konum = (string) $istek->query('konum');
        if (preg_match('/^(il|ilce)-(\d+)$/', $konum, $m)) {
            $s[$m[1]] = (int) $m[2];
        }

        foreach (['fiyat_min', 'fiyat_max'] as $alan) {
            $kurus = Para::kurusa($istek->query($alan));
            if ($kurus !== null && $kurus > 0) {
                $s[$alan] = $kurus;
            }
        }

        foreach (['m2_min', 'm2_max'] as $alan) {
            $deger = (int) $istek->query($alan);
            if ($deger > 0) {
                $s[$alan] = $deger;
            }
        }

        // Oda yalnız konutta anlamlı (arsa/ticari seçiliyse yok sayılır)
        $oda = (string) $istek->query('oda');
        if (in_array($oda, self::ODALAR, true) && ($s['kategori'] ?? 'konut') === 'konut') {
            $s['oda'] = $oda;
        }

        $q = trim((string) $istek->query('q'));
        if ($q !== '') {
            $s['q'] = mb_substr($q, 0, 60);
        }

        $sira = (string) $istek->query('sirala');
        $s['sirala'] = array_key_exists($sira, self::SIRALAMALAR) ? $sira : 'yeni';

        $this->suzgec = $s;
    }

    public function sorgu(): Builder
    {
        $s = $this->suzgec;

        $sorgu = Portfoy::query()
            ->yayinda()
            ->with(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad', 'kapak', 'danisman:id,name,foto'])
            ->withCount('fotograflar');

        $sorgu->when($s['islem'] ?? null, fn ($q, $v) => $q->where('islem', $v))
            ->when($s['kategori'] ?? null, fn ($q, $v) => $q->where('kategori', $v))
            ->when($s['tip'] ?? null, fn ($q, $v) => $q->where('tip', $v))
            ->when($s['il'] ?? null, fn ($q, $v) => $q->where('il_id', $v))
            ->when($s['ilce'] ?? null, fn ($q, $v) => $q->where('ilce_id', $v))
            ->when($s['mahalle'] ?? null, fn ($q, $v) => $q->where('mahalle_id', $v))
            ->when($s['oda'] ?? null, fn ($q, $v) => $q->where('oda', $v))
            ->when($s['fiyat_min'] ?? null, fn ($q, $v) => $q->where('fiyat_kurus', '>=', $v))
            ->when($s['fiyat_max'] ?? null, fn ($q, $v) => $q->where('fiyat_kurus', '<=', $v))
            ->when($s['m2_min'] ?? null, fn ($q, $v) => $q->whereRaw('COALESCE(net_m2, brut_m2) >= ?', [$v]))
            ->when($s['m2_max'] ?? null, fn ($q, $v) => $q->whereRaw('COALESCE(net_m2, brut_m2) <= ?', [$v]))
            ->when($s['q'] ?? null, function ($q, $v) {
                $q->where(fn ($w) => $w->where('baslik', 'like', '%'.$v.'%')->orWhere('portfoy_no', 'like', '%'.$v.'%'));
            });

        // Satılan/kiralanan ilanlar listenin sonunda (durumlarıyla görünür)
        $sorgu->orderByRaw("CASE WHEN operasyon_durumu IN ('aktif', 'rezerve') THEN 0 ELSE 1 END");

        match ($s['sirala']) {
            'fiyat_artan' => $sorgu->orderBy('fiyat_kurus'),
            'fiyat_azalan' => $sorgu->orderByDesc('fiyat_kurus'),
            'm2_artan' => $sorgu->orderByRaw('COALESCE(net_m2, brut_m2)'),
            'm2_azalan' => $sorgu->orderByRaw('COALESCE(net_m2, brut_m2) DESC'),
            default => $sorgu->orderByDesc('yayin_tarihi')->orderByDesc('id'),
        };

        return $sorgu;
    }

    /**
     * Kaldırılabilir süzgeç etiketleri: [etiket, süzgeçsiz bağlantı].
     *
     * @return list<array{0:string,1:string}>
     */
    public function etkinEtiketler(): array
    {
        $s = $this->suzgec;
        $etiketler = [];
        $konumlar = Konum::whereIn('id', array_filter([$s['il'] ?? null, $s['ilce'] ?? null, $s['mahalle'] ?? null]))->pluck('ad', 'id');

        $ekle = function (string $etiket, array $cikar) use (&$etiketler) {
            $etiketler[] = [$etiket, $this->baglanti(array_fill_keys($cikar, null))];
        };

        if (isset($s['islem'])) {
            $ekle(Islem::from($s['islem'])->getLabel(), ['islem']);
        }
        if (isset($s['kategori'])) {
            $k = Kategori::from($s['kategori']);
            $ekle(isset($s['tip']) ? $k->tipler()[$s['tip']] : $k->getLabel(), ['kategori', 'tip', 'oda']);
        }
        foreach (['il', 'ilce', 'mahalle'] as $alan) {
            if (isset($s[$alan], $konumlar[$s[$alan]])) {
                $ekle($konumlar[$s[$alan]], $alan === 'il' ? ['il', 'ilce', 'mahalle'] : ($alan === 'ilce' ? ['ilce', 'mahalle'] : ['mahalle']));
            }
        }
        if (isset($s['oda'])) {
            $ekle($s['oda'], ['oda']);
        }
        if (isset($s['fiyat_min']) || isset($s['fiyat_max'])) {
            $ekle(trim((isset($s['fiyat_min']) ? Para::bicimle($s['fiyat_min']) : '').' – '.(isset($s['fiyat_max']) ? Para::bicimle($s['fiyat_max']) : '')), ['fiyat_min', 'fiyat_max']);
        }
        if (isset($s['m2_min']) || isset($s['m2_max'])) {
            $ekle(trim(($s['m2_min'] ?? '').' – '.($s['m2_max'] ?? '').' m²'), ['m2_min', 'm2_max']);
        }
        if (isset($s['q'])) {
            $ekle('“'.$s['q'].'”', ['q']);
        }

        return $etiketler;
    }

    /** Mevcut süzgeçler + değişiklikler ile liste adresi. Kuruş değerleri TL'ye çevrilir. */
    public function baglanti(array $degisiklik = []): string
    {
        $p = array_merge($this->suzgec, $degisiklik);

        foreach (['fiyat_min', 'fiyat_max'] as $alan) {
            if (isset($p[$alan])) {
                $p[$alan] = intdiv((int) $p[$alan], 100);
            }
        }
        if (($p['sirala'] ?? 'yeni') === 'yeni') {
            unset($p['sirala']);
        }

        return route('emlak.index', array_filter($p, fn ($v) => $v !== null && $v !== ''));
    }

    /** Form alanlarının doldurulması için (fiyatlar TL). */
    public function formDegeri(string $alan): mixed
    {
        $deger = $this->suzgec[$alan] ?? null;

        if (in_array($alan, ['fiyat_min', 'fiyat_max'], true) && $deger !== null) {
            return intdiv($deger, 100);
        }

        return $deger;
    }
}
