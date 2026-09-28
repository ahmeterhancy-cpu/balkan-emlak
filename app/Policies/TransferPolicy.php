<?php

namespace App\Policies;

/** Hesaplar arası transfer. Değişmez. */
class TransferPolicy extends SiteKaydiPolicy
{
    protected array $gorIzinleri = ['finans.goruntule'];

    protected string $yazIzni = 'finans.hesap';

    protected bool $degismez = true;
}
