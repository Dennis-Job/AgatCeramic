<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Подтверждение заказа {$this->order->order_number}",
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->emailHtml());
    }

    private function emailHtml(): string
    {
        $items = $this->order->items->map(fn ($item): string => sprintf(
            '<tr><td>%s</td><td>%s</td><td>%d</td><td>%s ₽</td><td>%s ₽</td></tr>',
            $this->escape($item->product_name),
            $this->escape($item->product_sku ?? '—'),
            $item->quantity,
            $this->escape($item->unit_price),
            $this->escape($item->line_total),
        ))->implode('');

        return sprintf(
            '<!doctype html><html lang="ru"><body><h1>Заказ принят</h1><p>Номер вашего заказа: <strong>%s</strong>.</p><p>Мы свяжемся с вами для подтверждения деталей заказа.</p><table><thead><tr><th>Товар</th><th>Артикул</th><th>Количество</th><th>Цена</th><th>Сумма</th></tr></thead><tbody>%s</tbody><tfoot><tr><th colspan="4">Итого</th><td><strong>%s ₽</strong></td></tr></tfoot></table></body></html>',
            $this->escape($this->order->order_number),
            $items,
            $this->escape($this->order->total_amount),
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
