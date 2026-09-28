<?php

namespace App\Http\Controllers;

use App\Enums\Kategori;
use App\Enums\TalepDurumu;
use App\Enums\TalepTuru;
use App\Mail\YeniBasvuru;
use App\Models\Hizmet;
use App\Models\Portfoy;
use App\Models\Talep;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Web formları → Talep kaydı (panelde "Başvurular ve Talepler").
 *
 * Spam önlemi üç katman: hız sınırı (rota), bal küpü alanı ve
 * şifreli form zamanı (3 saniyeden hızlı ya da 1 günden eski gönderim
 * reddedilir). Bot yakalanınca kullanıcıya başarı gösterilir ama kayıt
 * açılmaz — botun yöntemini değiştirmesi için ipucu verilmez.
 */
class BasvuruController extends Controller
{
    private const TEL_KURALI = ['nullable', 'string', 'max:30', 'regex:/^[0-9 ()+\-]{7,30}$/'];

    public function portfoyFormu(): View
    {
        return view('basvuru.portfoy');
    }

    public function teklifFormu(Request $istek): View
    {
        return view('basvuru.teklif', [
            'hizmetler' => Hizmet::yayinda()->get(['id', 'baslik', 'slug']),
            'seciliHizmet' => (string) $istek->query('hizmet'),
        ]);
    }

    public function ilanTalebi(Request $istek, Portfoy $portfoy): RedirectResponse
    {
        abort_unless($portfoy->yayin_durumu?->value === 'yayinda', 404);

        if (! $portfoy->talepAlabilir()) {
            return back()->withErrors(['genel' => 'Bu gayrimenkul için artık talep alınmıyor.']);
        }

        if ($this->botMu($istek)) {
            return $this->tamam('ilan');
        }

        $v = $istek->validateWithBag('ilan', [
            'ad_soyad' => ['required', 'string', 'min:3', 'max:120'],
            'telefon' => ['required', ...array_slice(self::TEL_KURALI, 1)],
            'eposta' => ['nullable', 'email:rfc', 'max:160'],
            'tercih_edilen_zaman' => ['nullable', 'date', 'after:now', 'before:+6 months'],
            'mesaj' => ['nullable', 'string', 'max:2000'],
            'kvkk' => ['accepted'],
        ]);

        $talep = $this->kaydet($istek, TalepTuru::IlanBilgi, [
            'ad_soyad' => $v['ad_soyad'],
            'telefon' => $v['telefon'],
            'eposta' => $v['eposta'] ?? null,
            'mesaj' => $v['mesaj'] ?? null,
            'portfoy_id' => $portfoy->id,
            'atanan_id' => $portfoy->danisman_id, // ilanın danışmanına düşer
            'tercih_edilen_zaman' => filled($v['tercih_edilen_zaman'] ?? null)
                ? Carbon::parse($v['tercih_edilen_zaman'], config('app.timezone'))
                : null,
            'konu' => $portfoy->portfoy_no.' — '.Str::limit($portfoy->baslik, 80),
        ]);

        return $this->tamam('ilan', $talep);
    }

    public function portfoyBasvurusu(Request $istek): RedirectResponse
    {
        if ($this->botMu($istek)) {
            return $this->tamam('portfoy');
        }

        $v = $istek->validate([
            'ad_soyad' => ['required', 'string', 'min:3', 'max:120'],
            'telefon' => ['required', ...array_slice(self::TEL_KURALI, 1)],
            'eposta' => ['nullable', 'email:rfc', 'max:160'],
            'islem' => ['required', Rule::in(['satis', 'kiralama'])],
            'kategori' => ['required', Rule::in(array_column(Kategori::cases(), 'value'))],
            'konum' => ['required', 'string', 'max:160'],
            'mesaj' => ['nullable', 'string', 'max:3000'],
            'kvkk' => ['accepted'],
        ]);

        $talep = $this->kaydet($istek, TalepTuru::PortfoyBasvurusu, [
            'ad_soyad' => $v['ad_soyad'],
            'telefon' => $v['telefon'],
            'eposta' => $v['eposta'] ?? null,
            'mesaj' => $v['mesaj'] ?? null,
            'konu' => ($v['islem'] === 'satis' ? 'Satış' : 'Kiralama').' — '.Kategori::from($v['kategori'])->getLabel(),
            'detay' => [
                'islem' => $v['islem'] === 'satis' ? 'Satış' : 'Kiralama',
                'kategori' => Kategori::from($v['kategori'])->getLabel(),
                'konum' => $v['konum'],
            ],
        ]);

        return $this->tamam('portfoy', $talep);
    }

    public function teklif(Request $istek): RedirectResponse
    {
        if ($this->botMu($istek)) {
            return $this->tamam('teklif');
        }

        $hizmetAdlari = Hizmet::yayinda()->pluck('baslik')->all();

        $v = $istek->validate([
            'ad_soyad' => ['required', 'string', 'min:3', 'max:120'],
            'telefon' => ['required_without:eposta', ...self::TEL_KURALI],
            'eposta' => ['required_without:telefon', 'nullable', 'email:rfc', 'max:160'],
            'site_adi' => ['required', 'string', 'max:160'],
            'konum' => ['required', 'string', 'max:160'],
            'blok_sayisi' => ['nullable', 'integer', 'min:1', 'max:500'],
            'bagimsiz_bolum_sayisi' => ['required', 'integer', 'min:1', 'max:20000'],
            'ihtiyaclar' => ['nullable', 'array', 'max:20'],
            'ihtiyaclar.*' => ['string', Rule::in($hizmetAdlari)],
            'iletisim_tercihi' => ['nullable', Rule::in(['telefon', 'eposta', 'whatsapp'])],
            'mesaj' => ['nullable', 'string', 'max:3000'],
            'kvkk' => ['accepted'],
        ]);

        $talep = $this->kaydet($istek, TalepTuru::YonetimTeklifi, [
            'ad_soyad' => $v['ad_soyad'],
            'telefon' => $v['telefon'] ?? null,
            'eposta' => $v['eposta'] ?? null,
            'mesaj' => $v['mesaj'] ?? null,
            'konu' => $v['site_adi'],
            'sonuc' => 'beklemede',
            'detay' => array_filter([
                'site_adi' => $v['site_adi'],
                'konum' => $v['konum'],
                'blok_sayisi' => isset($v['blok_sayisi']) ? (int) $v['blok_sayisi'] : null,
                'bagimsiz_bolum_sayisi' => (int) $v['bagimsiz_bolum_sayisi'],
                'ihtiyaclar' => array_values($v['ihtiyaclar'] ?? []),
                'iletisim_tercihi' => match ($v['iletisim_tercihi'] ?? null) {
                    'telefon' => 'Telefon', 'eposta' => 'E-posta', 'whatsapp' => 'WhatsApp', default => null,
                },
            ], fn ($d) => $d !== null && $d !== []),
        ]);

        return $this->tamam('teklif', $talep);
    }

    public function iletisim(Request $istek): RedirectResponse
    {
        if ($this->botMu($istek)) {
            return $this->tamam('iletisim');
        }

        $v = $istek->validate([
            'ad_soyad' => ['required', 'string', 'min:3', 'max:120'],
            'telefon' => ['required_without:eposta', ...self::TEL_KURALI],
            'eposta' => ['required_without:telefon', 'nullable', 'email:rfc', 'max:160'],
            'konu' => ['required', 'string', 'max:160'],
            'mesaj' => ['required', 'string', 'min:10', 'max:3000'],
            'kvkk' => ['accepted'],
        ]);

        $talep = $this->kaydet($istek, TalepTuru::Iletisim, [
            'ad_soyad' => $v['ad_soyad'],
            'telefon' => $v['telefon'] ?? null,
            'eposta' => $v['eposta'] ?? null,
            'konu' => $v['konu'],
            'mesaj' => $v['mesaj'],
        ]);

        return $this->tamam('iletisim', $talep);
    }

    /** Ana sayfadaki kısa form: emlak talebi ya da yönetim teklifi. */
    public function kisaTalep(Request $istek): RedirectResponse
    {
        if ($this->botMu($istek)) {
            return $this->tamam('kisa');
        }

        $v = $istek->validateWithBag('kisa', [
            'talep_turu' => ['required', Rule::in(['emlak', 'yonetim'])],
            'ad_soyad' => ['required', 'string', 'min:3', 'max:120'],
            'telefon' => ['required', ...array_slice(self::TEL_KURALI, 1)],
            'mesaj' => ['nullable', 'string', 'max:2000'],
            'kvkk' => ['accepted'],
        ]);

        $yonetim = $v['talep_turu'] === 'yonetim';

        $talep = $this->kaydet($istek, $yonetim ? TalepTuru::YonetimTeklifi : TalepTuru::MusteriTalebi, [
            'ad_soyad' => $v['ad_soyad'],
            'telefon' => $v['telefon'],
            'mesaj' => $v['mesaj'] ?? null,
            'konu' => $yonetim ? 'Kısa form — yönetim teklifi' : 'Kısa form — emlak talebi',
            'sonuc' => $yonetim ? 'beklemede' : null,
        ]);

        return $this->tamam('kisa', $talep);
    }

    /* ------------------------------------------------------------------ */

    /** @param array<string, mixed> $alanlar */
    private function kaydet(Request $istek, TalepTuru $tur, array $alanlar): Talep
    {
        $talep = Talep::create($alanlar + [
            'tur' => $tur,
            'kaynak' => 'web',
            'durum' => TalepDurumu::Yeni,
            'kvkk_onay' => true,
            'pazarlama_izni' => $istek->boolean('pazarlama'),
            'ip' => $istek->ip(),
            'tarayici' => Str::limit((string) $istek->userAgent(), 250, ''),
        ]);

        $this->bildir($talep);

        return $talep;
    }

    /** Bildirim hatası başvuruyu düşürmez: kayıt panelde zaten var. */
    private function bildir(Talep $talep): void
    {
        $alici = ayar('bildirim_eposta');
        if (! $alici) {
            return;
        }

        try {
            Mail::to($alici)->send(new YeniBasvuru($talep->loadMissing('portfoy')));
        } catch (Throwable $e) {
            Log::warning('Başvuru bildirimi gönderilemedi', ['talep' => $talep->id, 'hata' => $e->getMessage()]);
        }
    }

    private function botMu(Request $istek): bool
    {
        if (filled($istek->input('web_sitesi'))) {
            return true; // bal küpü dolu
        }

        try {
            $zaman = (int) Crypt::decryptString((string) $istek->input('form_zamani'));
        } catch (Throwable) {
            // Şifreli zaman yok/bozuk: eski sekmeden gelmiş olabilir; insanı reddetme, doğrulamaya bırak
            throw ValidationException::withMessages(['genel' => 'Form süresi doldu. Lütfen sayfayı yenileyip tekrar deneyin.']);
        }

        $gecen = now()->timestamp - $zaman;

        return $gecen < 3 || $gecen > 86400;
    }

    private function tamam(string $form, ?Talep $talep = null): RedirectResponse
    {
        $mesaj = match ($form) {
            'ilan' => 'Talebiniz alındı. İlgili danışmanımız en kısa sürede sizinle iletişime geçecek. Randevu, danışmanın onayıyla kesinleşir.',
            'portfoy' => 'Başvurunuz alındı. Danışmanımız gayrimenkulünüzü değerlendirmek için sizi arayacak.',
            'teklif' => 'Teklif talebiniz alındı. Keşif ve teklif süreci için sizinle iletişime geçeceğiz.',
            default => 'Mesajınız alındı. En kısa sürede size dönüş yapacağız.',
        };

        return back()->with('basari', $mesaj)->with('basari_form', $form);
    }
}
