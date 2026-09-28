<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Gayrimenkul ana kategorisi. Alt tipler ve kategoriye özgü alanlar buradan
 * okunur: oda sayısı gibi konut alanları arsa ilanında ne formda ne de
 * vitrindeki süzgeçte görünür (şartname 5.3).
 */
enum Kategori: string implements HasLabel
{
    case Konut = 'konut';
    case Arsa = 'arsa';
    case Ticari = 'ticari';

    public function getLabel(): string
    {
        return match ($this) {
            self::Konut => 'Konut',
            self::Arsa => 'Arsa',
            self::Ticari => 'Ticari',
        };
    }

    /** @return array<string, string> tip => etiket */
    public function tipler(): array
    {
        return match ($this) {
            self::Konut => [
                'daire' => 'Daire',
                'mustakil' => 'Müstakil ev',
                'villa' => 'Villa',
                'rezidans' => 'Rezidans',
                'yazlik' => 'Yazlık',
                'dubleks' => 'Dubleks',
            ],
            self::Arsa => [
                'imarli_arsa' => 'İmarlı arsa',
                'tarla' => 'Tarla',
                'bag_bahce' => 'Bağ / bahçe',
                'zeytinlik' => 'Zeytinlik',
            ],
            self::Ticari => [
                'dukkan' => 'Dükkân',
                'ofis' => 'Ofis',
                'depo' => 'Depo',
                'isyeri' => 'İş yeri',
                'bina' => 'Komple bina',
            ],
        };
    }

    /** Oda, banyo, kat, bina yaşı, ısıtma gibi yapı alanları anlamlı mı? */
    public function yapiAlanlari(): bool
    {
        return $this !== self::Arsa;
    }

    /** Oda sayısı yalnız konutta anlamlı. */
    public function odaAlani(): bool
    {
        return $this === self::Konut;
    }

    /** Ada / parsel / imar alanları. */
    public function arsaAlanlari(): bool
    {
        return $this === self::Arsa;
    }

    /** @return array<string, string> */
    public static function secenekler(): array
    {
        $sonuc = [];
        foreach (self::cases() as $k) {
            $sonuc[$k->value] = $k->getLabel();
        }

        return $sonuc;
    }

    /** Tüm tipler düz liste (tablo etiketi için). */
    public static function tipEtiketi(?string $tip): ?string
    {
        foreach (self::cases() as $k) {
            if (isset($k->tipler()[$tip])) {
                return $k->tipler()[$tip];
            }
        }

        return $tip;
    }
}
