/*
 * Balkan Emlak ve Yönetim — vitrin davranışları. Kütüphane yok.
 * Her parça, işaretlediği öğe sayfada yoksa sessizce atlanır. JS
 * çalışmazsa sayfa kullanılabilir kalır: menü bağlantıları, formlar ve
 * süzgeçler düz HTML olarak çalışır, galeride ilk fotoğraf görünür.
 */

const $ = (sec, kok = document) => kok.querySelector(sec);
const $$ = (sec, kok = document) => Array.from(kok.querySelectorAll(sec));

/* Dokunmatik kaydırma */
function kaydirmaDinle(el, ileri, geri) {
    if (!el) return;
    let x = null;
    el.addEventListener('touchstart', (e) => (x = e.touches[0].clientX), { passive: true });
    el.addEventListener('touchend', (e) => {
        if (x === null) return;
        const fark = e.changedTouches[0].clientX - x;
        if (Math.abs(fark) > 40) (fark < 0 ? ileri : geri)();
        x = null;
    });
}

/* ---------- Mobil çekmece ---------- */
(() => {
    const cekmece = $('[data-cekmece]');
    const ac = $('[data-cekmece-ac]');
    if (!cekmece || !ac) return;

    let oncekiOdak = null;

    const kapat = () => {
        if (!cekmece.classList.contains('acik')) return;
        cekmece.classList.remove('acik');
        cekmece.setAttribute('aria-hidden', 'true');
        ac.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        oncekiOdak?.focus();
    };

    ac.addEventListener('click', () => {
        oncekiOdak = document.activeElement;
        cekmece.classList.add('acik');
        cekmece.setAttribute('aria-hidden', 'false');
        ac.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        $('.cekmece-panel a, .cekmece-panel button', cekmece)?.focus();
    });

    $$('[data-cekmece-kapat]', cekmece).forEach((el) => el.addEventListener('click', kapat));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') kapat();
    });
    window.matchMedia('(min-width: 992px)').addEventListener('change', (e) => e.matches && kapat());
})();

/* ---------- Masaüstü açılır menü: dokunmatikte ilk dokunuş açar ---------- */
(() => {
    $$('.ana-menu > li').forEach((li) => {
        const baglanti = li.querySelector(':scope > a[aria-haspopup]');
        if (!baglanti) return;
        baglanti.addEventListener('click', (e) => {
            if (window.matchMedia('(hover: none)').matches && !li.classList.contains('acik')) {
                e.preventDefault();
                $$('.ana-menu > li.acik').forEach((d) => d.classList.remove('acik'));
                li.classList.add('acik');
            }
        });
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.ana-menu')) $$('.ana-menu > li.acik').forEach((d) => d.classList.remove('acik'));
    });
})();

/* ---------- Ana sayfa arama: sekmeler + oda alanı ---------- */
(() => {
    const form = $('[data-arama]');
    if (!form) return;
    const gizli = $('[data-arama-islem]', form);

    $$('[data-islem]', form).forEach((dugme) => {
        dugme.addEventListener('click', () => {
            $$('[data-islem]', form).forEach((d) => d.setAttribute('aria-pressed', String(d === dugme)));
            gizli.value = dugme.dataset.islem;
        });
    });

    // Boş alanları adrese koyma: paylaşılan bağlantı temiz kalsın
    form.addEventListener('submit', () => {
        $$('input, select', form).forEach((el) => {
            if (!el.value || el.closest('[hidden]')) el.disabled = true;
        });
    });

    const tur = $('[data-kategori-secimi]', form);
    const oda = $('[data-oda-alani]', form);
    if (tur && oda) {
        const guncelle = () => {
            const k = tur.value.split(':')[0];
            const goster = !k || k === 'konut';
            oda.hidden = !goster;
            if (!goster) $('select', oda).value = '';
        };
        tur.addEventListener('change', guncelle);
        guncelle();
    }
})();

/* ---------- Portföy süzgeçleri: bağımlı il/ilçe/mahalle, alt tür, oda ---------- */
(() => {
    const form = $('[data-suzgec-form]');
    if (!form) return;

    const agac = JSON.parse(form.dataset.konumAgaci || '{}');
    const tipler = JSON.parse(form.dataset.tipler || '{}');
    const il = $('[data-il]', form);
    const ilce = $('[data-ilce]', form);
    const mahalle = $('[data-mahalle]', form);
    const kategori = $('[data-kategori]', form);
    const tip = $('[data-tip]', form);
    const tipAlani = $('[data-tip-alani]', form);
    const odaAlani = $('[data-oda-alani]', form);

    const doldur = (secim, secenekler, secili) => {
        secim.innerHTML = '';
        secim.append(new Option('Tümü', ''));
        Object.entries(secenekler).forEach(([deger, ad]) => {
            secim.append(new Option(ad, deger, false, String(deger) === String(secili)));
        });
    };

    const ilceleriDoldur = () => {
        const ilceler = {};
        Object.entries(agac).forEach(([ilId, veri]) => {
            if (!il.value || il.value === ilId) {
                Object.entries(veri.ilceler).forEach(([id, v]) => (ilceler[id] = v.ad));
            }
        });
        doldur(ilce, ilceler, ilce.value);
    };

    const mahalleleriDoldur = () => {
        let mahalleler = {};
        Object.values(agac).forEach((veri) => {
            if (veri.ilceler[ilce.value]) mahalleler = veri.ilceler[ilce.value].mahalleler;
        });
        doldur(mahalle, mahalleler, mahalle.value);
        mahalle.disabled = !ilce.value;
    };

    if (il && ilce && mahalle) {
        il.addEventListener('change', () => { ilce.value = ''; ilceleriDoldur(); mahalleleriDoldur(); });
        ilce.addEventListener('change', () => { mahalle.value = ''; mahalleleriDoldur(); });
        mahalle.disabled = !ilce.value;
    }

    if (kategori && tip && tipAlani && odaAlani) {
        kategori.addEventListener('change', () => {
            const k = kategori.value;
            tipAlani.hidden = !k;
            doldur(tip, k ? tipler[k] || {} : {}, '');
            const odaGoster = !k || k === 'konut';
            odaAlani.hidden = !odaGoster;
            if (!odaGoster) $('select', odaAlani).value = '';
        });
    }

    form.addEventListener('submit', () => {
        $$('input, select', form).forEach((el) => {
            if (!el.value || el.closest('[hidden]')) el.disabled = true;
        });
    });

    // Mobilde süzgeç kutusu kapalı başlasın (JS yoksa açık kalır)
    const kutu = $('[data-suzgec]');
    if (kutu && window.matchMedia('(max-width: 991px)').matches) kutu.open = false;
})();

/* Sıralama değişince listeyi yenile */
$$('[data-otomatik-gonder] select').forEach((s) => s.addEventListener('change', () => s.form.submit()));

/* ---------- Kaydırıcı (öne çıkan ilanlar) ---------- */
$$('[data-kaydirici]').forEach((kok) => {
    const slaytlar = $$('.kaydirici-slayt', kok);
    const noktalar = $$('[data-noktalar] button', kok);
    if (slaytlar.length < 2) return;
    let simdiki = 0;

    const git = (i) => {
        simdiki = (i + slaytlar.length) % slaytlar.length;
        slaytlar.forEach((s, j) => {
            const etkin = j === simdiki;
            s.classList.toggle('etkin', etkin);
            s.setAttribute('aria-hidden', String(!etkin));
            // Görsel bağlantısı hep sekme dışı; kart bağlantısı yalnız görünen slaytta odaklanır
            $$('.one-cikan-kart a', s).forEach((a) => (etkin ? a.removeAttribute('tabindex') : a.setAttribute('tabindex', '-1')));
        });
        noktalar.forEach((n, j) => (j === simdiki ? n.setAttribute('aria-current', 'true') : n.removeAttribute('aria-current')));
    };

    $('[data-onceki]', kok)?.addEventListener('click', () => git(simdiki - 1));
    $('[data-sonraki]', kok)?.addEventListener('click', () => git(simdiki + 1));
    noktalar.forEach((n, j) => n.addEventListener('click', () => git(j)));
    kaydirmaDinle(kok, () => git(simdiki + 1), () => git(simdiki - 1));
});

/* ---------- İlan galerisi ---------- */
$$('[data-galeri]').forEach((kok) => {
    const gorseller = $$('[data-galeri-gorsel]', kok);
    const kucukler = $$('[data-galeri-kucuk]', kok);
    const sayac = $('[data-galeri-sayac]', kok);
    if (gorseller.length < 2) return;
    let simdiki = 0;

    const git = (i) => {
        simdiki = (i + gorseller.length) % gorseller.length;
        gorseller.forEach((g, j) => {
            g.classList.toggle('etkin', j === simdiki);
            if (j === simdiki) g.loading = 'eager';
        });
        kucukler.forEach((k, j) => (j === simdiki ? k.setAttribute('aria-current', 'true') : k.removeAttribute('aria-current')));
        const kucuk = kucukler[simdiki];
        if (kucuk) kucuk.parentElement.scrollTo({ left: kucuk.offsetLeft - 40, behavior: 'smooth' });
        if (sayac) sayac.textContent = String(simdiki + 1);
    };

    $('[data-onceki]', kok)?.addEventListener('click', () => git(simdiki - 1));
    $('[data-sonraki]', kok)?.addEventListener('click', () => git(simdiki + 1));
    kucukler.forEach((k) => k.addEventListener('click', () => git(Number(k.dataset.galeriKucuk))));
    kok.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight') git(simdiki + 1);
        if (e.key === 'ArrowLeft') git(simdiki - 1);
    });
    kaydirmaDinle($('.galeri-ana', kok), () => git(simdiki + 1), () => git(simdiki - 1));
});

/* ---------- Bağlantıyı kopyala ---------- */
$$('[data-kopyala]').forEach((d) => {
    d.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(d.dataset.kopyala);
            const eski = d.getAttribute('aria-label');
            d.setAttribute('aria-label', 'Kopyalandı');
            d.style.background = 'var(--yesil-koyu)';
            d.style.color = '#fff';
            setTimeout(() => { d.setAttribute('aria-label', eski); d.style.background = ''; d.style.color = ''; }, 1600);
        } catch {
            window.prompt('Bağlantıyı kopyalayın:', d.dataset.kopyala);
        }
    });
});

/* ---------- Formlar: çift gönderimi engelle ---------- */
$$('[data-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        const dugme = $('button[type="submit"]', form);
        if (!dugme || dugme.getAttribute('aria-disabled') === 'true') return;
        dugme.dataset.metin = dugme.textContent;
        setTimeout(() => {
            dugme.setAttribute('aria-disabled', 'true');
            dugme.textContent = 'Gönderiliyor…';
        }, 0);
        setTimeout(() => { dugme.removeAttribute('aria-disabled'); dugme.textContent = dugme.dataset.metin; }, 8000);
    });
});

/* ---------- Yukarı dön ---------- */
(() => {
    const d = $('[data-yukari]');
    if (!d) return;
    const guncelle = () => d.classList.toggle('gorunur', window.scrollY > 700);
    window.addEventListener('scroll', guncelle, { passive: true });
    d.addEventListener('click', (e) => { e.preventDefault(); window.scrollTo({ top: 0 }); });
    guncelle();
})();
