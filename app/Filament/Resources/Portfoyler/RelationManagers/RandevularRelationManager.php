<?php

namespace App\Filament\Resources\Portfoyler\RelationManagers;

use App\Filament\Resources\Randevular\RandevuResource;
use App\Models\Randevu;
use App\Support\Kapsam;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Bu ilan için randevular (salt okunur, kullanıcının kapsamıyla). */
class RandevularRelationManager extends RelationManager
{
    protected static string $relationship = 'randevular';

    protected static ?string $title = 'Randevular';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('viewAny', Randevu::class) && Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => Kapsam::randevular($query))
            ->defaultSort('baslangic', 'desc')
            ->recordUrl(fn (Randevu $record) => Gate::allows('update', $record) ? RandevuResource::getUrl('edit', ['record' => $record]) : null)
            ->columns([
                TextColumn::make('baslangic')->label('Zaman')->dateTime('d.m.Y H:i'),
                TextColumn::make('musteri_adi')->label('Müşteri'),
                TextColumn::make('danisman.name')->label('Danışman')->placeholder('—'),
                TextColumn::make('durum')->label('Durum')->badge(),
            ]);
    }
}
