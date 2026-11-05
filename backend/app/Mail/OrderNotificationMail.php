<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderNotificationMail extends Mailable
{
    public function __construct(public readonly Order $order, private readonly ?SiteSetting $settings = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Новый заказ {$this->order->order_number} — AgatCeramic");
    }

    public function content(): Content
    {
        return new Content(htmlString: (new OrderMailContent($this->order, $this->settings))->notification());
    }
}
