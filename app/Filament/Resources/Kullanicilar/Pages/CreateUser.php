<?php

namespace App\Filament\Resources\Kullanicilar\Pages;

use App\Filament\Resources\Kullanicilar\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
