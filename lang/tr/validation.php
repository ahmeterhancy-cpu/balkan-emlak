<?php

/*
 * Türkçe doğrulama iletileri — yalnız projede kullanılan kurallar.
 * Eksik bir kural İngilizce görünürse buraya eklenir.
 */

return [
    'accepted' => ':attribute onaylanmalıdır.',
    'after' => ':attribute ileri bir tarih olmalıdır.',
    'before' => ':attribute :date tarihinden önce olmalıdır.',
    'array' => ':attribute geçersiz.',
    'boolean' => ':attribute geçersiz.',
    'confirmed' => ':attribute tekrarı eşleşmiyor.',
    'date' => ':attribute geçerli bir tarih olmalıdır.',
    'email' => ':attribute geçerli bir e-posta adresi olmalıdır.',
    'exists' => 'Seçilen :attribute geçersiz.',
    'file' => ':attribute bir dosya olmalıdır.',
    'image' => ':attribute bir görsel olmalıdır.',
    'in' => 'Seçilen :attribute geçersiz.',
    'integer' => ':attribute tam sayı olmalıdır.',
    'max' => [
        'numeric' => ':attribute en fazla :max olabilir.',
        'file' => ':attribute en fazla :max KB olabilir.',
        'string' => ':attribute en fazla :max karakter olabilir.',
        'array' => ':attribute en fazla :max öğe içerebilir.',
    ],
    'mimes' => ':attribute şu türlerden biri olmalıdır: :values.',
    'min' => [
        'numeric' => ':attribute en az :min olmalıdır.',
        'file' => ':attribute en az :min KB olmalıdır.',
        'string' => ':attribute en az :min karakter olmalıdır.',
        'array' => ':attribute en az :min öğe içermelidir.',
    ],
    'numeric' => ':attribute sayı olmalıdır.',
    'regex' => ':attribute biçimi geçersiz.',
    'required' => ':attribute zorunludur.',
    'required_if' => ':attribute zorunludur.',
    'required_with' => ':attribute zorunludur.',
    'required_without' => 'Telefon ya da e-postadan en az birini yazın.',
    'string' => ':attribute metin olmalıdır.',
    'unique' => 'Bu :attribute zaten kayıtlı.',
    'url' => ':attribute geçerli bir adres olmalıdır.',
    'uploaded' => ':attribute yüklenemedi.',

    'custom' => [
        'kvkk' => ['accepted' => 'Devam etmek için aydınlatma metnini okuduğunuzu onaylayın.'],
    ],

    'attributes' => [
        'ad_soyad' => 'Ad soyad',
        'telefon' => 'Telefon',
        'eposta' => 'E-posta',
        'mesaj' => 'Mesaj',
        'konu' => 'Konu',
        'islem' => 'İşlem',
        'kategori' => 'Gayrimenkul türü',
        'konum' => 'Konum',
        'site_adi' => 'Site / apartman adı',
        'blok_sayisi' => 'Blok sayısı',
        'bagimsiz_bolum_sayisi' => 'Bağımsız bölüm sayısı',
        'ihtiyaclar' => 'İhtiyaçlar',
        'ihtiyaclar.*' => 'İhtiyaç',
        'tercih_edilen_zaman' => 'Tercih edilen tarih ve saat',
        'talep_turu' => 'Talep türü',
        'iletisim_tercihi' => 'İletişim tercihi',
        'kvkk' => 'Aydınlatma metni onayı',
        'name' => 'Ad',
        'email' => 'E-posta',
        'password' => 'Parola',
    ],
];
