<?php

namespace App\Filament\Resources\Kisiler\RelationManagers;

use App\Filament\Resources\Portfoyler\PortfoyResource;
use App\Models\Portfoy;
use App\Support\Kapsam;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Kişinin sahibi olduğu portföyler (salt okunur, kapsamlı). */
class PortfoylerRelationManager extends RelationManager
{
    protected static string $relationship = 'portfoyler';

    protected static ?string $title = 'Sahibi olduğu portföyler';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('viewAny', Portfoy::class);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => Kapsam::portfoyler($query))
            ->recordUrl(fn (Portfoy $record) => PortfoyResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('portfoy_no')->label('No'),
                TextColumn::make('baslik')->label('Başlık')->limit(50),
                TextColumn::make('fiyat')->label('Fiyat')->state(fn (Portfoy $record) => $record->fiyatMetni()),
                TextColumn::make('operasyon_durumu')->label('Operasyon')->badge(),
                TextColumn::make('yayin_durumu')->label('Yayın')->badge(),
            ]);
    }
}
