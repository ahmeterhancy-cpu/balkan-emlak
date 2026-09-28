<?php

use App\Models\Ayar;

if (! function_exists('ayar')) {
    /** Site ayarı (panel → Site Ayarları). Boş değer varsayılana döner. */
    function ayar(string $anahtar, mixed $varsayilan = null): mixed
    {
        return Ayar::al($anahtar, $varsayilan);
    }
}

if (! function_exists('tel_baglanti')) {
    /** "0505 393 07 22" → "tel:+905053930722" */
    function tel_baglanti(?string $telefon): string
    {
        $rakam = preg_replace('/\D/', '', (string) $telefon);
        if (str_starts_with($rakam, '0')) {
            $rakam = '90'.substr($rakam, 1);
        } elseif (strlen($rakam) === 10) {
            $rakam = '90'.$rakam;
        }

        return 'tel:+'.$rakam;
    }
}

if (! function_exists('wa_baglanti')) {
    /** WhatsApp sohbet bağlantısı (isteğe bağlı hazır mesajla). */
    function wa_baglanti(?string $telefon, ?string $mesaj = null): string
    {
        $numara = substr(tel_baglanti($telefon), 5);

        return 'https://wa.me/'.$numara.($mesaj ? '?text='.rawurlencode($mesaj) : '');
    }
}

if (! function_exists('tarih_tr')) {
    /** 12 Haziran 2026 */
    function tarih_tr(?DateTimeInterface $tarih, bool $saat = false): string
    {
        if (! $tarih) {
            return '';
        }

        return Illuminate\Support\Carbon::instance($tarih)
            ->timezone(config('app.timezone'))
            ->locale('tr')
            ->translatedFormat($saat ? 'j F Y H:i' : 'j F Y');
    }
}

if (! function_exists('site_gorsel')) {
    /** Panelden yüklenen site görseli; yoksa public/img altındaki varsayılan. */
    function site_gorsel(string $anahtar, string $varsayilan): string
    {
        $yol = ayar($anahtar);

        return $yol ? Illuminate\Support\Facades\Storage::disk('medya')->url($yol) : asset('img/'.$varsayilan);
    }
}
