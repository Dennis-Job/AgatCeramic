<?php

namespace App\Mail;

use App\Models\ContactRequest;
use App\Support\RussianPhoneNumber;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactRequestMail extends Mailable
{
    public function __construct(public readonly ContactRequest $contact) {}

    public function envelope(): Envelope
    {
        $kind = $this->contact->type === 'partner' ? 'на сотрудничество' : 'с сайта';

        return new Envelope(subject: "Новая заявка {$kind} №{$this->contact->id} — AgatCeramic");
    }

    public function content(): Content
    {
        $adminUrl = config('admin.url');
        $url = rtrim(is_string($adminUrl) ? $adminUrl : '', '/').'/contacts?contact='.$this->contact->id;
        $details = '<p>Номер обращения: <strong>'.$this->contact->id.'</strong></p>'
            .'<p>Имя: '.e($this->contact->name ?? 'Не указано').'</p>'
            .'<p>Email: '.e($this->contact->email).'</p>';
        if ($this->contact->phone !== null) {
            $details .= '<p>Телефон: '.e(RussianPhoneNumber::format($this->contact->phone)).'</p>';
        }
        $details .= '<p>Сообщение:</p><p>'.nl2br(e($this->contact->message)).'</p>'
            .'<p><a href="'.e($url).'">Открыть обращение в админке</a></p>';

        return new Content(htmlString: '<!doctype html><html lang="ru"><body><h1>'.e($this->envelope()->subject).'</h1>'.$details.'</body></html>');
    }
}
