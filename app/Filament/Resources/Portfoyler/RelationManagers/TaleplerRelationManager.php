<?php

namespace App\Filament\Resources\Portfoyler\RelationManagers;

use App\Filament\Resources\Talepler\TalepResource;
use App\Models\Talep;
use App\Support\Kapsam;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Bu ilana gelen talepler (salt okunur, kullanıcının kapsamıyla). */
class TaleplerRelationManager extends RelationManager
{
    protected static string $relationship = 'talepler';

    protected static ?string $title = 'Talepler';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('viewAny', Talep::class) && Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => Kapsam::talepler($query))
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Talep $record) => Gate::allows('update', $record) ? TalepResource::getUrl('edit', ['record' => $record]) : null)
            ->columns([
                TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i'),
                TextColumn::make('tur')->label('Tür')->badge(),
                TextColumn::make('ad_soyad')->label('Ad soyad'),
                TextColumn::make('durum')->label('Durum')->badge(),
                TextColumn::make('atanan.name')->label('Atanan')->placeholder('—'),
            ]);
    }
}
