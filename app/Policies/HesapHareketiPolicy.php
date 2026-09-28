<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Defter satırı: yalnız görüntüleme; hareketler servislerden doğar. */
class HesapHareketiPolicy extends SiteKaydiPolicy
{
    protected array $gorIzinleri = ['finans.goruntule'];

    protected string $yazIzni = 'finans.hesap';

    protected bool $degismez = true;

    public function create(User $k): bool
    {
        return false;
    }
}
