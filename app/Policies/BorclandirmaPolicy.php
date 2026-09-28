<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Toplu borçlandırma. Kesinleşmiş iş düzenlenemez. */
class BorclandirmaPolicy extends SiteKaydiPolicy
{
    protected array $gorIzinleri = ['finans.goruntule'];

    protected string $yazIzni = 'finans.borclandir';

    public function update(User $k, Model $kayit): bool
    {
        return $kayit->durum === 'taslak' && parent::update($k, $kayit);
    }
}
