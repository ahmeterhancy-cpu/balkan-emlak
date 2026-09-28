<?php

namespace App\Filament\Resources\Borclandirmalar\RelationManagers;

use App\Filament\Resources\Borclar\BorcResource;
use App\Models\Borc;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Kesinleşen borçlandırmanın açtığı borçlar (salt okunur, düzeltme eylemiyle). */
class BorclarRelationManager extends RelationManager
{
    protected static string $relationship = 'borclar';

    protected static ?string $title = 'Açılan borçlar';

    protected static ?string $modelLabel = 'borç';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->durum === 'kesin' && Gate::allows('viewAny', Borc::class);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return BorcResource::table($table)
            ->modifyQueryUsing(fn ($query) => $query->asil()->bakiyeli()->with(['site', 'bolum.blok', 'kisi']))
            ->filters([])
            ->defaultSort('bagimsiz_bolum_id');
    }
}
