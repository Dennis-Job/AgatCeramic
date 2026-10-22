<?php

namespace App\Mail;

use App\Models\ContactRequest;
use App\Models\SiteSetting;
use App\Support\RussianPhoneNumber;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactConfirmationMail extends Mailable
{
    public function __construct(public readonly ContactRequest $contact, private readonly ?SiteSetting $settings) {}

    public function envelope(): Envelope
    {
        $address = $this->contact->type === 'partner'
            ? ($this->settings?->partner_email ?: $this->settings?->email)
            : $this->settings?->email;

        return new Envelope(
            subject: "Ваше обращение №{$this->contact->id} принято — AgatCeramic",
            replyTo: is_string($address) && filter_var($address, FILTER_VALIDATE_EMAIL)
                ? [new Address($address, 'AgatCeramic')] : [],
        );
    }

    public function content(): Content
    {
        $intro = $this->contact->type === 'partner'
            ? 'Спасибо за интерес к сотрудничеству! Мы получили ваше предложение. Наша команда рассмотрит его и свяжется с вами по указанным контактам.'
            : 'Спасибо за обращение! Мы получили ваше сообщение и свяжемся с вами по указанным контактам.';
        $name = $this->contact->name ? '<p>Здравствуйте, '.e($this->contact->name).'!</p>' : '<p>Здравствуйте!</p>';
        // Inline styles and a fluid presentation table work without web fonts or external assets in email clients.
        $html = '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            .'<body style="margin:0;background:#f2f1ee;color:#141414;font-family:Arial,Helvetica,sans-serif;line-height:1.6">'
            .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f2f1ee"><tr><td align="center" style="padding:24px 12px">'
            .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;table-layout:fixed;overflow-wrap:anywhere;word-break:break-word">'
            .'<tr><td style="padding:24px;border-bottom:1px solid #e3e1db"><div style="font-family:Arial,Helvetica,sans-serif;font-size:27px;letter-spacing:-1px;white-space:nowrap"><span style="font-weight:600">AGAT</span><span style="font-weight:400">CERAMIC</span></div>'
            .'<div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#6e6b66">Керамика. Пространство. Детали.</div></td></tr>'
            .'<tr><td style="padding:32px 24px;background:#fafaf8"><h1 style="font-family:Georgia,serif;font-size:28px;font-weight:400;line-height:1.2;margin:0 0 24px">Ваше обращение принято</h1>'
            .$name.'<p>'.e($intro).'</p><p>Номер обращения: <strong>'.$this->contact->id.'</strong></p>'
            .'<p style="color:#6e6b66;font-size:14px">Это автоматическое подтверждение получения обращения.</p></td></tr>'
            .'<tr><td style="padding:24px;border-top:1px solid #e3e1db;font-size:14px"><h2 style="font-size:16px;font-weight:400;letter-spacing:1px">Контакты AgatCeramic</h2>'
            .$this->contactDetails().'</td></tr></table></td></tr></table></body></html>';

        return new Content(htmlString: $html);
    }

    private function contactDetails(): string
    {
        if ($this->settings === null) {
            return '';
        }
        $contacts = '';
        if ($this->settings->seller_name) {
            $contacts .= '<p>'.e($this->settings->seller_name).'</p>';
        }
        foreach ($this->settings->phones ?? [] as $phone) {
            $canonical = RussianPhoneNumber::normalize($phone);
            if ($canonical !== null) {
                $contacts .= '<p><a style="color:#141414" href="tel:'.e($canonical).'">'.e(RussianPhoneNumber::format($canonical)).'</a></p>';
            }
        }
        foreach (['email' => 'Для покупателей', 'partner_email' => 'Для сотрудничества'] as $key => $label) {
            $address = $this->settings->getAttribute($key);
            if (is_string($address) && filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $contacts .= '<p>'.e($label).': <a style="color:#141414" href="mailto:'.e($address).'">'.e($address).'</a></p>';
            }
        }
        if ($this->settings->address) {
            $contacts .= '<p>'.nl2br(e($this->settings->address)).'</p>';
        }

        return $contacts;
    }
}
