<?php

namespace App\Policies;

/** Ekipman. */
class VarlikPolicy extends SiteKaydiPolicy
{
    protected array $gorIzinleri = ['operasyon.yonet'];

    protected string $yazIzni = 'operasyon.yonet';
}
