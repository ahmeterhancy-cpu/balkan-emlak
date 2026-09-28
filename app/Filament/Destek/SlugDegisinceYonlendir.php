<?php

namespace App\Filament\Destek;

/**
 * Kayıt adresi slug'a bağlı modellerde (Hizmet, Yazı) başlık/kısa ad
 * değişince tarayıcıdaki eski adres yenilemede 404 vermesin diye yeni
 * düzenleme adresine geçer.
 */
trait SlugDegisinceYonlendir
{
    protected function afterSave(): void
    {
        if ($this->getRecord()->wasChanged('slug')) {
            $this->redirect(static::getResource()::getUrl('edit', ['record' => $this->getRecord()]), navigate: true);
        }
    }
}
