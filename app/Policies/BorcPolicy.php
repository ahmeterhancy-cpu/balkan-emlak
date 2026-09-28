<?php

namespace App\Policies;

/** Borç: düzenlenmez / silinmez; düzeltme karşı kayıtla (BorcServisi). */
class BorcPolicy extends SiteKaydiPolicy
{
    protected array $gorIzinleri = ['finans.goruntule'];

    protected string $yazIzni = 'finans.borclandir';

    protected bool $degismez = true;
}
