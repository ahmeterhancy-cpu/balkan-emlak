{{-- Panel giriş sayfası: solda marka alanı (yalnız geniş ekranda), sağda Filament'in kendi formu. --}}
<style>
    .fi-simple-layout { min-height: 100dvh; }
    .giris-yan { display: none; }
    .fi-simple-main { border-radius: 10px; box-shadow: 0 1px 2px rgba(20, 45, 62, .06), 0 12px 32px -12px rgba(20, 45, 62, .18); }
    .fi-simple-layout .fi-logo { font-size: 1.05rem; letter-spacing: .01em; }

    @media (min-width: 1024px) {
        .fi-simple-layout { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); align-items: stretch; }
        .fi-simple-main { box-shadow: none; background: transparent; }
        .fi-simple-main .fi-logo { display: none; } /* marka solda zaten var */
        .giris-yan {
            position: relative; display: flex; flex-direction: column; justify-content: space-between;
            padding: 48px 56px; color: #fff; overflow: hidden;
            background: #142D3E url('{{ site_gorsel('hero_gorsel', 'varsayilan-kahraman.jpg') }}') center / cover no-repeat;
        }
        .giris-yan::before { content: ''; position: absolute; inset: 0; background: rgba(15, 33, 46, .86); }
        .giris-yan > * { position: relative; }
        .fi-simple-main-ctn { background: #fff; }
        .dark .fi-simple-main-ctn { background: transparent; }
    }

    .giris-marka { display: flex; flex-direction: column; line-height: 1.1; color: #fff; text-decoration: none; }
    .giris-marka b { font-size: 26px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .giris-marka span { margin-top: 4px; font-size: 12px; letter-spacing: .14em; text-transform: uppercase; color: #b5d36a; }
    .giris-orta h2 { margin: 0 0 14px; max-width: 30ch; font-size: 30px; font-weight: 600; line-height: 1.25; }
    .giris-orta p { margin: 0; max-width: 46ch; font-size: 15px; line-height: 1.6; color: rgba(255, 255, 255, .78); }
    .giris-liste { display: grid; gap: 14px; margin: 32px 0 0; padding: 0; list-style: none; }
    .giris-liste li { display: flex; gap: 12px; align-items: flex-start; font-size: 14.5px; color: rgba(255, 255, 255, .9); }
    .giris-liste li::before {
        content: ''; flex: none; width: 8px; height: 8px; margin-top: 7px;
        border-radius: 2px; background: #94ba30;
    }
    .giris-alt { display: flex; justify-content: space-between; gap: 16px; font-size: 13px; color: rgba(255, 255, 255, .6); }
    .giris-alt a { color: rgba(255, 255, 255, .85); text-decoration: none; }
    .giris-alt a:hover { color: #fff; text-decoration: underline; }

    .giris-siteye { display: block; margin-top: 20px; text-align: center; font-size: 13.5px; color: var(--gray-500); }
    .giris-siteye a { color: var(--primary-500); font-weight: 500; text-decoration: none; }
    .dark .giris-siteye a { color: var(--primary-400); }
    .giris-siteye a:hover { text-decoration: underline; }
</style>

<aside class="giris-yan" aria-label="Panel hakkında">
    <span class="giris-marka"><b>Balkan</b><span>Emlak ve Yönetim</span></span>

    <div class="giris-orta">
        <h2>Portföy, site finansı ve operasyon tek panelde.</h2>
        <p>İlanlar, müşteri talepleri, aidat ve tahsilatlar, giderler ve iş emirleri; her kayıt yetkiye göre ve işlem geçmişiyle tutulur.</p>
        <ul class="giris-liste">
            <li>Emlak portföyü, talepler ve randevular</li>
            <li>Borçlandırma, tahsilat, gider onayı ve kasa / banka</li>
            <li>Arıza, iş emri, bakım planı ve site raporları</li>
        </ul>
    </div>

    <div class="giris-alt">
        <span>© {{ now()->year }} {{ ayar('firma_adi') }}</span>
        <span>Yalnız yetkili personel içindir</span>
    </div>
</aside>
