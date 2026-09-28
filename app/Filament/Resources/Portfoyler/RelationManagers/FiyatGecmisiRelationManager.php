<?php

namespace App\Filament\Resources\Portfoyler\RelationManagers;

use App\Models\FiyatGecmisi;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Fiyat değişiklikleri; yalnız okunur (kayıtlar model olayıyla eklenir). */
class FiyatGecmisiRelationManager extends RelationManager
{
    protected static string $relationship = 'fiyatGecmisi';

    protected static ?string $title = 'Fiyat geçmişi';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i'),
                TextColumn::make('eski')->label('Eski fiyat')->state(fn (FiyatGecmisi $record) => $record->eski_fiyat_kurus === null ? 'İlk fiyat' : $record->eskiMetin()),
                TextColumn::make('yeni')->label('Yeni fiyat')->state(fn (FiyatGecmisi $record) => $record->yeniMetin())->weight('bold'),
                TextColumn::make('kullanici.name')->label('Kullanıcı')->placeholder('Sistem'),
            ])
            ->paginated([10, 25]);
    }
}
