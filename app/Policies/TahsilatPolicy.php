<?php

namespace App\Policies;

/** Tahsilat: düzenlenmez / silinmez; iade karşı kayıtla (TahsilatServisi). */
class TahsilatPolicy extends SiteKaydiPolicy
{
    protected array $gorIzinleri = ['finans.goruntule'];

    protected string $yazIzni = 'finans.tahsilat';

    protected bool $degismez = true;
}
