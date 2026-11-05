<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Support\RussianPhoneNumber;

final class OrderMailContent
{
    public function __construct(private readonly Order $order, private readonly ?SiteSetting $settings) {}

    public function customer(): string
    {
        $intro = '<p style="margin:0 0 16px;font-size:16px">Уважаемый(ая) '.e($this->order->customer_name).'!</p>'
            .'<p style="margin:0 0 16px">Благодарим вас за заказ в AgatCeramic. Мы получили ваш заказ № <strong>'.e($this->order->order_number).'</strong>.</p>'
            .'<p style="margin:0 0 24px">Менеджер подтвердит наличие товаров и согласует с вами доставку и оплату.</p>';
        $closing = '<p style="margin:24px 0 16px">Пожалуйста, проверьте указанные данные. Если заметите ошибку, свяжитесь с нами и назовите номер заказа.</p>'
            .'<p style="margin:0">С уважением,<br>команда AgatCeramic</p>';

        return $this->layout('Ваш заказ принят', $intro, 'Вы указали', $closing);
    }

    public function notification(): string
    {
        $intro = '<p style="margin:0 0 24px">На сайте AgatCeramic оформлен заказ № <strong>'.e($this->order->order_number).'</strong>. Свяжитесь с покупателем, чтобы подтвердить наличие товаров и согласовать доставку и оплату.</p>';

        return $this->layout('Новый заказ', $intro, 'Данные покупателя', '');
    }

    private function layout(string $title, string $intro, string $detailsTitle, string $closing): string
    {
        // Fluid tables and inline styles remain readable without web fonts, scripts or remote assets.
        return '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' — AgatCeramic</title></head>'
            .'<body style="margin:0;background:#f2f1ee;color:#141414;font-family:Arial,Helvetica,sans-serif;line-height:1.6">'
            .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f2f1ee"><tr><td align="center" style="padding:24px 12px">'
            .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;table-layout:fixed;overflow-wrap:anywhere;word-break:break-word">'
            .'<tr><td style="padding:24px;border-bottom:1px solid #e3e1db"><div style="font-size:27px;letter-spacing:-1px;white-space:nowrap"><span style="font-weight:600">AGAT</span><span style="font-weight:400">CERAMIC</span></div></td></tr>'
            .'<tr><td style="padding:32px 24px;background:#fafaf8"><h1 style="font-size:28px;font-weight:400;line-height:1.2;margin:0 0 24px">'.e($title).'</h1>'
            .$intro.$this->items().$this->customerDetails($detailsTitle).$closing.'</td></tr>'
            .'<tr><td style="padding:24px;border-top:1px solid #e3e1db;font-size:14px"><h2 style="font-size:16px;font-weight:400;margin:0 0 16px">Контакты AgatCeramic</h2>'
            .$this->contacts().'</td></tr></table></td></tr></table></body></html>';
    }

    private function items(): string
    {
        $rows = '';
        foreach ($this->order->items as $item) {
            $rows .= '<tr><td style="padding:16px 12px 16px 0;border-bottom:1px solid #e3e1db;vertical-align:top">'
                .e($item->product_name).'<div style="font-size:12px;color:#6e6b66">Артикул: '.e($item->product_sku ?? 'Не указан').'</div>'
                .'<div style="font-size:12px;color:#6e6b66">Количество: '.$item->quantity.'<br>Цена: '.$this->money($item->unit_price).'</div></td>'
                .'<td style="padding:16px 0;border-bottom:1px solid #e3e1db;vertical-align:top;text-align:right">'.$this->money($item->line_total).'</td></tr>';
        }

        return '<h2 style="font-size:16px;font-weight:400;letter-spacing:1px;margin:24px 0 12px">Состав заказа</h2>'
            .'<table width="100%" cellspacing="0" cellpadding="0" style="table-layout:fixed;font-size:14px"><thead><tr>'
            .'<th scope="col" style="width:65%;padding:8px 12px 8px 0;border-bottom:1px solid #e3e1db;text-align:left;font-weight:400;color:#6e6b66">Товар</th>'
            .'<th scope="col" style="width:35%;padding:8px 0;border-bottom:1px solid #e3e1db;text-align:right;font-weight:400;color:#6e6b66">Сумма</th></tr></thead><tbody>'
            .$rows.'</tbody><tfoot><tr><th scope="row" style="padding:20px 12px 20px 0;text-align:left;font-weight:400">Итого за товары</th>'
            .'<td style="padding:20px 0;text-align:right;font-weight:600">'.$this->money($this->order->total_amount).'</td></tr></tfoot></table>';
    }

    private function customerDetails(string $title): string
    {
        $fields = [
            'ФИО' => $this->order->customer_name,
            'Телефон' => $this->order->customer_phone !== null ? RussianPhoneNumber::format($this->order->customer_phone) : null,
            'Email' => $this->order->customer_email,
            'Адрес доставки' => $this->order->delivery_address,
            'Комментарий' => $this->order->customer_comment,
        ];
        $details = '<h2 style="font-size:16px;font-weight:400;letter-spacing:1px;margin:24px 0 16px">'.e($title).'</h2>';
        foreach ($fields as $label => $value) {
            $display = $value === null || $value === '' ? 'Не указан' : $value;
            $details .= '<p style="margin:0 0 12px;font-size:14px"><span style="color:#6e6b66">'.e($label).'</span><br>'.nl2br(e($display)).'</p>';
        }

        return $details;
    }

    private function contacts(): string
    {
        if ($this->settings === null) {
            return '';
        }
        $contacts = '';
        if ($this->settings->seller_name) {
            $contacts .= '<p style="margin:0 0 12px">'.e($this->settings->seller_name).'</p>';
        }
        foreach ($this->settings->phones ?? [] as $phone) {
            $canonical = RussianPhoneNumber::normalize($phone);
            if ($canonical !== null) {
                $contacts .= '<p style="margin:0 0 12px"><a style="color:#141414" href="tel:'.e($canonical).'">'.e(RussianPhoneNumber::format($canonical)).'</a></p>';
            }
        }
        $email = $this->settings->email;
        if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $contacts .= '<p style="margin:0 0 12px"><a style="color:#141414" href="mailto:'.e($email).'">'.e($email).'</a></p>';
        }
        if ($this->settings->address) {
            $contacts .= '<p style="margin:0">'.nl2br(e($this->settings->address)).'</p>';
        }

        return $contacts;
    }

    private function money(string $amount): string
    {
        // Eloquent decimal:2 provides the exact minor units; grouping stays string-only.
        [$integer, $fraction] = explode('.', $amount);
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', "\u{00A0}", $integer) ?? $integer;

        $groups = explode("\u{00A0}", $grouped);
        $parts = [];
        foreach ($groups as $index => $group) {
            $value = ($index === 0 ? '' : "\u{00A0}").$group;
            if ($index === count($groups) - 1) {
                $value .= ','.$fraction."\u{00A0}₽";
            }
            $parts[] = '<span style="white-space:nowrap">'.e($value).'</span>';
        }

        // Wrap only between complete thousand groups; keep minor units and currency together.
        return '<span style="word-break:normal;overflow-wrap:normal">'.implode('<wbr>', $parts).'</span>';
    }
}
