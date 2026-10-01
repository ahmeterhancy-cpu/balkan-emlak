<?php

namespace App\Filament\Pages;

use App\Filament\Destek\SiteAlanlari;
use App\Support\Donem;
use App\Support\Yetki;
use Filament\Forms\Components\Select;
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

    protected static ?string $title = 'Panel';

    protected static ?string $navigationLabel = 'Panel';

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

    /** Gelecek ay + son 24 ay, yeniden eskiye. @return array<string, string> */
    public static function donemSecenekleri(): array
    {
        $secenekler = [];
        $ay = now()->startOfMonth()->addMonth();
        for ($i = 0; $i < 26; $i++, $ay->subMonth()) {
            $secenekler[$ay->format('Y-m')] = Donem::ad($ay->format('Y-m'));
        }

        return $secenekler;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            // İnce süzgeç çubuğu: alttaki site kartları bu seçime göre hesaplanır
            Section::make()
                ->compact()
                ->extraAttributes(['class' => 'bp-suzgec'])
                ->visible(fn () => static::siteGostergeleri())
                ->columns(['default' => 1, 'md' => 4])
                ->columnSpanFull()
                ->schema([
                    Select::make('site_id')
                        ->label('Site')
                        ->prefixIcon('heroicon-o-building-office-2')
                        ->options(fn () => SiteAlanlari::siteler())
                        ->placeholder('Tüm yetkili siteler')
                        ->default(fn () => SiteAlanlari::tekSiteId())
                        ->columnSpan(['md' => 2]),
                    Select::make('donem')
                        ->label('Dönem')
                        ->prefixIcon('heroicon-o-calendar')
                        ->options(fn () => static::donemSecenekleri())
                        ->placeholder('Bu ay ('.Donem::ad(now()->format('Y-m')).')')
                        ->columnSpan(['md' => 1]),
                ]),
        ]);
    }
}
