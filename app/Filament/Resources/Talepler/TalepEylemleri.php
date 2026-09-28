<?php

namespace App\Filament\Resources\Talepler;

use App\Enums\RandevuDurumu;
use App\Enums\TalepDurumu;
use App\Filament\Destek\Alanlar;
use App\Filament\Resources\Randevular\RandevuResource;
use App\Models\Kisi;
use App\Models\Portfoy;
use App\Models\Randevu;
use App\Models\Talep;
use App\Support\Kapsam;
use App\Support\Yetki;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/** Talep kaydı üzerindeki iş akışı eylemleri. */
class TalepEylemleri
{
    public static function banaAta(): Action
    {
        return Action::make('banaAta')
            ->label('Bana ata')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('gray')
            ->authorize(fn (Talep $record): bool => Gate::allows('ata', $record))
            ->visible(fn (Talep $record): bool => $record->atanan_id !== auth()->id())
            ->action(function (Talep $record, $livewire): void {
                $record->update(['atanan_id' => auth()->id()]);
                static::tazele($livewire, ['atanan_id']);
                Notification::make()->success()->title('Talep size atandı')->send();
            });
    }

    /**
     * Talebi bir kişi kaydına bağlar: olası mükerrer kayıt varsa önerilir,
     * seçilmezse talepteki bilgilerle yeni kişi açılır. Otomatik birleştirme yok.
     */
    public static function kisiyeBagla(): Action
    {
        return Action::make('kisiyeBagla')
            ->label(fn (Talep $record) => $record->kisi_id ? 'Kişi kaydını değiştir' : 'Kişi kaydına bağla')
            ->icon(Heroicon::OutlinedIdentification)
            ->color('gray')
            ->authorize(fn (Talep $record): bool => Gate::allows('update', $record) && Gate::allows('create', Kisi::class))
            ->modalHeading('Kişi kaydına bağla / oluştur')
            ->modalDescription('Aynı telefon veya e-postayla kayıtlı kişi varsa aşağıda önerilir. Seçmezseniz talepteki bilgilerle yeni kişi kaydı oluşturulur.')
            ->fillForm(function (Talep $record): array {
                $aday = Kisi::olasiMukerrerler($record->telefon, $record->eposta)
                    ->first(fn (Kisi $k) => Kapsam::kisiler(Kisi::query())->whereKey($k->id)->exists());

                return ['kisi_id' => $record->kisi_id ?? $aday?->id];
            })
            ->schema([
                Select::make('kisi_id')
                    ->label('Mevcut kişi')
                    ->placeholder('Yeni kişi oluştur')
                    ->searchable()
                    ->options(fn () => Alanlar::kisiSecenekleri())
                    ->getSearchResultsUsing(fn (string $search) => Alanlar::kisiSecenekleri($search))
                    ->getOptionLabelUsing(fn ($value) => ($k = Kisi::find($value)) ? Alanlar::kisiEtiketi($k) : null),
            ])
            ->action(function (Talep $record, array $data, $livewire): void {
                $kisiId = $data['kisi_id'] ?? null;

                if ($kisiId) {
                    abort_unless(Kapsam::kisiler(Kisi::query())->whereKey($kisiId)->exists(), 403);
                    $baslik = 'Talep mevcut kişi kaydına bağlandı';
                } else {
                    $kisi = Kisi::create([
                        'tur' => 'bireysel',
                        'ad_soyad' => $record->ad_soyad,
                        'telefon' => $record->telefon,
                        'eposta' => $record->eposta,
                        'iletisim_tercihi' => array_key_exists($record->detay['iletisim_tercihi'] ?? '', Kisi::ILETISIM_TERCIHLERI)
                            ? $record->detay['iletisim_tercihi'] : null,
                        'pazarlama_izni' => (bool) $record->pazarlama_izni,
                        'olusturan_id' => auth()->id(),
                    ]);
                    $kisiId = $kisi->id;
                    $baslik = 'Yeni kişi kaydı oluşturuldu ve bağlandı';
                }

                $record->update(['kisi_id' => $kisiId]);
                $record->unsetRelation('kisi');
                Notification::make()->success()->title($baslik)->send();
            });
    }

    public static function randevuOlustur(): Action
    {
        return Action::make('randevuOlustur')
            ->label('Randevu oluştur')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('primary')
            ->authorize(fn (Talep $record): bool => Gate::allows('update', $record) && Gate::allows('create', Randevu::class))
            ->modalHeading('Randevu oluştur')
            ->modalDescription('Randevu kaydedilince talebin durumu "Randevu planlandı" olur.')
            ->fillForm(fn (Talep $record): array => [
                'baslangic' => $record->tercih_edilen_zaman?->format('Y-m-d H:i:s'),
                'sure_dk' => 60,
                'portfoy_id' => $record->portfoy_id,
                'danisman_id' => $record->atanan_id ?? auth()->id(),
            ])
            ->schema([
                DateTimePicker::make('baslangic')->label('Başlangıç')->seconds(false)->native(false)->displayFormat('d.m.Y H:i')->required(),
                TextInput::make('sure_dk')->label('Süre (dakika)')->numeric()->minValue(5)->maxValue(600)->required(),
                Select::make('portfoy_id')->label('Portföy')->options(fn () => RandevuResource::portfoySecenekleri())->searchable(),
                Alanlar::danisman('danisman_id', 'Danışman', 'randevu.goruntule_tumu'),
            ])
            ->action(function (Talep $record, array $data, $livewire): void {
                $danisman = Yetki::var('randevu.goruntule_tumu') ? ($data['danisman_id'] ?? auth()->id()) : auth()->id();
                $portfoy = $data['portfoy_id'] ?? null;
                abort_if($portfoy && ! Kapsam::portfoyler(Portfoy::query())->whereKey($portfoy)->exists(), 403);

                Randevu::create([
                    'talep_id' => $record->id,
                    'portfoy_id' => $portfoy,
                    'danisman_id' => $danisman,
                    'musteri_adi' => $record->ad_soyad,
                    'musteri_telefon' => $record->telefon,
                    'baslangic' => $data['baslangic'],
                    'sure_dk' => (int) $data['sure_dk'],
                    'durum' => RandevuDurumu::Planlandi,
                ]);

                $record->update(['durum' => TalepDurumu::RandevuPlanlandi]);
                static::tazele($livewire, ['durum']);

                Notification::make()->success()->title('Randevu oluşturuldu')->send();
            });
    }

    private static function tazele(mixed $livewire, array $alanlar): void
    {
        if (is_object($livewire) && method_exists($livewire, 'refreshFormData')) {
            $livewire->refreshFormData($alanlar);
        }
    }
}
