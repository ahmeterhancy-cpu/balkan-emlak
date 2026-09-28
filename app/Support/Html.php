<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Panelden gelen zengin metni (ilan açıklaması, hizmet kapsamı, yazı)
 * vitrine basmadan önce temizler. Danışman gibi daha düşük yetkili
 * kullanıcılar da içerik girdiği için RichEditor çıktısına körü körüne
 * güvenilmez: izinli etiket/öznitelik listesi dışındaki her şey atılır,
 * javascript: bağlantıları ve on* olayları düşer.
 */
class Html
{
    private const ETIKETLER = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'h2', 'h3', 'h4',
        'blockquote', 'a', 'span', 'hr', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'img', 'figure', 'figcaption'];

    private const OZNITELIKLER = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
        'span' => ['class'],
        'p' => ['class'],
        'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
    ];

    public static function temizle(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $onceki = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="kok">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($onceki);

        $kok = $dom->getElementById('kok');
        if (! $kok) {
            return e(strip_tags($html));
        }

        self::dugumTemizle($kok);

        $cikti = '';
        foreach ($kok->childNodes as $cocuk) {
            $cikti .= $dom->saveHTML($cocuk);
        }

        return $cikti;
    }

    private static function dugumTemizle(DOMNode $dugum): void
    {
        foreach (iterator_to_array($dugum->childNodes) as $cocuk) {
            if ($cocuk instanceof DOMElement) {
                $etiket = strtolower($cocuk->tagName);

                if (in_array($etiket, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math'], true)) {
                    $dugum->removeChild($cocuk);

                    continue;
                }

                if (! in_array($etiket, self::ETIKETLER, true)) {
                    // Etiketi at, içeriğini koru
                    self::dugumTemizle($cocuk);
                    while ($cocuk->firstChild) {
                        $dugum->insertBefore($cocuk->firstChild, $cocuk);
                    }
                    $dugum->removeChild($cocuk);

                    continue;
                }

                $izinli = self::OZNITELIKLER[$etiket] ?? [];
                foreach (iterator_to_array($cocuk->attributes) as $oz) {
                    $ad = strtolower($oz->name);
                    $deger = trim($oz->value);

                    $gecersizAdres = in_array($ad, ['href', 'src'], true)
                        && ! preg_match('#^(https?://|/|\#|mailto:|tel:)#i', $deger);

                    if (! in_array($ad, $izinli, true) || $gecersizAdres) {
                        $cocuk->removeAttribute($oz->name);
                    }
                }

                if ($etiket === 'a' && $cocuk->getAttribute('target') === '_blank') {
                    $cocuk->setAttribute('rel', 'noopener noreferrer');
                }

                self::dugumTemizle($cocuk);
            } elseif ($cocuk->nodeType === XML_COMMENT_NODE) {
                $dugum->removeChild($cocuk);
            }
        }
    }
}
