<?php

namespace App\Models;

use App\Models\Concerns\GorselSurumleri;
use App\Models\Concerns\IslemKaydeder;
use App\Models\Concerns\SlugYonlendirmesi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Bilgi merkezi yazısı. Taslak → yayında → arşiv. */
class Yazi extends Model
{
    use GorselSurumleri, IslemKaydeder, SlugYonlendirmesi;

    public function vitrinYolu(string $slug): string
    {
        return '/bilgi-merkezi/'.$slug;
    }

    public function slugYonlendirmesiGerekli(): bool
    {
        return $this->yayin_tarihi !== null;
    }

    protected $table = 'yazilar';

    protected $fillable = ['baslik', 'slug', 'kategori', 'ozet', 'icerik', 'gorsel', 'gorsel_alt', 'durum', 'yayin_tarihi', 'yazar_id', 'seo_aciklama', 'gorsel_hakki', 'gorsel_kaynak'];

    protected function casts(): array
    {
        return ['yayin_tarihi' => 'datetime'];
    }

    public const KATEGORILER = ['emlak' => 'Emlak', 'yonetim' => 'Site ve apartman yönetimi'];

    public const DURUMLAR = ['taslak' => 'Taslak', 'yayinda' => 'Yayında', 'arsiv' => 'Arşiv'];

    protected static function booted(): void
    {
        static::saving(function (Yazi $y) {
            $y->slug = filled($y->slug) ? Str::slug($y->slug) : Str::slug($y->baslik);
            if ($y->durum === 'yayinda') {
                $y->yayin_tarihi ??= now();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function yazar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'yazar_id');
    }

    /** İleri tarihli yazı zamanı gelene kadar görünmez. @param Builder<Yazi> $sorgu */
    public function scopeYayinda(Builder $sorgu): void
    {
        $sorgu->where('durum', 'yayinda')->where('yayin_tarihi', '<=', now())->latest('yayin_tarihi');
    }

    public function gorselUrl(): ?string
    {
        return $this->gorsel ? Storage::disk('medya')->url($this->gorsel) : null;
    }

    public function okumaSuresi(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $this->icerik)) / 200));
    }
}
