<?php

namespace App\Services\Yedek;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use ZipArchive;

/**
 * Yedekleme, doğrulama ve geri yükleme (şartname 15 ve 17).
 *
 * Yedek = tek zip (AES-256, parola YEDEK_PAROLA):
 *   manifest.json            tablo satır sayıları, mali kontrol toplamları, dosya özetleri (sha1)
 *   veri/{tablo}.jsonl       her satır bir JSON (ham değerler: şifreli sütunlar şifreli kalır)
 *   dosyalar/private/...     özel belgeler, dekontlar, site belgeleri (storage/app/private)
 *   dosyalar/medya/...       ilan ve site görselleri (public/medya)
 *
 * mysqldump gerektirmez (paylaşımlı sunucuda olmayabilir); MySQL ve SQLite'ta aynı çalışır.
 *
 * Doğrulama gerçek bir geri yüklemedir: yedek, göçlerle kurulan BOŞ ve GEÇİCİ bir
 * SQLite veritabanına yüklenir; satır sayıları ve mali toplamlar manifestle,
 * dosyalar sha1 ile karşılaştırılır. Canlı veritabanına dokunmaz.
 */
class Yedekleyici
{
    /** Yedeğe alınmayan geçici tablolar. */
    public const HARIC = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    /** Mali bütünlük kontrolü: tablo => toplamı alınan sütunlar. */
    public const KONTROL_TOPLAMLARI = [
        'borclar' => ['tutar_kurus'],
        'tahsilatlar' => ['tutar_kurus'],
        'tahsilat_dagitimlari' => ['tutar_kurus'],
        'hesap_hareketleri' => ['tutar_kurus'],
        'giderler' => ['tutar_kurus'],
        'gider_odemeleri' => ['tutar_kurus'],
        'transferler' => ['tutar_kurus'],
        'komisyonlar' => ['beklenen_kurus'],
        'portfoyler' => ['fiyat_kurus'],
    ];

    private const DOGRULAMA_BAGLANTISI = 'yedek_dogrulama';

    public function klasor(): string
    {
        return storage_path('app/yedekler');
    }

    public function parola(): ?string
    {
        $p = config('app.yedek_parola');

        return filled($p) ? (string) $p : null;
    }

    /**
     * Yedek al. Dönen: zip yolu.
     */
    public function al(bool $sifresizeIzin = false): string
    {
        $parola = $this->parola();
        if (! $parola && ! $sifresizeIzin) {
            throw new RuntimeException('YEDEK_PAROLA tanımlı değil. Kişisel veri ve mali kayıt içeren yedek şifresiz alınmaz.');
        }

        File::ensureDirectoryExists($this->klasor());
        $yol = $this->klasor().'/balkan-'.now()->format('Ymd-His').'.zip';

        $zip = new ZipArchive;
        if ($zip->open($yol, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Yedek dosyası açılamadı: '.$yol);
        }
        if ($parola) {
            $zip->setPassword($parola);
        }

        $gecici = [];
        $manifest = [
            'surum' => 1,
            'olusturma' => now()->toIso8601String(),
            'uygulama' => config('app.name'),
            'surucu' => DB::connection()->getDriverName(),
            'sifreli' => (bool) $parola,
            'tablolar' => [],
            'kontroller' => [],
            'dosyalar' => [],
        ];

        foreach ($this->tablolar() as $tablo) {
            $dosya = tempnam(sys_get_temp_dir(), 'yedek');
            $gecici[] = $dosya;
            $akis = fopen($dosya, 'w');
            $adet = 0;

            foreach (DB::table($tablo)->cursor() as $satir) {
                fwrite($akis, json_encode((array) $satir, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)."\n");
                $adet++;
            }
            fclose($akis);

            $this->ekle($zip, $dosya, "veri/{$tablo}.jsonl", $parola);
            $manifest['tablolar'][$tablo] = $adet;
        }

        $manifest['kontroller'] = $this->kontroller(DB::connection()->getName());

        foreach ($this->dosyaKaynaklari() as $on => $kok) {
            if (! is_dir($kok)) {
                continue;
            }
            foreach (File::allFiles($kok) as $f) {
                $goreli = str_replace('\\', '/', $f->getRelativePathname());
                if (str_starts_with($goreli, 'yedekler/') || str_starts_with($goreli, 'livewire-tmp/')) {
                    continue; // yedeğin yedeği ve yarım yüklemeler alınmaz
                }
                $ad = "dosyalar/{$on}/{$goreli}";
                $this->ekle($zip, $f->getPathname(), $ad, $parola);
                $manifest['dosyalar'][$ad] = sha1_file($f->getPathname());
            }
        }

        $manifestYolu = tempnam(sys_get_temp_dir(), 'yedek');
        $gecici[] = $manifestYolu;
        file_put_contents($manifestYolu, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->ekle($zip, $manifestYolu, 'manifest.json', $parola);

        if (! $zip->close()) {
            throw new RuntimeException('Yedek dosyası yazılamadı.');
        }

        foreach ($gecici as $g) {
            @unlink($g);
        }

        return $yol;
    }

    /**
     * Yedeği boş, geçici bir veritabanına yükleyip doğrula.
     *
     * @return array{basarili: bool, hatalar: list<string>, tablolar: int, satirlar: int, dosyalar: int, olusturma: ?string}
     */
    public function dogrula(string $yol): array
    {
        $hatalar = [];
        [$zip, $manifest] = $this->ac($yol);

        $dbYolu = storage_path('app/yedek-dogrulama-'.bin2hex(random_bytes(4)).'.sqlite');
        touch($dbYolu);

        try {
            $this->baglantiKur($dbYolu);
            Artisan::call('migrate', ['--database' => self::DOGRULAMA_BAGLANTISI, '--force' => true]);

            $this->veriYukle($zip, $manifest, self::DOGRULAMA_BAGLANTISI, $hatalar);

            // Satır sayıları
            $hedef = DB::connection(self::DOGRULAMA_BAGLANTISI);
            foreach ($manifest['tablolar'] as $tablo => $beklenen) {
                if ($tablo === 'migrations') {
                    continue;
                }
                if (! Schema::connection(self::DOGRULAMA_BAGLANTISI)->hasTable($tablo)) {
                    $hatalar[] = "Tablo geri yüklenen şemada yok: {$tablo}";

                    continue;
                }
                $gercek = $hedef->table($tablo)->count();
                if ($gercek !== (int) $beklenen) {
                    $hatalar[] = "{$tablo}: yedekte {$beklenen} satır, geri yüklenen {$gercek}";
                }
            }

            // Mali toplamlar
            $yeni = $this->kontroller(self::DOGRULAMA_BAGLANTISI);
            foreach ($manifest['kontroller'] as $anahtar => $deger) {
                if ((string) ($yeni[$anahtar] ?? 'yok') !== (string) $deger) {
                    $hatalar[] = "Kontrol toplamı tutmuyor: {$anahtar} (yedek {$deger}, geri yüklenen ".($yeni[$anahtar] ?? 'yok').')';
                }
            }

            // Dosyalar
            foreach ($manifest['dosyalar'] as $ad => $sha1) {
                $icerik = $zip->getFromName($ad);
                if ($icerik === false) {
                    $hatalar[] = "Dosya yedekte yok: {$ad}";
                } elseif (sha1($icerik) !== $sha1) {
                    $hatalar[] = "Dosya bozuk: {$ad}";
                }
            }
        } finally {
            $zip->close();
            DB::purge(self::DOGRULAMA_BAGLANTISI);
            @unlink($dbYolu);
        }

        return [
            'basarili' => $hatalar === [],
            'hatalar' => $hatalar,
            'tablolar' => count($manifest['tablolar']),
            'satirlar' => array_sum($manifest['tablolar']),
            'dosyalar' => count($manifest['dosyalar']),
            'olusturma' => $manifest['olusturma'] ?? null,
        ];
    }

    /**
     * Canlı veritabanını ve dosyaları yedekten geri yükle. Önce güvenlik yedeği alınır.
     * Dönen: güvenlik yedeğinin yolu.
     */
    public function geriYukle(string $yol): string
    {
        $dogrulama = $this->dogrula($yol);
        if (! $dogrulama['basarili']) {
            throw new RuntimeException("Yedek doğrulanamadı, geri yükleme yapılmadı:\n- ".implode("\n- ", $dogrulama['hatalar']));
        }

        $guvenlik = $this->al(sifresizeIzin: true);

        [$zip, $manifest] = $this->ac($yol);
        try {
            $hatalar = [];
            $this->veriYukle($zip, $manifest, DB::connection()->getName(), $hatalar, bosalt: true);
            if ($hatalar) {
                throw new RuntimeException(implode("\n", $hatalar));
            }

            foreach (array_keys($manifest['dosyalar']) as $ad) {
                [, $on, $goreli] = explode('/', $ad, 3);
                $kok = $this->dosyaKaynaklari()[$on] ?? null;
                if ($kok) {
                    File::ensureDirectoryExists(dirname($kok.'/'.$goreli));
                    file_put_contents($kok.'/'.$goreli, $zip->getFromName($ad));
                }
            }
        } finally {
            $zip->close();
        }

        return $guvenlik;
    }

    /** En eski yedekleri sil; son $adet yedek kalır. */
    public function temizle(int $adet): int
    {
        $dosyalar = collect(File::glob($this->klasor().'/balkan-*.zip'))->sort()->values();
        $silinecek = $dosyalar->slice(0, max(0, $dosyalar->count() - $adet));
        $silinecek->each(fn ($f) => File::delete($f));

        return $silinecek->count();
    }

    public function sonYedek(): ?string
    {
        return collect(File::glob($this->klasor().'/balkan-*.zip'))->sort()->last();
    }

    /* ------------------------------------------------------------------ */

    /** @return list<string> */
    private function tablolar(): array
    {
        $tablolar = collect(Schema::getTableListing(schemaQualified: false))
            ->reject(fn ($t) => in_array($t, self::HARIC, true) || str_starts_with($t, 'sqlite_'))
            ->values()->all();
        sort($tablolar);

        return $tablolar;
    }

    /** @return array<string, string> */
    private function dosyaKaynaklari(): array
    {
        return [
            'private' => config('filesystems.disks.local.root'),
            'medya' => config('filesystems.disks.medya.root'),
        ];
    }

    /** @return array<string, string> */
    private function kontroller(string $baglanti): array
    {
        $sonuc = [];
        $db = DB::connection($baglanti);

        foreach (self::KONTROL_TOPLAMLARI as $tablo => $sutunlar) {
            if (! Schema::connection($baglanti)->hasTable($tablo)) {
                continue;
            }
            foreach ($sutunlar as $sutun) {
                $sonuc["{$tablo}.{$sutun}"] = (string) (int) $db->table($tablo)->sum($sutun);
            }
        }

        return $sonuc;
    }

    private function ekle(ZipArchive $zip, string $kaynak, string $ad, ?string $parola): void
    {
        $zip->addFile($kaynak, $ad);
        if ($parola) {
            $zip->setEncryptionName($ad, ZipArchive::EM_AES_256, $parola);
        }
    }

    /** @return array{0: ZipArchive, 1: array} */
    private function ac(string $yol): array
    {
        if (! is_file($yol)) {
            throw new RuntimeException('Yedek dosyası bulunamadı: '.$yol);
        }

        $zip = new ZipArchive;
        if ($zip->open($yol) !== true) {
            throw new RuntimeException('Yedek dosyası açılamadı (bozuk olabilir).');
        }
        if ($this->parola()) {
            $zip->setPassword($this->parola());
        }

        $ham = $zip->getFromName('manifest.json');
        $manifest = $ham !== false ? json_decode($ham, true) : null;
        if (! is_array($manifest) || ! isset($manifest['tablolar'])) {
            $zip->close();
            throw new RuntimeException('Yedek okunamadı: manifest yok ya da parola (YEDEK_PAROLA) yanlış.');
        }

        return [$zip, $manifest];
    }

    private function baglantiKur(string $dbYolu): void
    {
        config(['database.connections.'.self::DOGRULAMA_BAGLANTISI => [
            'driver' => 'sqlite',
            'database' => $dbYolu,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge(self::DOGRULAMA_BAGLANTISI);
    }

    /** @param list<string> $hatalar */
    private function veriYukle(ZipArchive $zip, array $manifest, string $baglanti, array &$hatalar, bool $bosalt = false): void
    {
        $db = DB::connection($baglanti);
        $sema = Schema::connection($baglanti);
        $sema->disableForeignKeyConstraints();

        // Canlıya geri yüklemede hepsi ya da hiçbiri: yarıda kalan yükleme tabloları boş bırakmasın.
        // (SQLite'ta yabancı anahtar ayarı transaction içinde değişmez; bu yüzden önce kapatılır.)
        $yukle = function () use ($zip, $manifest, $db, $sema, &$hatalar, $bosalt) {
            foreach (array_keys($manifest['tablolar']) as $tablo) {
                if ($tablo === 'migrations' || ! $sema->hasTable($tablo)) {
                    continue;
                }

                $icerik = $zip->getFromName("veri/{$tablo}.jsonl");
                if ($icerik === false) {
                    $hatalar[] = "Tablo verisi yedekte yok: {$tablo}";

                    continue;
                }

                if ($bosalt) {
                    $db->table($tablo)->delete();
                }

                $sutunlar = array_flip($sema->getColumnListing($tablo));
                $parti = [];
                foreach (explode("\n", $icerik) as $satir) {
                    if ($satir === '') {
                        continue;
                    }
                    $parti[] = array_intersect_key(json_decode($satir, true), $sutunlar);
                    if (count($parti) === 200) {
                        $db->table($tablo)->insert($parti);
                        $parti = [];
                    }
                }
                if ($parti) {
                    $db->table($tablo)->insert($parti);
                }
            }
        };

        try {
            $bosalt ? $db->transaction($yukle) : $yukle();
        } catch (\Throwable $e) {
            $hatalar[] = 'Veri yüklenemedi: '.$e->getMessage();
        } finally {
            $sema->enableForeignKeyConstraints();
        }
    }
}
