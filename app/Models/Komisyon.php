<?php

namespace App\Models;

use App\Casts\Gun;
use App\Enums\KomisyonDurumu;
use App\Models\Concerns\IslemKaydeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Şirketin emlak komisyon geliri. Yönetilen sitelerin paralarıyla aynı
 * hesapta veya raporda BİRLEŞTİRİLMEZ; resmi muhasebe belgesi değildir.
 */
class Komisyon extends Model
{
    use IslemKaydeder;

    protected $table = 'komisyonlar';

    protected $fillable = ['portfoy_id', 'danisman_id', 'taraf', 'beklenen_kurus', 'gerceklesen_kurus', 'para_birimi', 'odeme_tarihi', 'durum', 'aciklama'];

    protected function casts(): array
    {
        return [
            'durum' => KomisyonDurumu::class,
            'odeme_tarihi' => Gun::class,
            'beklenen_kurus' => 'integer',
            'gerceklesen_kurus' => 'integer',
        ];
    }

    public const TARAFLAR = ['satici' => 'Satıcı', 'alici' => 'Alıcı', 'ev_sahibi' => 'Ev sahibi', 'kiraci' => 'Kiracı'];

    public function portfoy(): BelongsTo
    {
        return $this->belongsTo(Portfoy::class);
    }

    public function danisman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'danisman_id');
    }
}
