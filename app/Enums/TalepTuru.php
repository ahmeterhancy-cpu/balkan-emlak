<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Başvuru / talep türü. İlk dördü web formlarından gelir. */
enum TalepTuru: string implements HasColor, HasLabel
{
    case Iletisim = 'iletisim';
    case IlanBilgi = 'ilan_bilgi';
    case PortfoyBasvurusu = 'portfoy_basvurusu';
    case YonetimTeklifi = 'yonetim_teklifi';
    case MusteriTalebi = 'musteri_talebi';

    public function getLabel(): string
    {
        return match ($this) {
            self::Iletisim => 'Genel iletişim',
            self::IlanBilgi => 'İlan bilgi / randevu',
            self::PortfoyBasvurusu => 'Portföy başvurusu',
            self::YonetimTeklifi => 'Yönetim teklifi',
            self::MusteriTalebi => 'Müşteri arayışı',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Iletisim => 'gray',
            self::IlanBilgi => 'info',
            self::PortfoyBasvurusu => 'success',
            self::YonetimTeklifi => 'warning',
            self::MusteriTalebi => 'primary',
        };
    }

    /** @return array<string, string> değer => etiket */
    public static function secenekler(): array
    {
        $sonuc = [];
        foreach (self::cases() as $durum) {
            $sonuc[$durum->value] = $durum->getLabel();
        }

        return $sonuc;
    }
}
