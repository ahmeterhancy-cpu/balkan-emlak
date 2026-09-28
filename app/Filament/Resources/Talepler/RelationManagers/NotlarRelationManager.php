<?php

namespace App\Filament\Resources\Talepler\RelationManagers;

use App\Models\TalepNotu;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Görüşme notları. Not yazan ve zaman otomatik; yalnız yazan kendi notunu silebilir. */
class NotlarRelationManager extends RelationManager
{
    protected static string $relationship = 'notlar';

    protected static ?string $title = 'Görüşme notları';

    protected static ?string $modelLabel = 'not';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return parent::isReadOnly() || ! Gate::allows('update', $this->getOwnerRecord());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('not')->label('Not')->required()->rows(4)->maxLength(5000)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i'),
                TextColumn::make('kullanici.name')->label('Yazan')->placeholder('—'),
                TextColumn::make('not')->label('Not')->wrap(),
            ])
            ->headerActions([
                CreateAction::make()->label('Not ekle')->modalHeading('Görüşme notu ekle'),
            ])
            ->recordActions([
                DeleteAction::make()->visible(fn (TalepNotu $record) => $record->user_id === auth()->id()),
            ]);
    }
}
