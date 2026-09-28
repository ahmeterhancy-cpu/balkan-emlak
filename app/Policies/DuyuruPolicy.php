<?php

namespace App\Policies;

/** Duyuru. */
class DuyuruPolicy extends SiteKaydiPolicy
{
    protected array $gorIzinleri = ['operasyon.yonet'];

    protected string $yazIzni = 'operasyon.yonet';
}
