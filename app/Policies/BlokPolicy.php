<?php

namespace App\Policies;

/** Blok. */
class BlokPolicy extends SiteKaydiPolicy
{
    protected array $gorIzinleri = ['finans.goruntule', 'operasyon.yonet', 'site_rapor'];

    protected string $yazIzni = 'site.yonet';
}
