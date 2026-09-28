<?php

namespace App\Models;

use App\Casts\Gun;
use App\Models\Concerns\IslemKaydeder;
use App\Models\Concerns\SiteyeBagli;
use App\Support\Yetki;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Site belgesi (yönetim planı, karar, tutanak, sözleşme, bakım belgesi).
 * Özel diskte; sürümler aynı `grup` altında, eski sürüm silinmez.
 * Herkese açık siteye hiçbir koşulda çıkmaz.
 */
class SiteBelgesi extends Model
{
    use IslemKaydeder, SiteyeBagli;

    protected $table = 'site_belgeleri';

    /** Veritabanı varsayılanları: create() sonrası modelde de dolu olsun. */
    protected $attributes = ['erisim' => 'yonetim', 'surum' => 1];

    protected $fillable = ['site_id', 'kategori', 'ad', 'grup', 'surum', 'yol', 'orijinal_ad', 'erisim', 'belge_tarihi', 'yukleyen_id'];

    protected function casts(): array
    {
        return ['belge_tarihi' => Gun::class];
    }

    public const DISK = 'local';

    public const KATEGORILER = [
        'yonetim_plani' => 'Yönetim planı', 'karar' => 'Karar', 'tutanak' => 'Toplantı tutanağı',
        'sozlesme' => 'Sözleşme', 'bakim' => 'Bakım belgesi', 'diger' => 'Diğer',
    ];

    public const ERISIMLER = ['yonetim' => 'Yönetim ve muhasebe', 'muhasebe' => 'Yalnız muhasebe', 'herkes' => 'Siteye atanmış herkes'];

    protected static function booted(): void
    {
        static::creating(function (SiteBelgesi $b) {
            $b->grup ??= (string) Str::uuid();
            $b->yukleyen_id ??= auth()->id();
            $b->surum = (int) static::where('grup', $b->grup)->max('surum') + 1;
        });
    }

    public function yukleyen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'yukleyen_id');
    }

    /** Aynı belgenin yeni sürümü (eskisi korunur). */
    public function yeniSurum(string $yol, ?string $orijinalAd = null): self
    {
        return static::create([
            'site_id' => $this->site_id, 'kategori' => $this->kategori, 'ad' => $this->ad, 'grup' => $this->grup,
            'yol' => $yol, 'orijinal_ad' => $orijinalAd, 'erisim' => $this->erisim, 'belge_tarihi' => now()->toDateString(),
        ]);
    }

    public function sonSurumMu(): bool
    {
        return (int) static::where('grup', $this->grup)->max('surum') === (int) $this->surum;
    }

    /** Site erişimi ayrıca Kapsam ile denetlenir; bu yalnız belge düzeyi erişim. */
    public function gorebilir(?User $k = null): bool
    {
        return match ($this->erisim) {
            'muhasebe' => Yetki::var('finans.hesap', $k), // site yöneticisinde yok
            'herkes' => Yetki::var('panel', $k),
            default => Yetki::var('site_belge.goruntule', $k),
        };
    }
}
