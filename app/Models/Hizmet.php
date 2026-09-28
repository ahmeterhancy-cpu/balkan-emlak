<?php

namespace App\Models;

use App\Models\Concerns\GorselSurumleri;
use App\Models\Concerns\IslemKaydeder;
use App\Models\Concerns\SlugYonlendirmesi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Site ve apartman yönetimi hizmeti (genel, hukuki, temizlik, ...). */
class Hizmet extends Model
{
    use GorselSurumleri, IslemKaydeder, SlugYonlendirmesi;

    public function vitrinYolu(string $slug): string
    {
        return '/site-yonetimi/'.$slug;
    }

    protected $table = 'hizmetler';

    protected $fillable = ['baslik', 'slug', 'ikon', 'ozet', 'kapsam', 'surec', 'gorsel', 'yayinda', 'sira', 'seo_aciklama', 'gorsel_hakki', 'gorsel_kaynak'];

    protected function casts(): array
    {
        return ['surec' => 'array', 'yayinda' => 'boolean'];
    }

    /** Vitrinde çizilebilen ikonlar (resources/views/components/ikon.blade.php). */
    public const IKONLAR = [
        'yonetim' => 'Genel yönetim',
        'hukuk' => 'Hukuk / terazi',
        'temizlik' => 'Temizlik',
        'bakim' => 'Bakım / anahtar',
        'teknik' => 'Teknik / dişli',
        'aidat' => 'Aidat / belge',
        'personel' => 'Personel',
        'bahce' => 'Bahçe / yaprak',
        'guvenlik' => 'Güvenlik / kalkan',
    ];

    protected static function booted(): void
    {
        static::saving(function (Hizmet $h) {
            $h->slug = filled($h->slug) ? Str::slug($h->slug) : Str::slug($h->baslik);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @param Builder<Hizmet> $sorgu */
    public function scopeYayinda(Builder $sorgu): void
    {
        $sorgu->where('yayinda', true)->orderBy('sira')->orderBy('id');
    }

    public function gorselUrl(): ?string
    {
        return $this->gorsel ? Storage::disk('medya')->url($this->gorsel) : null;
    }
}
