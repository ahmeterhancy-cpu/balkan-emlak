<?php

namespace App\Filament\Widgets;

use App\Enums\Islem;
use App\Enums\RandevuDurumu;
use App\Enums\TalepDurumu;
use App\Enums\YayinDurumu;
use App\Filament\Resources\Portfoyler\PortfoyResource;
use App\Filament\Resources\Talepler\TalepResource;
use App\Models\Portfoy;
use App\Models\Randevu;
use App\Models\Talep;
use App\Support\Kapsam;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;

/** Emlak özet kartları; danışman için yalnız kendi kayıtları sayılır. */
class EmlakOzeti extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return Gate::allows('viewAny', Portfoy::class);
    }

    /** Taslak/incelemede olup yayın için eksiği olan portföy sayısı. */
    public static function eksikBilgiliSayisi(): int
    {
        return Kapsam::portfoyler(Portfoy::query())
            ->whereIn('yayin_durumu', [YayinDurumu::Taslak->value, YayinDurumu::Incelemede->value])
            ->get()
            ->filter(fn (Portfoy $p) => $p->yayinEksikleri() !== [])
            ->count();
    }

    protected function getStats(): array
    {
        $portfoy = fn () => Kapsam::portfoyler(Portfoy::query());
        $liste = PortfoyResource::getUrl('index');

        $kartlar = [
            Stat::make('Yayındaki satılık', $portfoy()->yayinda()->where('islem', Islem::Satilik->value)->count())
                ->icon('heroicon-o-home')
                ->url($liste),
            Stat::make('Yayındaki kiralık', $portfoy()->yayinda()->where('islem', Islem::Kiralik->value)->count())
                ->icon('heroicon-o-key')
                ->url($liste),
        ];

        if (Gate::allows('viewAny', Talep::class)) {
            $kartlar[] = Stat::make('Yeni talepler', Kapsam::talepler(Talep::query())->where('durum', TalepDurumu::Yeni->value)->count())
                ->description('Henüz dönülmemiş')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('warning')
                ->url(TalepResource::getUrl('index'));
        }

        if (Gate::allows('viewAny', Randevu::class)) {
            $kartlar[] = Stat::make('7 gün içindeki randevular', Kapsam::randevular(Randevu::query())
                ->where('durum', RandevuDurumu::Planlandi->value)
                ->whereBetween('baslangic', [now(), now()->addDays(7)])
                ->count())
                ->icon('heroicon-o-calendar-days');
        }

        $kartlar[] = Stat::make('İncelemede bekleyen ilan', $portfoy()->where('yayin_durumu', YayinDurumu::Incelemede->value)->count())
            ->description('Yayın onayı bekliyor')
            ->icon('heroicon-o-clipboard-document-check');

        $kartlar[] = Stat::make('Eksik bilgili portföy', static::eksikBilgiliSayisi())
            ->description('Taslak / incelemede, yayın için eksiği var')
            ->icon('heroicon-o-exclamation-triangle')
            ->color('danger');

        return $kartlar;
    }
}
