<?php

namespace App\Models;

use App\Models\Concerns\IslemKaydeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Throwable;

/**
 * Eski adres → yeni adres (şartname 10). Yalnız 404 olacak isteklerde devreye
 * girer (bootstrap/app.php): var olan sayfaları asla ezmez.
 *
 * İlan, yazı ve hizmet başlığı değişince eski adres için otomatik kayıt açılır
 * (Concerns\SlugYonlendirmesi) — paylaşılmış bağlantılar ve arama motoru
 * dizini kırılmaz.
 */
class Yonlendirme extends Model
{
    use IslemKaydeder;

    protected $table = 'yonlendirmeler';

    protected $fillable = ['eski_yol', 'hedef', 'kod', 'aktif', 'otomatik', 'not'];

    protected $attributes = ['kod' => 301, 'aktif' => true, 'otomatik' => false, 'isabet' => 0];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'otomatik' => 'boolean', 'son_isabet' => 'datetime'];
    }

    public const KODLAR = [301 => '301 — kalıcı (arama motorları yeni adrese geçer)', 302 => '302 — geçici'];

    protected static function booted(): void
    {
        static::saving(function (Yonlendirme $y) {
            $y->eski_yol = static::normallestir($y->eski_yol);
            if (! preg_match('#^https?://#i', $y->hedef)) {
                $y->hedef = '/'.ltrim($y->hedef, '/');
            }
        });
    }

    /** "/Eski-Sayfa/" → "/Eski-Sayfa"; sorgu dizesi korunur, alan adı atılır. */
    public static function normallestir(string $yol): string
    {
        $parca = parse_url(trim($yol));
        $yolKismi = '/'.trim(rawurldecode($parca['path'] ?? '/'), '/');

        return $yolKismi.(isset($parca['query']) && $parca['query'] !== '' ? '?'.$parca['query'] : '');
    }

    /** İstek için etkin yönlendirme: önce yol + sorgu, sonra yalnız yol. */
    public static function bul(Request $istek): ?self
    {
        try {
            $yol = '/'.trim(rawurldecode($istek->getPathInfo()), '/');
            $sorgu = $istek->getQueryString();

            return static::query()->where('aktif', true)
                ->whereIn('eski_yol', array_filter([$sorgu ? $yol.'?'.$sorgu : null, $yol]))
                ->orderByRaw('LENGTH(eski_yol) DESC')
                ->first();
        } catch (Throwable) {
            return null; // tablo yoksa (ilk kurulum) sessizce 404'e devam
        }
    }

    public function hedefAdresi(): string
    {
        return preg_match('#^https?://#i', $this->hedef) ? $this->hedef : url($this->hedef);
    }

    public function isabetYaz(): void
    {
        static::whereKey($this->id)->update(['isabet' => $this->isabet + 1, 'son_isabet' => now()]);
    }

    /**
     * Slug değişince: eski → yeni kaydı. Zincir oluşmasın diye eskiden bu eski
     * adrese yönlenen kayıtlar da yeni adrese çekilir; yeni adres daha önce
     * "eski" olarak kayıtlıysa (geri alınan başlık) o kayıt silinir — döngü olmaz.
     */
    public static function slugDegisti(string $eskiYol, string $yeniYol): void
    {
        $eskiYol = static::normallestir($eskiYol);
        $yeniYol = static::normallestir($yeniYol);

        if ($eskiYol === $yeniYol) {
            return;
        }

        static::where('eski_yol', $yeniYol)->delete();
        static::where('hedef', $eskiYol)->update(['hedef' => $yeniYol]);

        static::updateOrCreate(
            ['eski_yol' => $eskiYol],
            ['hedef' => $yeniYol, 'kod' => 301, 'aktif' => true, 'otomatik' => true, 'not' => 'Başlık değişikliğinden otomatik'],
        );
    }
}
