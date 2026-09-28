<?php

namespace App\Filament\Resources\Giderler\RelationManagers;

use App\Filament\Destek\SiteAlanlari;
use App\Models\GiderOdemesi;
use App\Services\SiteFinans\GiderServisi;
use App\Support\Para;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/** Giderin ödemeleri; hatalı ödeme silinmez, "Geri al" karşı kaydı açar. */
class OdemelerRelationManager extends RelationManager
{
    protected static string $relationship = 'odemeler';

    protected static ?string $title = 'Ödemeler';

    protected static ?string $modelLabel = 'ödeme';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $geriAlinanlar = fn () => GiderOdemesi::where('gider_id', $this->getOwnerRecord()->id)->whereNotNull('iptal_edilen_id')->pluck('iptal_edilen_id')->all();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('hesap'))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('tarih')->label('Tarih')->date('d.m.Y'),
                TextColumn::make('hesap.ad')->label('Hesap'),
                TextColumn::make('tutar_kurus')->label('Tutar')->alignEnd()
                    ->formatStateUsing(fn ($state) => Para::bicimle((int) $state, $this->getOwnerRecord()->para_birimi))
                    ->color(fn ($state) => $state < 0 ? 'danger' : null),
                TextColumn::make('aciklama')->label('Açıklama')->placeholder('—')->wrap(),
                TextColumn::make('durum')->label('')
                    ->state(fn (GiderOdemesi $record) => $record->iptal_edilen_id ? 'Geri alma kaydı' : (in_array($record->id, $geriAlinanlar(), true) ? 'Geri alındı' : null))
                    ->badge()->color('gray'),
            ])
            ->recordActions([
                Action::make('geriAl')
                    ->label('Geri al')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (GiderOdemesi $record) => $record->tutar_kurus > 0 && ! in_array($record->id, $geriAlinanlar(), true))
                    ->authorize(fn () => Gate::allows('create', GiderOdemesi::class) && Gate::allows('view', $this->getOwnerRecord()))
                    ->modalHeading(fn () => $this->getOwnerRecord()->site?->ad.' — ödemeyi geri al')
                    ->modalDescription('Ödeme silinmez; eksi tutarlı karşı kayıt ve hesaba giriş yazılır.')
                    ->schema([
                        DatePicker::make('tarih')->label('Tarih')->required()->native(false)->displayFormat('d.m.Y')->default(fn () => now()->toDateString()),
                        Textarea::make('gerekce')->label('Gerekçe')->required()->rows(2)->maxLength(255),
                    ])
                    ->action(function (GiderOdemesi $record, array $data, Action $action) {
                        SiteAlanlari::erisimDenetle($this->getOwnerRecord()->site_id);
                        SiteAlanlari::calistir(fn () => app(GiderServisi::class)->odemeyiGeriAl($record, $data['gerekce'], $data['tarih']), 'Ödeme geri alındı', $action);
                    }),
            ])
            ->emptyStateHeading('Ödeme yok');
    }
}
