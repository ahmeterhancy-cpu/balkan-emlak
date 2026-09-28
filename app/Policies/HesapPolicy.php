<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Kasa / banka hesabı. Silinmez, pasife alınır. */
class HesapPolicy extends SiteKaydiPolicy
{
    protected array $gorIzinleri = ['finans.goruntule'];

    protected string $yazIzni = 'finans.hesap';

    public function delete(User $k, Model $kayit): bool
    {
        return false;
    }
}
