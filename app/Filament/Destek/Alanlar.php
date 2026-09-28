<?php

namespace App\Filament\Destek;

use App\Models\Kisi;
use App\Models\User;
use App\Support\Kapsam;
use App\Support\Para;
use App\Support\Yetki;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;

/**
 * Panelde birden fazla kaynakta tekrarlanan form alanları.
 */
class Alanlar
{
    /**
     * Kuruş cinsinden saklanan tutar alanı.
     *
     * Formda "1.250.000", "1250000" ya da "1.250.000,50" yazılabilir;
     * kayda kuruş (125000000) gider, düzenlemede yine 1250000 görünür.
     * Alan bilerek `numeric()` DEĞİL: tarayıcı sayı kutusu Türkçe binlik
     * noktasını kabul etmez.
     */
    public static function para(string $ad, string $etiket): TextInput
    {
        return TextInput::make($ad)
            ->label($etiket)
            ->inputMode('decimal')
            ->placeholder('1.250.000')
            ->formatStateUsing(fn ($state) => is_numeric($state) ? Para::formaDegeri((int) $state) : $state)
            ->dehydrateStateUsing(fn ($state) => Para::kurusa($state))
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }
                $kurus = Para::kurusa($value);
                if ($kurus === null || $kurus < 0) {
                    $fail('Geçerli bir tutar girin (ör. 1.250.000 ya da 1250000,50).');
                }
            });
    }

    /** Sorumlu danışman seçimi; "tümünü görme" izni olmayan yalnız kendini görür. */
    public static function danisman(string $ad = 'danisman_id', string $etiket = 'Sorumlu danışman', string $izin = 'portfoy.goruntule_tumu'): Select
    {
        return Select::make($ad)
            ->label($etiket)
            ->options(fn (): array => Yetki::var($izin)
                ? User::query()->danismanlar()->pluck('name', 'id')->all()
                : User::query()->whereKey(auth()->id())->pluck('name', 'id')->all())
            ->default(fn () => auth()->id())
            ->searchable()
            ->disabled(fn (): bool => ! Yetki::var($izin))
            ->helperText(fn (): ?string => Yetki::var($izin) ? null : 'Kayıt size atanır.');
    }

    /** Kapsam içindeki kişilerden seçim (danışman başkasının müşterisini göremez). */
    public static function kisiSecenekleri(?string $arama = null): array
    {
        return Kapsam::kisiler(Kisi::query())
            ->when($arama, fn ($q) => $q->where(fn ($w) => $w
                ->where('ad_soyad', 'like', "%{$arama}%")
                ->orWhere('telefon', 'like', "%{$arama}%")
                ->orWhere('eposta', 'like', "%{$arama}%")))
            ->orderBy('ad_soyad')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Kisi $k) => [$k->id => static::kisiEtiketi($k)])
            ->all();
    }

    public static function kisiEtiketi(Kisi $k): string
    {
        return trim($k->ad_soyad.' '.($k->telefon ? '· '.$k->telefon : ''));
    }

    /**
     * Telefon / e-posta benzerliğine göre olası mükerrer kişi uyarısı.
     * Hiçbir zaman otomatik birleştirmez (şartname 7.2). Kullanıcının
     * kapsamı dışındaki kayıtların adı gösterilmez, yalnız sayısı.
     */
    public static function mukerrerUyarisi(string $telefonAlani = 'telefon', string $epostaAlani = 'eposta', bool $kendisiHaric = true): Callout
    {
        $bul = function (Get $get, ?Model $record) use ($telefonAlani, $epostaAlani, $kendisiHaric) {
            return Kisi::olasiMukerrerler(
                $get($telefonAlani),
                $get($epostaAlani),
                $kendisiHaric && $record instanceof Kisi ? $record->id : null,
            );
        };

        return Callout::make('Olası mükerrer kayıt')
            ->warning()
            ->description(function (Get $get, ?Model $record) use ($bul): string {
                $liste = $bul($get, $record);
                $gorunur = Kapsam::kisiler(Kisi::query())->whereKey($liste->modelKeys())->pluck('id')->all();

                $adlar = $liste->filter(fn (Kisi $k) => in_array($k->id, $gorunur, true))
                    ->map(fn (Kisi $k) => static::kisiEtiketi($k).($k->eposta ? ' · '.$k->eposta : ''))
                    ->values()->all();
                $gizli = $liste->count() - count($adlar);

                $metin = 'Aynı telefon veya e-postayla kayıtlı kişi var: '.($adlar ? implode('; ', $adlar) : '');
                if ($gizli > 0) {
                    $metin .= ($adlar ? ' ve ' : '').$gizli.' kayıt (erişiminiz dışında)';
                }

                return $metin.'. Kayıtlar otomatik birleştirilmez; aynı kişiyse mevcut kaydı kullanın.';
            })
            ->visible(fn (Get $get, ?Model $record): bool => $bul($get, $record)->isNotEmpty())
            ->columnSpanFull();
    }
}
