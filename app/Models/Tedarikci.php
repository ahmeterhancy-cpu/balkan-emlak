<?php

namespace App\Models;

use App\Models\Concerns\IslemKaydeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tedarikci extends Model
{
    use IslemKaydeder;

    protected $table = 'tedarikciler';

    /** Veritabanı varsayılanları: create() sonrası modelde de dolu olsun. */
    protected $attributes = ['aktif' => true];

    protected $fillable = ['ad', 'kategori', 'vergi_no', 'telefon', 'eposta', 'iban', 'notlar', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function giderler(): HasMany
    {
        return $this->hasMany(Gider::class);
    }
}
