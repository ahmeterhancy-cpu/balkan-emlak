<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\IslemKaydi;
use App\Models\PortfoyBelgesi;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Özel belge indirme. Belgeler `local` diskinde (storage/app/private) durur
 * ve web kökünden erişilemez; tek yol bu rota. Her indirme politika
 * denetiminden geçer ve işlem geçmişine yazılır (şartname 7.1, 15).
 */
class BelgeController extends Controller
{
    public function __invoke(PortfoyBelgesi $belge): StreamedResponse
    {
        Gate::authorize('view', $belge);

        $disk = Storage::disk(PortfoyBelgesi::DISK);

        abort_unless($belge->yol && $disk->exists($belge->yol), 404, 'Belge dosyası bulunamadı.');

        IslemKaydi::yaz('belge_indirildi', $belge);

        $ad = $belge->orijinal_ad ?: basename($belge->yol);

        return $disk->download($belge->yol, $ad, [
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
