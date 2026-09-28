<?php

namespace Database\Seeders;

use App\Models\Hizmet;
use App\Models\Sayfa;
use App\Models\Sss;
use App\Models\Yazi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * İçerik iskeleti. Metinler taslak niteliğindedir:
 *  - Hizmet açıklamaları tanıtım görsellerindeki sekiz başlıktan türetildi;
 *    kapsam işletmeyle teyit edilmeli. "Hukuki yönetim" özellikle
 *    işletmenin fiilen çalıştığı uzmanlara göre yeniden yazılmalı.
 *  - Yasal sayfalar `inceleme_bekliyor` işaretli gelir; sitede
 *    "hukuki incelemeden geçmedi" şeridiyle görünür.
 *
 * firstOrCreate: panelde düzenlenmiş metni yeniden çalıştırınca ezmez.
 */
class IcerikSeeder extends Seeder
{
    public function run(): void
    {
        $this->hizmetler();
        $this->sayfalar();
        $this->sss();

        if (! app()->isProduction()) {
            $this->yazilar();
        }
    }

    private function hizmetler(): void
    {
        $hizmetler = [
            [
                'genel-yonetim', 'Genel Yönetim', 'yonetim', 'apartman-blok',
                'Yönetim planına uygun işleyiş, karar defteri, toplantı hazırlığı ve malikler kurulu kararlarının takibi.',
                '<p>Genel yönetim; sitenin ya da apartmanın günlük işleyişinin yönetim planı ve kat malikleri kurulu kararları doğrultusunda yürütülmesidir.</p><ul><li>Olağan ve olağanüstü toplantıların hazırlığı, çağrı ve tutanakları</li><li>Karar defteri ve işletme projesinin takibi</li><li>Tedarikçi ve hizmet sözleşmelerinin düzenli izlenmesi</li><li>Maliklere dönemsel bilgilendirme</li></ul>',
                ['Keşif ve mevcut durum', 'Yönetim planı ve kararların incelenmesi', 'Yıllık işletme planı', 'Düzenli raporlama'],
            ],
            [
                'hukuki-yonetim', 'Hukuki Yönetim', 'hukuk', null,
                'Yönetimle ilgili hukuki süreçlerin takibi ve gerektiğinde yetkili hukuk profesyoneliyle koordinasyon.',
                '<p>Hukuki yönetim kapsamı, yönetimin yürüttüğü idari süreçlerin mevzuata ve yönetim planına uygun takibidir. Hukuki danışmanlık ve temsil, yalnızca yetkili hukuk profesyonelleri tarafından verilir.</p><ul><li>Toplantı ve karar süreçlerinin usulüne uygun belgelenmesi</li><li>Gecikmiş aidatlar için yazılı bildirim süreçleri</li><li>Gerekli durumlarda yönetim kurulunun yetkilendirdiği avukatla koordinasyon</li></ul><p><em>Bu hizmetin kapsamı işletmeyle teyit edildikten sonra kesinleşecektir.</em></p>',
                ['İhtiyacın tespiti', 'Belgelerin derlenmesi', 'Yetkili uzmana yönlendirme', 'Sürecin takibi'],
            ],
            [
                'temizlik-yonetimi', 'Temizlik Yönetimi', 'temizlik', 'temizlik',
                'Ortak alanların temizlik planı, ekip ve malzeme takibi, düzenli denetim.',
                '<p>Merdiven, asansör, giriş holü, otopark ve çevre gibi ortak alanlar için ihtiyaca göre temizlik planı hazırlanır ve uygulanması takip edilir.</p><ul><li>Günlük / haftalık temizlik takvimi</li><li>Malzeme ve sarf takibi</li><li>Periyodik kalite kontrolü</li></ul>',
                ['Alan ve sıklık planı', 'Ekip görevlendirme', 'Uygulama', 'Denetim ve geri bildirim'],
            ],
            [
                'bakim-ve-onarim', 'Bakım ve Onarım Yönetimi', 'bakim', null,
                'Arıza kayıtları, iş emirleri, onarımların takibi ve maliyet kaydı.',
                '<p>Bildirilen arızalar kayıt altına alınır, önceliklendirilir ve ilgili personele ya da tedarikçiye atanır. Tamamlanan işler notu ve gerekirse fotoğrafıyla kapatılır.</p><ul><li>Arıza kaydı ve önceliklendirme</li><li>Tedarikçi teklifi ve onay süreci</li><li>İşin tamamlanma kontrolü</li></ul><p>Acil durumlar için iletişim bilgileri ayrıca paylaşılır; form yanıtı acil müdahale garantisi değildir.</p>',
                ['Arıza kaydı', 'Önceliklendirme ve atama', 'Onarım', 'Kontrol ve kapanış'],
            ],
            [
                'teknik-yonetim', 'Teknik Yönetim', 'teknik', 'teknik-cizim',
                'Asansör, hidrofor, jeneratör, yangın ekipmanı gibi sistemlerin periyodik bakım planı.',
                '<p>Binadaki teknik sistemler için periyodik bakım takvimi oluşturulur ve bakım kayıtları tutulur.</p><ul><li>Asansör periyodik bakımı ve kontrolleri</li><li>Hidrofor, pompa ve su deposu</li><li>Jeneratör ve elektrik panoları</li><li>Yangın söndürme ekipmanı</li></ul>',
                ['Envanter çıkarma', 'Bakım takvimi', 'Bakımların takibi', 'Kayıt ve raporlama'],
            ],
            [
                'seffaf-aidat-yonetimi', 'Şeffaf Aidat Yönetimi', 'aidat', null,
                'Aidat tahakkuku, tahsilat takibi, gider kayıtları ve maliklere anlaşılır dönem raporu.',
                '<p>Aidatlar onaylanan dağıtım yöntemine göre (eşit, metrekare veya arsa payı) tahakkuk ettirilir. Tahsilatlar ve giderler belgeleriyle kaydedilir; malikler dönemsel olarak bilgilendirilir.</p><ul><li>Bağımsız bölüm bazında borç ve ödeme takibi</li><li>Gider belgeleriyle kayıt</li><li>Dönem sonu gelir-gider raporu</li></ul>',
                ['Dağıtım yönteminin belirlenmesi', 'Dönem tahakkuku', 'Tahsilat ve gider kaydı', 'Dönem raporu'],
            ],
            [
                'personel-yonetimi', 'Personel Yönetimi', 'personel', null,
                'Görevli, güvenlik ve temizlik personelinin görev planı ve iş takibi.',
                '<p>Sitede çalışan personelin görev alanları, çalışma takvimi ve tamamlanan işleri takip edilir.</p><ul><li>Görev tanımları ve vardiya planı</li><li>Görev tamamlama kayıtları</li><li>Performans geri bildirimi</li></ul>',
                ['İhtiyaç analizi', 'Görev tanımı', 'Takvim', 'Takip'],
            ],
            [
                'bahce-bakim-yonetimi', 'Bahçe Bakım Yönetimi', 'bahce', null,
                'Peyzaj alanlarının mevsimsel bakımı, sulama ve budama planı.',
                '<p>Yeşil alanlar için mevsime uygun bakım programı hazırlanır ve uygulanması takip edilir.</p><ul><li>Çim biçme, budama ve ilaçlama takvimi</li><li>Sulama sistemi kontrolü</li><li>Mevsimlik dikim planı</li></ul>',
                ['Alan keşfi', 'Mevsimsel plan', 'Uygulama', 'Kontrol'],
            ],
        ];

        foreach ($hizmetler as $sira => [$slug, $baslik, $ikon, $gorsel, $ozet, $kapsam, $surec]) {
            Hizmet::firstOrCreate(['slug' => $slug], [
                'baslik' => $baslik,
                'ikon' => $ikon,
                'ozet' => $ozet,
                'kapsam' => $kapsam,
                'surec' => $surec,
                'gorsel' => $gorsel ? $this->gorselKopyala($gorsel, 'hizmetler') : null,
                'yayinda' => true,
                'sira' => $sira,
            ]);
        }
    }

    private function sayfalar(): void
    {
        $eksik = '<span class="eksik">[TEYİT BEKLİYOR]</span>';

        Sayfa::firstOrCreate(['slug' => 'hakkimizda'], [
            'baslik' => 'Hakkımızda',
            'grup' => 'kurumsal',
            'yayinda' => true,
            'ozet' => 'Balkan Emlak ve Yönetim; gayrimenkul alım-satım ve kiralama danışmanlığı ile apartman ve site yönetimini tek çatı altında sunar.',
            'icerik' => '<p>Balkan Emlak ve Yönetim iki alanda hizmet verir: satılık ve kiralık gayrimenkullerde danışmanlık, apartman ve sitelerde profesyonel yönetim.</p>'
                .'<h2>Çalışma ilkelerimiz</h2><ul><li><strong>Şeffaflık:</strong> Aidat, gider ve tahsilatlar belgeleriyle raporlanır.</li><li><strong>Düzenli bakım:</strong> Teknik sistemler takvime bağlı bakım planıyla izlenir.</li><li><strong>Kolay iletişim:</strong> Talepleriniz kayda alınır ve sonuçlanana kadar takip edilir.</li><li><strong>Doğru bilgi:</strong> İlanlarda yalnız teyit edilmiş bilgiler yayımlanır.</li></ul>'
                .'<p>Kuruluş yılı, ofis adresi ve ekip bilgileri '.$eksik.'</p>',
        ]);

        $yasalGiris = '<p class="uyari">Bu metin taslaktır; işletmenin gerçek veri işleme süreçlerine göre hukuki incelemeden geçirilmelidir.</p>';

        $yasal = [
            'kvkk-aydinlatma-metni' => ['KVKK Aydınlatma Metni',
                $yasalGiris.'<h2>Veri sorumlusu</h2><p>Unvan: '.$eksik.' · Adres: '.$eksik.'</p><h2>İşlenen veriler</h2><p>İletişim ve başvuru formlarında paylaştığınız ad soyad, telefon, e-posta, talep içeriği ve (varsa) gayrimenkul bilgileri.</p><h2>İşleme amaçları</h2><ul><li>Talebinize dönüş yapılması</li><li>Randevu ve görüşmelerin planlanması</li><li>Yönetim teklifinin hazırlanması</li></ul><h2>Haklarınız</h2><p>6698 sayılı Kanun kapsamındaki haklarınız ve başvuru yöntemi '.$eksik.'</p>'],
            'gizlilik-politikasi' => ['Gizlilik Politikası',
                $yasalGiris.'<p>Web sitemizde formlar aracılığıyla toplanan kişisel veriler yalnız başvurunuzun yanıtlanması amacıyla kullanılır ve üçüncü kişilerle pazarlama amacıyla paylaşılmaz. Ayrıntılar: '.$eksik.'</p>'],
            'cerez-politikasi' => ['Çerez Politikası',
                $yasalGiris.'<p>Bu site yalnız oturum ve güvenlik (form koruması) için zorunlu çerezler kullanır. Reklam veya analiz çerezi kullanılmaz.</p>'],
        ];

        foreach ($yasal as $slug => [$baslik, $icerik]) {
            Sayfa::firstOrCreate(['slug' => $slug], [
                'baslik' => $baslik,
                'grup' => 'yasal',
                'yayinda' => true,
                'inceleme_bekliyor' => true,
                'icerik' => $icerik,
            ]);
        }
    }

    private function sss(): void
    {
        $sorular = [
            ['emlak', 'Gayrimenkulümü satmak veya kiralamak için ne yapmalıyım?', '"Gayrimenkulünüzü Bize Bırakın" formunu doldurmanız yeterli. Danışmanımız sizi arayarak gayrimenkulün bilgilerini ve belgelerini birlikte değerlendirir.'],
            ['emlak', 'İlandaki adres neden tam görünmüyor?', 'Mülk sahibinin gizliliği için ilanlarda genel konum (mahalle ve ilçe) gösterilir. Açık adres, randevu sırasında danışmanımız tarafından paylaşılır.'],
            ['emlak', 'Yer göstermesi için nasıl randevu alırım?', 'İlan sayfasındaki formdan tercih ettiğiniz tarih ve saati iletebilirsiniz. Randevu, danışmanımızın onayıyla kesinleşir.'],
            ['yonetim', 'Yönetim teklifi için hangi bilgiler gerekiyor?', 'Site veya apartman adı, konumu, blok ve bağımsız bölüm sayısı ile ihtiyaç duyduğunuz hizmetler. Keşif sonrasında yazılı teklif hazırlanır.'],
            ['yonetim', 'Aidatlar nasıl hesaplanıyor?', 'Aidat dağıtımı yönetim planına ve kat malikleri kurulu kararına göre eşit, metrekare veya arsa payı esasıyla yapılır. Seçilen yöntem her dönem raporunda belirtilir.'],
            ['yonetim', 'Acil bir arıza olduğunda ne yapmalıyım?', 'Acil durumlarda web formu yerine iletişim sayfasındaki telefon numaralarını arayın. Form yanıtı acil müdahale garantisi değildir.'],
        ];

        foreach ($sorular as $sira => [$grup, $soru, $cevap]) {
            Sss::firstOrCreate(['soru' => $soru], ['grup' => $grup, 'cevap' => $cevap, 'yayinda' => true, 'sira' => $sira]);
        }
    }

    private function yazilar(): void
    {
        $yazilar = [
            ['kiralik-ev-tutarken-kontrol-listesi', 'Kiralık ev tutarken kontrol listesi', 'emlak', 'daire-ic',
                'Sözleşmeden önce bakılması gereken noktalar: depozito, aidat, sayaçlar ve teslim tutanağı.',
                '<p>Kiralık bir evi tutmadan önce birkaç temel noktayı kontrol etmek, ileride çıkabilecek anlaşmazlıkları azaltır.</p><h2>1. Aylık toplam maliyet</h2><p>Kira bedelinin yanında aidat, ısınma ve ortak gider paylarını sorun. İlanımızda aidat ayrıca belirtilir.</p><h2>2. Depozito ve iade koşulları</h2><p>Depozitonun tutarı ve hangi durumda, ne zaman iade edileceği sözleşmede açıkça yazmalıdır.</p><h2>3. Sayaçlar ve abonelikler</h2><p>Elektrik, su ve doğalgaz sayaçlarının son değerlerini teslim günü birlikte not edin.</p><h2>4. Teslim tutanağı</h2><p>Evin mevcut durumunu fotoğraflarla birlikte bir tutanağa bağlayın; çıkışta aynı tutanak esas alınır.</p>'],
            ['aidat-dagitim-yontemleri', 'Aidat dağıtım yöntemleri: eşit, metrekare ve arsa payı', 'yonetim', 'site-tugla',
                'Apartman ve sitelerde ortak giderlerin paylaştırılmasında kullanılan yöntemler ve farkları.',
                '<p>Ortak giderlerin maliklere nasıl dağıtılacağı, yönetim planı ve kat malikleri kurulu kararıyla belirlenir.</p><h2>Eşit dağıtım</h2><p>Her bağımsız bölüm aynı tutarı öder. Bölümlerin büyüklükleri birbirine yakınsa anlaşılır bir yöntemdir.</p><h2>Metrekareye göre</h2><p>Gider, bağımsız bölümlerin alanlarıyla orantılı paylaştırılır.</p><h2>Arsa payına göre</h2><p>Tapuda yazan arsa payı oranı esas alınır.</p><p>Hangi yöntem seçilirse seçilsin, dönem raporunda açıkça belirtilmesi şeffaflığın temelidir.</p>'],
            ['satis-oncesi-gayrimenkul-hazirligi', 'Satış öncesi gayrimenkul hazırlığı', 'emlak', 'salon-4',
                'Gayrimenkulünüzü satışa çıkarmadan önce belge, fotoğraf ve fiyat hazırlığı.',
                '<p>İyi hazırlanmış bir ilan, doğru alıcıya daha kısa sürede ulaşır.</p><h2>Belgeler</h2><p>Tapu, varsa iskân ve yapı kullanma izin belgesi ile güncel aidat durumu hazır olmalı.</p><h2>Fotoğraf</h2><p>Gün ışığında, toplanmış ve havalandırılmış odalarda çekilen fotoğraflar ilanın ilk izlenimini belirler.</p><h2>Fiyat</h2><p>Bölgedeki benzer ilanlar ve gerçekleşen satışlar birlikte değerlendirilerek fiyat belirlenir.</p>'],
        ];

        foreach ($yazilar as $i => [$slug, $baslik, $kategori, $gorsel, $ozet, $icerik]) {
            Yazi::firstOrCreate(['slug' => $slug], [
                'baslik' => $baslik,
                'kategori' => $kategori,
                'ozet' => $ozet,
                'icerik' => $icerik,
                'gorsel' => $this->gorselKopyala($gorsel, 'yazilar'),
                'gorsel_alt' => $baslik,
                'durum' => 'yayinda',
                'yayin_tarihi' => now()->subDays(10 * ($i + 1)),
            ]);
        }
    }

    /** Demo görseli medya diskine kopyala; yolunu döndür. */
    public static function gorselKopyala(string $ad, string $klasor): ?string
    {
        $kaynak = database_path("seeders/demo-gorseller/{$ad}.jpg");
        if (! File::exists($kaynak)) {
            return null;
        }

        $hedef = "{$klasor}/demo-{$ad}.jpg";
        if (! Storage::disk('medya')->exists($hedef)) {
            Storage::disk('medya')->put($hedef, File::get($kaynak));
        }

        return $hedef;
    }
}
