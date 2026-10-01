<?php

namespace App\Providers\Filament;

use App\Filament\Pages\GostergePaneli;
use App\Models\Ayar;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Auth\Pages\Login;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // Gösterge bileşenlerinin stili (Filament temasına dokunmadan, "bp-" önekli)
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => new HtmlString(Vite::withEntryPoints(['resources/css/panel.css'])->toHtml()))
            // Giriş sayfası: solda marka alanı, formun altında siteye dönüş bağlantısı
            ->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, fn () => view('filament.giris-yan'), scopes: Login::class)
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => new HtmlString(
                '<p class="giris-siteye"><a href="'.e(route('anasayfa')).'">← Web sitesine dön</a></p>'
            ), scopes: Login::class)
            ->brandName(fn (): string => (string) Ayar::al('firma_adi', 'Balkan Emlak ve Yönetim'))
            ->colors([
                /*
                 * TUZAK: Color::hex() KULLANMAYIN — yalnız tonu (hue) alıp
                 * kendi rampasını kurar, lacivert başka bir maviye döner.
                 *
                 * Birincil: marka laciverti #142D3E (500). 500 ve üstü beyaz
                 * yazıyla 12:1'in üstünde kontrast verir; 400 koyu temada
                 * bağlantı rengi olarak koyu zeminde okunur.
                 */
                'primary' => [
                    50 => '238, 243, 247',
                    100 => '214, 226, 235',
                    200 => '176, 199, 216',
                    300 => '128, 163, 189',
                    400 => '104, 146, 178',
                    500 => '20, 45, 62',
                    600 => '18, 40, 55',
                    700 => '15, 33, 46',
                    800 => '12, 27, 37',
                    900 => '9, 20, 28',
                    950 => '5, 12, 17',
                ],
                /*
                 * Vurgu yeşili #739B16 (500). Beyaz yazı 500 üstünde yalnız
                 * ~3,4:1 verdiği için düğmelerde kullanılan 600 (#5C7C12,
                 * ~4,8:1) ve 700 bilerek koyulaştırıldı.
                 */
                'success' => [
                    50 => '246, 250, 236',
                    100 => '234, 243, 207',
                    200 => '213, 231, 160',
                    300 => '186, 214, 104',
                    400 => '148, 186, 48',
                    500 => '115, 155, 22',
                    600 => '92, 124, 18',
                    700 => '74, 100, 14',
                    800 => '59, 79, 13',
                    900 => '47, 63, 12',
                    950 => '25, 35, 5',
                ],
            ])
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                'Emlak Yönetimi',
                'Site Yönetimi',
                'Site Finansı',
                'Site Operasyonu',
                'Site Raporları',
                'Başvurular',
                'Web İçerikleri',
                'Sistem',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                GostergePaneli::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
