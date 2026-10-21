<?php

namespace App\Mail;

use App\Models\ContactRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CallbackRequestMail extends Mailable
{
    public function __construct(public readonly ContactRequest $contact) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Новая заявка на обратный звонок №{$this->contact->id} — AgatCeramic");
    }

    public function content(): Content
    {
        $phone = $this->contact->phone ?? '';
        if (preg_match('/^\+7([0-9]{3})([0-9]{3})([0-9]{2})([0-9]{2})$/D', $phone, $matches)) {
            $phone = "+7 ({$matches[1]}) {$matches[2]}-{$matches[3]}-{$matches[4]}";
        }
        $title = 'Новая заявка на обратный звонок';
        $intro = 'Клиент просит перезвонить.';
        $details = '<p>Номер заявки: <strong>'.$this->contact->id.'</strong></p>'
            .'<p>Телефон: '.e($phone).'</p>';
        $details .= '<p>Имя: '.e($this->contact->name ?? 'Не указано').'</p>';
        $adminUrl = config('admin.url');
        $url = rtrim(is_string($adminUrl) ? $adminUrl : '', '/').'/contacts?contact='.$this->contact->id;
        $details .= '<p><a href="'.e($url).'">Открыть обращение в админке</a></p>';

        return new Content(htmlString: '<!doctype html><html lang="ru"><body><h1>'.$title.'</h1><p>'.$intro.'</p>'.$details.'</body></html>');
    }
}
