<?php

namespace App\Filament\Resources\Talepler\Pages;

use App\Enums\TalepTuru;
use App\Filament\Resources\Talepler\TalepResource;
use App\Models\Talep;
use App\Support\Kapsam;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTalepler extends ListRecords
{
    protected static string $resource = TalepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Yeni talep'),
        ];
    }

    /** Türe göre sekmeler; sayılar kullanıcının kapsamıyla. */
    public function getTabs(): array
    {
        $sayilar = Kapsam::talepler(Talep::query())
            ->selectRaw('tur, COUNT(*) as adet')
            ->groupBy('tur')
            ->pluck('adet', 'tur');

        $sekmeler = [
            'tumu' => Tab::make('Tümü')->badge((string) $sayilar->sum()),
        ];

        $sira = [
            TalepTuru::PortfoyBasvurusu,
            TalepTuru::YonetimTeklifi,
            TalepTuru::IlanBilgi,
            TalepTuru::Iletisim,
            TalepTuru::MusteriTalebi,
        ];

        foreach ($sira as $tur) {
            $sekmeler[$tur->value] = Tab::make($tur->getLabel())
                ->badge((string) ($sayilar[$tur->value] ?? 0))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('tur', $tur->value));
        }

        return $sekmeler;
    }
}
