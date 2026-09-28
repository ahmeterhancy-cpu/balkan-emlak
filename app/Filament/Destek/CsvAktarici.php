<?php

namespace App\Filament\Destek;

use App\Models\IslemKaydi;
use App\Models\Portfoy;
use App\Models\Talep;
use App\Support\Para;
use App\Support\Yetki;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Portföy ve talep listelerinin CSV dışa aktarımı.
 *
 * Türkçe Excel'in doğrudan açması için UTF-8 BOM ve `;` ayırıcı. Gelen
 * sorgu çağıran taraftan KAPSAMLI gelir (Kapsam + tablo süzgeçleri);
 * burada ayrıca daraltılmaz ama izin yeniden denetlenir. Mülk sahibi
 * adı/telefonu, açık adres ve iç notlar yalnız `portfoy.ic_bilgi` izniyle
 * dosyaya girer.
 */
class CsvAktarici
{
    public static function izinliMi(): bool
    {
        return Yetki::var('disa_aktar');
    }

    /**
     * @param  Builder<Portfoy>|Collection<int, Portfoy>  $kaynak
     * @param  bool|null  $icBilgi  false verilirse iç bilgiler izin olsa da çıkarılır; izni GENİŞLETEMEZ.
     */
    public static function portfoyler(Builder|Collection $kaynak, ?bool $icBilgi = null): StreamedResponse
    {
        abort_unless(static::izinliMi(), 403);

        $icBilgi = Yetki::var('portfoy.ic_bilgi') && ($icBilgi ?? true);
        $kayitlar = $kaynak instanceof Builder
            ? $kaynak->with(['il', 'ilce', 'mahalle', 'danisman', 'mulkSahibi'])->get()
            : $kaynak->load(['il', 'ilce', 'mahalle', 'danisman', 'mulkSahibi']);

        $baslik = ['Portföy no', 'Başlık', 'İşlem', 'Kategori', 'Tip', 'Fiyat', 'Para birimi', 'İl', 'İlçe', 'Mahalle',
            'Brüt m²', 'Net m²', 'Oda', 'Operasyon durumu', 'Yayın durumu', 'Yayın tarihi', 'Danışman', 'Oluşturulma'];
        if ($icBilgi) {
            $baslik = [...$baslik, 'Mülk sahibi', 'Mülk sahibi telefonu', 'Açık adres', 'İç notlar', 'Komisyon anlaşması'];
        }

        $satirlar = $kayitlar->map(function (Portfoy $p) use ($icBilgi) {
            $satir = [
                $p->portfoy_no, $p->baslik, $p->islem?->getLabel(), $p->kategori?->getLabel(), $p->tipEtiketi(),
                Para::formaDegeri($p->fiyat_kurus), $p->para_birimi, $p->il?->ad, $p->ilce?->ad, $p->mahalle?->ad,
                $p->brut_m2, $p->net_m2, $p->oda, $p->operasyon_durumu?->getLabel(), $p->yayin_durumu?->getLabel(),
                $p->yayin_tarihi?->format('d.m.Y H:i'), $p->danisman?->name, $p->created_at?->format('d.m.Y H:i'),
            ];
            if ($icBilgi) {
                $satir = [...$satir, $p->mulkSahibi?->ad_soyad, $p->mulkSahibi?->telefon, $p->acik_adres, $p->ic_notlar, $p->komisyon_anlasmasi];
            }

            return $satir;
        });

        return static::indir('portfoyler', $baslik, $satirlar, 'Portföy CSV', 'portfoy');
    }

    /** @param Builder<Talep>|Collection<int, Talep> $kaynak */
    public static function talepler(Builder|Collection $kaynak): StreamedResponse
    {
        abort_unless(static::izinliMi(), 403);

        $kayitlar = $kaynak instanceof Builder
            ? $kaynak->with(['portfoy', 'atanan'])->get()
            : $kaynak->load(['portfoy', 'atanan']);

        $baslik = ['Tarih', 'Tür', 'Durum', 'Ad soyad', 'Telefon', 'E-posta', 'Kaynak', 'İlgili ilan', 'Atanan', 'Sonraki takip', 'Mesaj'];

        $satirlar = $kayitlar->map(fn (Talep $t) => [
            $t->created_at?->format('d.m.Y H:i'), $t->tur?->getLabel(), $t->durum?->getLabel(), $t->ad_soyad, $t->telefon,
            $t->eposta, Talep::KAYNAKLAR[$t->kaynak] ?? $t->kaynak, $t->portfoy?->portfoy_no, $t->atanan?->name,
            $t->sonraki_takip?->format('d.m.Y'), $t->mesaj,
        ]);

        return static::indir('talepler', $baslik, $satirlar, 'Talep CSV', 'talep');
    }

    /** CSV hücresi: formül enjeksiyonuna karşı =,+,-,@ ile başlayan metnin önüne ' konur. */
    public static function hucre(mixed $deger): string
    {
        if ($deger === null) {
            return '';
        }
        if (is_bool($deger)) {
            return $deger ? 'Evet' : 'Hayır';
        }
        if (is_int($deger) || is_float($deger)) {
            return str_replace('.', ',', (string) $deger);
        }

        $metin = (string) $deger;

        return preg_match('/^[=+\-@\t\r]/', $metin) ? "'".$metin : $metin;
    }

    /** CSV metnini üretir (test ve indirme aynı kodu kullanır). */
    public static function metin(array $baslik, iterable $satirlar): string
    {
        $akis = fopen('php://temp', 'r+');
        fwrite($akis, "\xEF\xBB\xBF");
        fputcsv($akis, $baslik, ';', '"', '');
        foreach ($satirlar as $satir) {
            fputcsv($akis, array_map([static::class, 'hucre'], $satir), ';', '"', '');
        }
        rewind($akis);
        $icerik = stream_get_contents($akis);
        fclose($akis);

        return $icerik;
    }

    private static function indir(string $ad, array $baslik, Collection $satirlar, string $ozet, string $kaynak): StreamedResponse
    {
        $icerik = static::metin($baslik, $satirlar);

        IslemKaydi::yaz('disa_aktarildi', null, null, ['kaynak' => $kaynak, 'adet' => $satirlar->count()], $ozet);

        return response()->streamDownload(
            function () use ($icerik) {
                echo $icerik;
            },
            $ad.'-'.now()->format('Y-m-d-His').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
