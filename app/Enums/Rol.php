<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Panel rolleri (şartname 9). İzinlerin kendisi App\Support\Yetki'de.
 *
 * Site yöneticisi, muhasebe sorumlusu ve saha personeli rolleri şimdiden
 * tanımlı; site ve apartman yönetimi modülü (sonraki tur) gelene kadar
 * emlak ve içerik tarafında hiçbir izinleri yok.
 */
enum Rol: string implements HasLabel
{
    case SistemYoneticisi = 'sistem_yoneticisi';
    case SirketYoneticisi = 'sirket_yoneticisi';
    case EmlakDanismani = 'emlak_danismani';
    case IcerikEditoru = 'icerik_editoru';
    case SiteYoneticisi = 'site_yoneticisi';
    case MuhasebeSorumlusu = 'muhasebe_sorumlusu';
    case SahaPersoneli = 'saha_personeli';

    public function getLabel(): string
    {
        return match ($this) {
            self::SistemYoneticisi => 'Sistem yöneticisi',
            self::SirketYoneticisi => 'Şirket yöneticisi',
            self::EmlakDanismani => 'Emlak danışmanı',
            self::IcerikEditoru => 'İçerik editörü',
            self::SiteYoneticisi => 'Site yöneticisi',
            self::MuhasebeSorumlusu => 'Muhasebe sorumlusu',
            self::SahaPersoneli => 'Saha personeli',
        };
    }

    /** @return array<string, string> */
    public static function secenekler(): array
    {
        $sonuc = [];
        foreach (self::cases() as $r) {
            $sonuc[$r->value] = $r->getLabel();
        }

        return $sonuc;
    }
}
