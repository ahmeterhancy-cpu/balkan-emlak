<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;

/** Gösterge paneli (şartname 6.2, emlak bölümü). Widget'lar yetkiye göre görünür. */
class GostergePaneli extends Dashboard
{
    protected static ?string $title = 'Gösterge Paneli';

    protected static ?string $navigationLabel = 'Gösterge Paneli';

    public function getColumns(): int|array
    {
        return 2;
    }
}
