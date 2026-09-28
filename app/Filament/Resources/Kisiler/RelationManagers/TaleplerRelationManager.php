<?php

namespace App\Filament\Resources\Kisiler\RelationManagers;

use App\Filament\Resources\Talepler\TalepResource;
use App\Models\Talep;
use App\Support\Kapsam;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Kişiye bağlı talepler (salt okunur, kapsamlı). */
class TaleplerRelationManager extends RelationManager
{
    protected static string $relationship = 'talepler';

    protected static ?string $title = 'Talepler';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('viewAny', Talep::class);
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
            ->recordUrl(fn (Talep $record) => TalepResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i'),
                TextColumn::make('tur')->label('Tür')->badge(),
                TextColumn::make('durum')->label('Durum')->badge(),
                TextColumn::make('portfoy.portfoy_no')->label('İlan')->placeholder('—'),
                TextColumn::make('atanan.name')->label('Atanan')->placeholder('—'),
            ]);
    }
}
