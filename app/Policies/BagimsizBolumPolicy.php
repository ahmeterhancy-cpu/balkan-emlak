<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Bağımsız bölüm. Silinmez, pasife alınır. */
class BagimsizBolumPolicy extends SiteKaydiPolicy
{
    protected array $gorIzinleri = ['finans.goruntule', 'operasyon.yonet', 'site_rapor'];

    protected string $yazIzni = 'site.yonet';

    public function delete(User $k, Model $kayit): bool
    {
        return false;
    }
}
