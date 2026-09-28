<?php

namespace App\Models;

use App\Models\Concerns\IslemKaydeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Sık sorulan soru. */
class Sss extends Model
{
    use IslemKaydeder;

    protected $table = 'sss';

    protected $fillable = ['grup', 'soru', 'cevap', 'yayinda', 'sira'];

    protected function casts(): array
    {
        return ['yayinda' => 'boolean'];
    }

    public const GRUPLAR = ['genel' => 'Genel', 'emlak' => 'Emlak', 'yonetim' => 'Site yönetimi'];

    /** @param Builder<Sss> $sorgu */
    public function scopeYayinda(Builder $sorgu): void
    {
        $sorgu->where('yayinda', true)->orderBy('sira')->orderBy('id');
    }
}
