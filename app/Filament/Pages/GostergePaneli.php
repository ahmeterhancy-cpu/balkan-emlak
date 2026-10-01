<?php

namespace App\Filament\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Support\Donem;
use App\Support\Yetki;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Gösterge paneli (şartname 6.2). Widget'lar yetkiye göre görünür:
 * emlak kartları emlak rollerine, site kartları siteye erişen rollere,
 * saha personeline yalnız kendi iş emirleri ve bugünkü görevleri.
 *
 * Site kullanıcıları için üstte görünür site seçici ve dönem (oturumda
 * saklanır). Boş site = erişilebilen tüm siteler; tutarlar yine de para
 * birimine göre ayrıdır.
 */
class GostergePaneli extends Dashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Gösterge Paneli';

    protected static ?string $navigationLabel = 'Gösterge Paneli';

    public function getColumns(): int|array
    {
        return 2;
    }

    /** Site göstergelerini görecek kullanıcı mı (saha personeli hariç). */
    public static function siteGostergeleri(): bool
    {
        return (Yetki::var('finans.goruntule') || Yetki::var('operasyon.yonet') || Yetki::var('site_rapor'))
            && SiteAlanlari::siteler() !== [];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Site yönetimi göstergeleri')
                ->description('Hemen alttaki site finansı ve operasyon kartları burada seçtiğiniz site ve aya göre hesaplanır.')
                ->visible(fn () => static::siteGostergeleri())
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('site_id')
                        ->label('Site')
                        ->options(fn () => SiteAlanlari::siteler())
                        ->placeholder('Tüm yetkili siteler')
                        ->default(fn () => SiteAlanlari::tekSiteId()),
                    TextInput::make('donem')
                        ->label('Dönem (YYYY-AA)')
                        ->placeholder(now()->format('Y-m'))
                        ->regex('/^\d{4}-(0[1-9]|1[0-2])$/')
                        ->live(onBlur: true)
                        ->helperText(fn ($state) => Donem::gecerli((string) $state) ? Donem::ad($state) : Donem::ad(now()->format('Y-m'))),
                ]),
        ]);
    }
}
