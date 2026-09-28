<?php

namespace App\Mail;

use App\Models\Talep;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Web formundan yeni başvuru geldi — ofise düz metin bildirim. */
class YeniBasvuru extends Mailable
{
    public function __construct(public Talep $talep)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Yeni başvuru: '.$this->talep->tur->getLabel().' — '.$this->talep->ad_soyad,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'eposta.yeni-basvuru');
    }
}
