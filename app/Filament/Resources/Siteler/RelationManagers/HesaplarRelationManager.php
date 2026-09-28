<?php

namespace App\Filament\Resources\Siteler\RelationManagers;

use App\Filament\Resources\Hesaplar\HesapResource;
use App\Models\Hesap;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/** Sitenin kasa / banka hesapları (ayrıntı ve hareketler Kasa / Banka ekranında). */
class HesaplarRelationManager extends RelationManager
{
    protected static string $relationship = 'hesaplar';

    protected static ?string $title = 'Kasa / banka hesapları';

    protected static ?string $modelLabel = 'hesap';

    public function isReadOnly(): bool
    {
        return ! Gate::allows('create', Hesap::class);
    }

    public function form(Schema $schema): Schema
    {
        return HesapResource::hesapFormu($schema, siteSec: false);
    }

    public function table(Table $table): Table
    {
        return HesapResource::table($table)
            ->filters([])
            ->headerActions([
                CreateAction::make()->label('Hesap ekle'),
            ])
            ->recordActions([
                ViewAction::make()->label('Aç')->url(fn (Hesap $record) => HesapResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
