<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order, private readonly ?SiteSetting $settings = null) {}

    public function envelope(): Envelope
    {
        $email = $this->settings?->email;

        return new Envelope(
            subject: "Ваш заказ {$this->order->order_number} принят — AgatCeramic",
            replyTo: is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)
                ? [new Address($email, 'AgatCeramic')] : [],
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: (new OrderMailContent($this->order, $this->settings))->customer());
    }
}
