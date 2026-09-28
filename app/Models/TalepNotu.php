<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Görüşme notu. */
class TalepNotu extends Model
{
    protected $table = 'talep_notlari';

    protected $fillable = ['talep_id', 'user_id', 'not'];

    protected static function booted(): void
    {
        static::creating(function (TalepNotu $n) {
            $n->user_id ??= auth()->id();
        });
    }

    public function talep(): BelongsTo
    {
        return $this->belongsTo(Talep::class);
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
