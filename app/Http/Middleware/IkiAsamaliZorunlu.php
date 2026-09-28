<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Yönetici rollerinde (User::MFA_ZORUNLU_ROLLER) iki aşamalı doğrulama
 * kurulmadan panel kullanılamaz: profil sayfasına yönlendirilir ve orada
 * doğrulayıcı uygulamayı kurar (şartname 15).
 *
 * Filament'in kendi "requiresMultiFactorAuthentication" seçeneği rota
 * kaydında bir kez değerlendirildiği için rol bazlı olamıyor; bu yüzden ayrı.
 * `config('balkan.mfa_zorunlu')` yerel geliştirme / testte kapatılabilir.
 */
class IkiAsamaliZorunlu
{
    public function handle(Request $request, Closure $next): Response
    {
        $k = Filament::auth()->user();

        if (! config('balkan.mfa_zorunlu') || ! $k instanceof User || ! $k->ikiAsamaliZorunlu() || $k->ikiAsamaliEtkin()) {
            return $next($request);
        }

        $profil = Filament::getProfileUrl();

        if (! $profil || $request->url() === $profil) {
            return $next($request);
        }

        Notification::make()
            ->warning()
            ->title('İki aşamalı doğrulama gerekli')
            ->body('Hesabınızın rolü için iki aşamalı doğrulama zorunlu. Lütfen profil sayfasından doğrulayıcı uygulamayı kurun.')
            ->persistent()
            ->send();

        return redirect($profil);
    }
}
