<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Gider. Onaylanan gider düzenlenmez / silinmez. */
class GiderPolicy extends SiteKaydiPolicy
{
    protected array $gorIzinleri = ['finans.goruntule'];

    protected string $yazIzni = 'finans.gider';

    public function update(User $k, Model $kayit): bool
    {
        return in_array($kayit->onay_durumu, ['taslak', 'reddedildi'], true) && parent::update($k, $kayit);
    }
}
