<?php

namespace App\Filament\Resources\Portfoyler;

use App\Filament\Resources\Portfoyler\Pages\CreatePortfoy;
use App\Filament\Resources\Portfoyler\Pages\EditPortfoy;
use App\Filament\Resources\Portfoyler\Pages\ListPortfoyler;
use App\Filament\Resources\Portfoyler\Pages\ViewPortfoy;
use App\Filament\Resources\Portfoyler\RelationManagers\BelgelerRelationManager;
use App\Filament\Resources\Portfoyler\RelationManagers\FiyatGecmisiRelationManager;
use App\Filament\Resources\Portfoyler\RelationManagers\MedyaRelationManager;
use App\Filament\Resources\Portfoyler\RelationManagers\RandevularRelationManager;
use App\Filament\Resources\Portfoyler\RelationManagers\TaleplerRelationManager;
use App\Filament\Resources\Portfoyler\Schemas\PortfoyForm;
use App\Filament\Resources\Portfoyler\Tables\PortfoylerTable;
use App\Models\Portfoy;
use App\Support\Kapsam;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Portföyler ve ilanlar. Liste ve doğrudan adres aynı kapsamdan geçer:
 * danışman başka danışmanın portföyünü adresini yazarak da açamaz (404).
 */
class PortfoyResource extends Resource
{
    protected static ?string $model = Portfoy::class;

    protected static ?string $slug = 'portfoyler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static ?string $navigationLabel = 'Portföyler ve İlanlar';

    protected static ?string $modelLabel = 'portföy';

    protected static ?string $pluralModelLabel = 'portföyler';

    protected static string|UnitEnum|null $navigationGroup = 'Emlak Yönetimi';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'baslik';

    public static function getEloquentQuery(): Builder
    {
        return Kapsam::portfoyler(parent::getEloquentQuery());
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['baslik', 'portfoy_no'];
    }

    public static function form(Schema $schema): Schema
    {
        return PortfoyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PortfoylerTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MedyaRelationManager::class,
            BelgelerRelationManager::class,
            FiyatGecmisiRelationManager::class,
            TaleplerRelationManager::class,
            RandevularRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortfoyler::route('/'),
            'create' => CreatePortfoy::route('/create'),
            'view' => ViewPortfoy::route('/{record}'),
            'edit' => EditPortfoy::route('/{record}/edit'),
        ];
    }
}
