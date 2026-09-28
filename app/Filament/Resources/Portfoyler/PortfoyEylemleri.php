<?php

namespace App\Filament\Resources\Portfoyler;

use App\Enums\YayinDurumu;
use App\Models\Portfoy;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Yayın akışı eylemleri (şartname 7.1, 12): taslak → incelemede → yayında
 * → yayından kaldırıldı / arşiv. Aynı eylemler hem liste satırında hem
 * kayıt sayfasının üstünde kullanılır.
 *
 * Her eylem `authorize()` ile politikaya bağlı: yetkisiz kullanıcıda düğme
 * görünmez VE doğrudan Livewire çağrısı da reddedilir.
 */
class PortfoyEylemleri
{
    /** @return list<Action> */
    public static function hepsi(): array
    {
        return [
            static::incelemeyeGonder(),
            static::yayimla(),
            static::yayindanKaldir(),
            static::arsivle(),
            static::onizle(),
            static::sitedeGor(),
        ];
    }

    public static function incelemeyeGonder(): Action
    {
        return Action::make('incelemeyeGonder')
            ->label('İncelemeye gönder')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('warning')
            ->authorize('incelemeyeGonder')
            ->requiresConfirmation()
            ->modalHeading('İlanı incelemeye gönder')
            ->modalDescription('İlan, yayın yetkisi olan bir yöneticinin onayına düşer. İnceleme sırasında kaydı düzenlemeye devam edebilirsiniz.')
            ->modalSubmitActionLabel('Gönder')
            ->action(function (Portfoy $record, $livewire): void {
                $record->update(['yayin_durumu' => YayinDurumu::Incelemede]);
                static::formuTazele($livewire);

                $eksik = $record->yayinEksikleri();
                $bildirim = Notification::make()->title('İlan incelemeye gönderildi');

                if ($eksik !== []) {
                    $bildirim->warning()->body('Yayından önce tamamlanması gerekenler: '.implode(', ', $eksik).'.');
                } else {
                    $bildirim->success();
                }

                $bildirim->send();
            });
    }

    public static function yayimla(): Action
    {
        return Action::make('yayimla')
            ->label('Yayımla')
            ->icon(Heroicon::OutlinedGlobeAlt)
            ->color('success')
            ->authorize('yayimla')
            ->visible(fn (Portfoy $record): bool => $record->yayin_durumu !== YayinDurumu::Yayinda)
            ->requiresConfirmation()
            ->modalHeading('İlanı yayımla')
            ->modalDescription('Zorunlu alanlar ve en az bir fotoğraf denetlenir. Kaydedilmemiş form değişiklikleri yayına girmez; önce kaydedin.')
            ->modalSubmitActionLabel('Yayımla')
            ->action(function (Portfoy $record, $livewire): void {
                $eksik = $record->yayinEksikleri();

                if ($eksik !== []) {
                    Notification::make()
                        ->danger()
                        ->title('İlan yayımlanamadı')
                        ->body('Eksik bilgiler: '.implode(', ', $eksik).'.')
                        ->persistent()
                        ->send();

                    return;
                }

                $record->update(['yayin_durumu' => YayinDurumu::Yayinda]);
                static::formuTazele($livewire);

                Notification::make()->success()->title('İlan yayımlandı')->send();
            });
    }

    public static function yayindanKaldir(): Action
    {
        return Action::make('yayindanKaldir')
            ->label('Yayından kaldır')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('danger')
            ->authorize('yayimla')
            ->visible(fn (Portfoy $record): bool => $record->yayin_durumu === YayinDurumu::Yayinda)
            ->requiresConfirmation()
            ->modalHeading('İlanı yayından kaldır')
            ->modalDescription('İlan sitede görünmez olur; kayıt ve fotoğraflar silinmez.')
            ->modalSubmitActionLabel('Kaldır')
            ->action(function (Portfoy $record, $livewire): void {
                $record->update(['yayin_durumu' => YayinDurumu::Kaldirildi]);
                static::formuTazele($livewire);

                Notification::make()->success()->title('İlan yayından kaldırıldı')->send();
            });
    }

    public static function arsivle(): Action
    {
        return Action::make('arsivle')
            ->label('Arşivle')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('gray')
            ->authorize('yayimla')
            ->visible(fn (Portfoy $record): bool => $record->yayin_durumu !== YayinDurumu::Arsiv)
            ->requiresConfirmation()
            ->modalHeading('Portföyü arşivle')
            ->modalDescription('İlan sitede görünmez ve listede arşiv olarak kalır.')
            ->modalSubmitActionLabel('Arşivle')
            ->action(function (Portfoy $record, $livewire): void {
                $record->update(['yayin_durumu' => YayinDurumu::Arsiv]);
                static::formuTazele($livewire);

                Notification::make()->success()->title('Portföy arşivlendi')->send();
            });
    }

    public static function onizle(): Action
    {
        return Action::make('onizle')
            ->label('Önizle')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->visible(fn (Portfoy $record): bool => Route::has('ilan.onizleme') && Gate::allows('view', $record))
            ->url(fn (Portfoy $record): ?string => Route::has('ilan.onizleme') ? route('ilan.onizleme', $record->id) : null, shouldOpenInNewTab: true);
    }

    public static function sitedeGor(): Action
    {
        return Action::make('sitedeGor')
            ->label('Sitede gör')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->visible(fn (Portfoy $record): bool => Route::has('ilan.detay') && $record->yayin_durumu === YayinDurumu::Yayinda)
            ->url(fn (Portfoy $record): ?string => Route::has('ilan.detay') ? route('ilan.detay', $record) : null, shouldOpenInNewTab: true);
    }

    /** Kayıt sayfasındaysak formdaki yayın alanını veritabanıyla eşitle (eski değer kaydedilip durumu geri almasın). */
    private static function formuTazele(mixed $livewire): void
    {
        if (is_object($livewire) && method_exists($livewire, 'refreshFormData')) {
            $livewire->refreshFormData(['yayin_durumu', 'one_cikan']);
        }
    }
}
