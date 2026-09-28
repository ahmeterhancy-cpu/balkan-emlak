<?php

namespace App\Filament\Resources\Giderler\Pages;

use App\Filament\Resources\Giderler\GiderResource;
use App\Models\Gider;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ViewGider extends ViewRecord
{
    protected static string $resource = GiderResource::class;

    public function getTitle(): string
    {
        /** @var Gider $g */
        $g = $this->getRecord();

        return $g->aciklama.' — '.$g->site?->ad;
    }

    protected function getHeaderActions(): array
    {
        return [
            ...GiderResource::eylemler(),
            Action::make('belge')
                ->label('Belgeyi indir')
                ->icon(Heroicon::OutlinedPaperClip)
                ->color('gray')
                ->visible(fn (Gider $record) => filled($record->belge_yolu) && Gate::allows('view', $record))
                ->url(fn (Gider $record) => route('panel.gider-belgesi.indir', $record), shouldOpenInNewTab: true),
            EditAction::make()->label('Düzenle'),
        ];
    }
}
