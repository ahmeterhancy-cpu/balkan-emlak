<?php

namespace App\Policies;

/** Bakım planı. */
class BakimPlaniPolicy extends SiteKaydiPolicy
{
    protected array $gorIzinleri = ['operasyon.yonet'];

    protected string $yazIzni = 'operasyon.yonet';
}
