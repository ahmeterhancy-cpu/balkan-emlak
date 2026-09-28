<?php

namespace App\Filament\Resources\Siteler\RelationManagers;

use App\Models\Blok;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class BloklarRelationManager extends RelationManager
{
    protected static string $relationship = 'bloklar';

    protected static ?string $title = 'Bloklar';

    protected static ?string $modelLabel = 'blok';

    public function isReadOnly(): bool
    {
        return ! Gate::allows('update', $this->getOwnerRecord());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('ad')->label('Blok adı')->required()->maxLength(60)->placeholder('A Blok')
                ->unique(table: 'bloklar', column: 'ad', ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('site_id', $this->getOwnerRecord()->id)),
            TextInput::make('sira')->label('Sıra')->numeric()->integer()->minValue(0)->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('bolumler'))
            ->columns([
                TextColumn::make('ad')->label('Blok'),
                TextColumn::make('bolumler_count')->label('Bağımsız bölüm')->alignEnd(),
                TextColumn::make('sira')->label('Sıra')->alignEnd(),
            ])
            ->headerActions([
                CreateAction::make()->label('Blok ekle'),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle'),
                // Bölümü olan blok silinmez (bölümler bloksuz kalırdı)
                DeleteAction::make()->visible(fn (Blok $record) => (int) $record->bolumler_count === 0),
            ])
            ->emptyStateHeading('Blok yok')
            ->emptyStateDescription('Blok yoksa (tek bina apartman) bölümleri doğrudan ekleyebilirsiniz.');
    }
}
