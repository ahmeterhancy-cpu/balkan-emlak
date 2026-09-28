<?php

namespace App\Filament\Resources\BagimsizBolumler\RelationManagers;

use App\Models\BolumIliskisi;
use App\Models\Kisi;
use App\Support\Kapsam;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Malik / kiracı ilişkileri (şartname 8.1). Kayıt silinmez, üzerine
 * yazılmaz: taşınmada "İlişkiyi sonlandır" ile bitiş girilir, yeni kişi
 * yeni kayıtla eklenir. Geçmiş dönem borçlarının sorumlusu değişmez.
 */
class IliskilerRelationManager extends RelationManager
{
    protected static string $relationship = 'iliskiler';

    protected static ?string $title = 'Malikler ve kiracılar';

    protected static ?string $modelLabel = 'ilişki';

    public function isReadOnly(): bool
    {
        return ! Gate::allows('update', $this->getOwnerRecord());
    }

    /**
     * Seçilebilir kişiler: kullanıcının kişi kapsamı + erişebildiği sitelerde
     * zaten malik/kiracı olan kişiler. Emlak müşteri listesinin tamamı açılmaz.
     */
    public static function kisiSorgusu(?string $arama = null): Builder
    {
        $siteIdleri = Kapsam::siteIdleri();

        return Kisi::query()
            ->where(function (Builder $q) use ($siteIdleri) {
                $q->whereIn('id', Kapsam::kisiler(Kisi::query())->select('id'))
                    ->orWhereIn('id', BolumIliskisi::query()
                        ->when($siteIdleri !== null, fn ($w) => $w->whereIn('site_id', $siteIdleri))
                        ->select('kisi_id'));
            })
            ->when($arama, fn ($q) => $q->where(fn ($w) => $w->where('ad_soyad', 'like', "%{$arama}%")->orWhere('telefon', 'like', "%{$arama}%")))
            ->orderBy('ad_soyad')
            ->limit(50);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Select::make('kisi_id')
                ->label('Kişi')
                ->required()
                ->searchable()
                ->getSearchResultsUsing(fn (string $search) => static::kisiSorgusu($search)->get()->mapWithKeys(fn (Kisi $k) => [$k->id => trim($k->ad_soyad.' '.($k->telefon ? '· '.$k->telefon : ''))])->all())
                ->options(fn () => static::kisiSorgusu()->get()->mapWithKeys(fn (Kisi $k) => [$k->id => trim($k->ad_soyad.' '.($k->telefon ? '· '.$k->telefon : ''))])->all())
                ->getOptionLabelUsing(fn ($value) => Kisi::find($value)?->ad_soyad)
                ->createOptionModalHeading('Yeni kişi')
                ->createOptionForm([
                    TextInput::make('ad_soyad')->label('Ad soyad / kurum adı')->required()->maxLength(255),
                    Select::make('tur')->label('Tür')->options(Kisi::TURLER)->default('bireysel')->required(),
                    TextInput::make('telefon')->label('Telefon')->tel()->maxLength(30),
                    TextInput::make('eposta')->label('E-posta')->email()->maxLength(255),
                ])
                ->createOptionUsing(fn (array $data): int => Kisi::create([...$data, 'olusturan_id' => auth()->id()])->id)
                ->columnSpan(2),
            Select::make('rol')->label('Rol')->options(BolumIliskisi::ROLLER)->required()->default('malik'),
            DatePicker::make('baslangic')->label('Başlangıç')->required()->native(false)->displayFormat('d.m.Y')->default(fn () => now()->toDateString()),
            DatePicker::make('bitis')->label('Bitiş')->native(false)->displayFormat('d.m.Y')->afterOrEqual('baslangic'),
            Toggle::make('borc_sorumlusu')->label('Borç sorumlusu')->inline(false)
                ->helperText('İşaretli değilse etkin malik sorumludur.'),
            TextInput::make('hisse_pay')->label('Hisse (pay)')->numeric()->integer()->minValue(1),
            TextInput::make('hisse_payda')->label('Hisse (payda)')->numeric()->integer()->minValue(1)->requiredWith('hisse_pay'),
            Textarea::make('notlar')->label('Notlar')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('kisi'))
            ->columns([
                TextColumn::make('kisi.ad_soyad')->label('Kişi')->description(fn (BolumIliskisi $record) => $record->kisi?->telefon),
                TextColumn::make('rol')->label('Rol')->badge()->formatStateUsing(fn ($state) => BolumIliskisi::ROLLER[$state] ?? $state)
                    ->color(fn ($state) => $state === 'malik' ? 'primary' : 'info'),
                TextColumn::make('hisse')->label('Hisse')
                    ->state(fn (BolumIliskisi $record) => $record->hisse_pay ? $record->hisse_pay.'/'.$record->hisse_payda : null)->placeholder('—'),
                TextColumn::make('baslangic')->label('Başlangıç')->date('d.m.Y'),
                TextColumn::make('bitis')->label('Bitiş')->date('d.m.Y')->placeholder('Devam ediyor'),
                IconColumn::make('borc_sorumlusu')->label('Borç sorumlusu')->boolean(),
                TextColumn::make('durum')->label('Durum')
                    ->state(fn (BolumIliskisi $record) => $record->etkinMi() ? 'Etkin' : ($record->baslangic->isFuture() ? 'Başlamadı' : 'Sona erdi'))
                    ->badge()->color(fn ($state) => $state === 'Etkin' ? 'success' : 'gray'),
            ])
            ->headerActions([
                CreateAction::make()->label('Malik / kiracı ekle')
                    ->mutateDataUsing(fn (array $data) => [...$data, 'site_id' => $this->getOwnerRecord()->site_id]),
            ])
            ->recordActions([
                Action::make('sonlandir')
                    ->label('İlişkiyi sonlandır')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('warning')
                    ->visible(fn (BolumIliskisi $record) => $record->bitis === null)
                    ->authorize(fn (BolumIliskisi $record) => Gate::allows('update', $record))
                    ->modalDescription('Kayıt silinmez; bitiş tarihi girilir. Bu tarihten sonraki borçlandırmalarda bu kişi sorumlu olmaz.')
                    ->schema([
                        DatePicker::make('bitis')->label('Bitiş tarihi')->required()->native(false)->displayFormat('d.m.Y')->default(fn () => now()->toDateString()),
                    ])
                    ->action(function (BolumIliskisi $record, array $data) {
                        try {
                            $record->update(['bitis' => $data['bitis']]);
                            Notification::make()->success()->title('İlişki sonlandırıldı')->send();
                        } catch (DomainException $e) {
                            Notification::make()->danger()->title('İşlem yapılamadı')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->defaultSort('baslangic', 'desc')
            ->emptyStateHeading('Malik / kiracı kaydı yok')
            ->emptyStateDescription('Borçlandırmada sorumlu kişi bulunamaz; önce malik ekleyin.');
    }
}
