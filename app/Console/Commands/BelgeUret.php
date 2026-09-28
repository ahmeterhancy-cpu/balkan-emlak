<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Support\Yetki;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Teslim belgelerini (şartname 19) KODDAN üretir: yetki tablosu ve veritabanı
 * şeması. Elle yazılan belge kodla ayrışır; bu ikisi her değişiklikte
 * `php artisan balkan:belgeler` ile yenilenir. tests/Feature/BelgelerTest
 * belge güncel değilse kırmızı yanar.
 */
class BelgeUret extends Command
{
    protected $signature = 'balkan:belgeler {--kontrol : Yazmadan, belgeler güncel mi diye bak}';

    protected $description = 'docs/YETKI-TABLOSU.md ve docs/VERITABANI.md belgelerini koddan üretir';

    /** Tablo → kısa açıklama. Yeni tablo eklenince buraya da yazılmalı (test uyarır). */
    public const TABLOLAR = [
        'users' => ['Sistem', 'Panel kullanıcıları; rol, etkinlik ve herkese açık ekip profili.'],
        'islem_kayitlari' => ['Sistem', 'İşlem geçmişi: kim, ne zaman, önceki/sonraki değer. Yalnız eklenir.'],
        'ayarlar' => ['Sistem', 'Site ayarları (iletişim, sosyal medya, görseller) — anahtar/değer.'],
        'yonlendirmeler' => ['Web içeriği', 'Eski adres → yeni adres (301/302). Yalnız 404 olacak isteklerde çalışır.'],
        'sayfalar' => ['Web içeriği', 'Kurumsal ve yasal sayfalar; yasal metinde "inceleme bekliyor" işareti.'],
        'hizmetler' => ['Web içeriği', 'Site yönetimi hizmetleri (vitrindeki 8 hizmet).'],
        'yazilar' => ['Web içeriği', 'Bilgi merkezi yazıları; taslak / yayında / arşiv, ileri tarihli yayın.'],
        'referanslar' => ['Web içeriği', 'Referans ve müşteri görüşleri; izin alınmadan yayımlanamaz.'],
        'sss' => ['Web içeriği', 'Sık sorulan sorular.'],
        'konumlar' => ['Emlak', 'İl → ilçe → mahalle ağacı.'],
        'kisiler' => ['Ortak', 'Kişi / kurum: mülk sahibi, müşteri, malik, kiracı. Herkese açık değil.'],
        'portfoyler' => ['Emlak', 'Gayrimenkul + ilan. Operasyon ve yayın durumu ayrı; iç bilgiler gizli.'],
        'portfoy_medya' => ['Emlak', 'İlan fotoğrafları ve kat planları (kapak, sıra, kullanım hakkı).'],
        'portfoy_belgeleri' => ['Emlak', 'Özel belgeler (tapu, yetki belgesi) — web kökü dışında.'],
        'fiyat_gecmisi' => ['Emlak', 'İlan fiyat değişiklikleri; yalnız eklenir.'],
        'talepler' => ['Emlak', 'Web başvuruları ve müşteri talepleri (iletişim, ilan bilgi, portföy başvurusu, yönetim teklifi).'],
        'talep_notlari' => ['Emlak', 'Talep görüşme notları.'],
        'talep_portfoy' => ['Emlak', 'Talebin ilgilendiği portföyler.'],
        'randevular' => ['Emlak', 'Yer gösterme randevuları.'],
        'komisyonlar' => ['Emlak', 'Şirket komisyon geliri takibi (resmi muhasebe değildir; site paralarından ayrı).'],
        'siteler' => ['Site yönetimi', 'Yönetilen site / apartman; onay limiti, kapalı dönem.'],
        'site_kullanici' => ['Site yönetimi', 'Kullanıcı ↔ site yetki ataması.'],
        'bloklar' => ['Site yönetimi', 'Bloklar.'],
        'bagimsiz_bolumler' => ['Site yönetimi', 'Bağımsız bölümler: m², arsa payı, tür.'],
        'bolum_iliskileri' => ['Site yönetimi', 'Malik / kiracı ilişkisi, başlangıç–bitiş tarihli; borç sorumlusu.'],
        'hesaplar' => ['Site finansı', 'Sitenin kasa / banka hesapları.'],
        'hesap_hareketleri' => ['Site finansı', 'Kasa/banka defteri; bakiye = toplam. Değişmez (mutabakat işareti hariç).'],
        'borclandirmalar' => ['Site finansı', 'Toplu tahakkuk işi; (site, dönem, tür, etiket) benzersiz — tekrar çalışmaz.'],
        'borclar' => ['Site finansı', 'Bölüm borçları; düzeltme işaretli karşı kayıt. Değişmez.'],
        'tahsilatlar' => ['Site finansı', 'Tahsilat (+) ve iade (−); dekontlu olan doğrulanana kadar işlenmez. Değişmez.'],
        'tahsilat_dagitimlari' => ['Site finansı', 'Tahsilat → borç dağıtımı; geri alma eksi satırla. Değişmez.'],
        'tedarikciler' => ['Site finansı', 'Tedarikçiler (tüm sitelerde ortak).'],
        'giderler' => ['Site finansı', 'Giderler; onay durumu sütunda, ödeme durumu ödemelerden hesaplanır.'],
        'gider_odemeleri' => ['Site finansı', 'Gider ödemeleri (+) ve geri almalar (−). Değişmez.'],
        'transferler' => ['Site finansı', 'Aynı sitenin hesapları arası transfer; gelir/gider değildir.'],
        'varliklar' => ['Site operasyonu', 'Bakımı izlenen ekipman (asansör, hidrofor...).'],
        'bakim_planlari' => ['Site operasyonu', 'Periyodik bakım planları.'],
        'is_emirleri' => ['Site operasyonu', 'Arıza / iş emirleri.'],
        'gorevler' => ['Site operasyonu', 'Personel görevleri.'],
        'duyurular' => ['Site operasyonu', 'Site / blok duyuruları.'],
        'site_belgeleri' => ['Site operasyonu', 'Yönetim planı, karar, tutanak... sürümlü, özel diskte.'],
        'migrations' => ['Altyapı', 'Laravel göç kayıtları.'],
        'sessions' => ['Altyapı', 'Oturumlar.'],
        'cache' => ['Altyapı', 'Önbellek.'],
        'cache_locks' => ['Altyapı', 'Önbellek kilitleri.'],
        'jobs' => ['Altyapı', 'Kuyruk işleri.'],
        'job_batches' => ['Altyapı', 'Kuyruk iş grupları.'],
        'failed_jobs' => ['Altyapı', 'Başarısız kuyruk işleri.'],
        'password_reset_tokens' => ['Altyapı', 'Parola sıfırlama jetonları.'],
    ];

    public function handle(): int
    {
        $belgeler = [
            base_path('docs/YETKI-TABLOSU.md') => $this->yetkiTablosu(),
            base_path('docs/VERITABANI.md') => $this->veritabani(),
        ];

        $eskimis = [];
        foreach ($belgeler as $yol => $icerik) {
            $mevcut = File::exists($yol) ? str_replace("\r\n", "\n", File::get($yol)) : null;
            if ($mevcut !== $icerik) {
                $eskimis[] = basename($yol);
                if (! $this->option('kontrol')) {
                    File::ensureDirectoryExists(dirname($yol));
                    File::put($yol, $icerik);
                }
            }
        }

        if ($this->option('kontrol')) {
            if ($eskimis) {
                $this->error('Güncel değil: '.implode(', ', $eskimis).' — php artisan balkan:belgeler');

                return self::FAILURE;
            }
            $this->info('Belgeler güncel.');

            return self::SUCCESS;
        }

        $this->info($eskimis ? 'Yenilendi: '.implode(', ', $eskimis) : 'Belgeler zaten güncel.');

        return self::SUCCESS;
    }

    public function yetkiTablosu(): string
    {
        $roller = Rol::cases();
        $s = "# Yetki tablosu\n\n";
        $s .= "> Bu belge koddan üretilir (`php artisan balkan:belgeler`); elle düzenlemeyin. Kaynak: `app/Support/Yetki.php`.\n\n";
        $s .= "Menü gizlemek yetmez: her izin sunucu tarafında politikalarla (`app/Policies`) da denetlenir.\n";
        $s .= "\"Tümünü görme\" izni olmayan kullanıcı yalnız kendi kayıtlarını görür (danışman: sorumlu olduğu portföy, kendisine atanan talep, kendi randevusu).\n";
        $s .= "Site yönetiminde \"Tüm siteleri görme\" izni yoksa kullanıcı yalnız **atandığı siteleri** görür (Siteler → Kullanıcılar); saha personeli yalnız kendisine atanan iş emri ve görevi görür.\n\n";

        $s .= '| İzin | '.implode(' | ', array_map(fn (Rol $r) => $r->getLabel(), $roller))." |\n";
        $s .= '|---|'.str_repeat(':---:|', count($roller))."\n";

        $roleGore = [];
        foreach ($roller as $r) {
            $roleGore[$r->value] = array_flip(Yetki::rolIzinleri($r));
        }

        foreach (Yetki::IZINLER as $izin => $ad) {
            $s .= "| {$ad} <br><sub>`{$izin}`</sub> | ";
            $s .= implode(' | ', array_map(fn (Rol $r) => isset($roleGore[$r->value][$izin]) ? '✓' : '', $roller));
            $s .= " |\n";
        }

        $s .= "\n## Değişmeyen kurallar (role bağlı değil)\n\n";
        $s .= "- Mali kayıtlar (borç, tahsilat, dağıtım, hesap hareketi, gider ödemesi, transfer) kimse tarafından silinemez ya da tutarı değiştirilemez; düzeltme ve iade gerekçeli karşı kayıtla yapılır.\n";
        $s .= "- Onaylanmış gider düzenlenemez; site onay limitini aşan gideri yalnız \"Onay limitini aşan gider onayı\" izni olan onaylar.\n";
        $s .= "- Kapalı döneme kayıt yalnız \"Kapalı dönemde gerekçeli düzeltme\" izni ve gerekçeyle.\n";
        $s .= "- İşlem geçmişi yalnız okunur. Kullanıcı kendi rolünü ve etkinliğini değiştiremez, kendini silemez.\n";
        $s .= "- Özel belgeler ve dekontlar web kökü dışında; indirme her seferinde yetki denetimi ve işlem kaydıyla.\n";

        return $s;
    }

    public function veritabani(): string
    {
        $tablolar = collect(Schema::getTableListing(schemaQualified: false))
            ->reject(fn ($t) => str_starts_with($t, 'sqlite_'))
            ->sort()->values();

        $gruplar = $tablolar->groupBy(fn ($t) => self::TABLOLAR[$t][0] ?? 'Tanımsız');
        $sira = ['Emlak', 'Ortak', 'Site yönetimi', 'Site finansı', 'Site operasyonu', 'Web içeriği', 'Sistem', 'Altyapı', 'Tanımsız'];

        $s = "# Veritabanı şeması\n\n";
        $s .= "> Bu belge koddan üretilir (`php artisan balkan:belgeler`); elle düzenlemeyin. Kaynak: `database/migrations`.\n\n";
        $s .= "Kurallar: tutarlar **kuruş cinsinden tam sayı** (`*_kurus`; mali tablolarda işaretli: tahsilat +, iade −). ";
        $s .= "Saatsiz tarihler `Y-m-d` saklanır (`App\\Casts\\Gun`). Siteye bağlı her kayıtta `site_id` vardır. ";
        $s .= "Türler geliştirme veritabanından (SQLite) okunur; MySQL'de `varchar`/`bigint`/`date` karşılıklarıdır.\n\n";

        foreach ($sira as $grup) {
            if (! isset($gruplar[$grup])) {
                continue;
            }
            $s .= "## {$grup}\n\n";

            foreach ($gruplar[$grup] as $tablo) {
                $s .= "### `{$tablo}`\n\n".(self::TABLOLAR[$tablo][1] ?? '_Açıklama yok._')."\n\n";

                if ($grup === 'Altyapı') {
                    continue;
                }

                $yabanci = collect(Schema::getForeignKeys($tablo))
                    ->mapWithKeys(fn ($f) => [$f['columns'][0] => $f['foreign_table'].'.'.$f['foreign_columns'][0].' ('.$this->silme($f['on_delete']).')']);

                $s .= "| Sütun | Tür | Boş olabilir | Varsayılan | Bağlantı |\n|---|---|:---:|---|---|\n";
                foreach (Schema::getColumns($tablo) as $c) {
                    $varsayilan = $c['default'] === null ? '' : '`'.trim((string) $c['default'], "'\"").'`';
                    $s .= "| `{$c['name']}` | {$c['type']} | ".($c['nullable'] ? 'evet' : '')." | {$varsayilan} | ".($yabanci[$c['name']] ?? '')." |\n";
                }

                $benzersiz = collect(Schema::getIndexes($tablo))->filter(fn ($i) => $i['unique'] && ! $i['primary'])
                    ->map(fn ($i) => '`'.implode(', ', $i['columns']).'`');
                if ($benzersiz->isNotEmpty()) {
                    $s .= "\nBenzersiz: ".$benzersiz->implode('; ')."\n";
                }
                $s .= "\n";
            }
        }

        return $s;
    }

    private function silme(string $kural): string
    {
        return match (strtolower($kural)) {
            'cascade' => 'silinince birlikte silinir',
            'set null' => 'silinince boşalır',
            'restrict' => 'bağlıyken silinemez',
            default => 'silme kısıtı yok',
        };
    }
}
