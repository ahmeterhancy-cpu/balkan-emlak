<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Gider;
use App\Models\IsEmri;
use App\Models\IslemKaydi;
use App\Models\SiteBelgesi;
use App\Models\Tahsilat;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Site yönetimi özel dosyaları: dekont, gider belgesi, site belgesi, iş emri
 * fotoğrafı. Hepsi `local` diskinde (storage/app/private), web kökünde
 * değil; tek yol bu rotalar. Her indirme politika denetiminden geçer
 * (başka sitenin dosyası 403) ve işlem geçmişine yazılır (şartname 8.4, 8.6, 15).
 */
class SiteDosyaController extends Controller
{
    private const BASLIKLAR = ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex'];

    public function dekont(Tahsilat $tahsilat): StreamedResponse
    {
        Gate::authorize('view', $tahsilat);

        return $this->gonder($tahsilat->belge_yolu, $tahsilat, 'dekont-'.$tahsilat->id, 'Dekont indirildi');
    }

    public function giderBelgesi(Gider $gider): StreamedResponse
    {
        Gate::authorize('view', $gider);

        return $this->gonder($gider->belge_yolu, $gider, 'gider-'.$gider->id, 'Gider belgesi indirildi');
    }

    public function siteBelgesi(SiteBelgesi $belge): StreamedResponse
    {
        Gate::authorize('view', $belge);

        return $this->gonder($belge->yol, $belge, $belge->orijinal_ad ?: null, 'Site belgesi indirildi: '.$belge->ad.' (sürüm '.$belge->surum.')');
    }

    public function isEmriFotografi(IsEmri $isEmri, string $alan, int $sira): StreamedResponse
    {
        Gate::authorize('view', $isEmri);
        abort_unless(in_array($alan, ['fotograflar', 'tamamlanma_fotograflari'], true), 404);

        $yol = array_values((array) ($isEmri->{$alan} ?? []))[$sira] ?? null;

        return $this->gonder($yol, $isEmri, null, null, satirIci: true);
    }

    private function gonder(?string $yol, $kayit, ?string $ad, ?string $ozet, bool $satirIci = false): StreamedResponse
    {
        $disk = Storage::disk('local');
        abort_unless($yol && $disk->exists($yol), 404, 'Dosya bulunamadı.');

        if ($ozet !== null) {
            IslemKaydi::yaz('belge_indirildi', $kayit, null, null, $ozet);
        }

        $uzanti = pathinfo($yol, PATHINFO_EXTENSION);
        $ad = $ad ? (str_contains($ad, '.') ? $ad : $ad.'.'.$uzanti) : basename($yol);

        return $satirIci
            ? $disk->response($yol, $ad, self::BASLIKLAR)
            : $disk->download($yol, $ad, self::BASLIKLAR);
    }
}
