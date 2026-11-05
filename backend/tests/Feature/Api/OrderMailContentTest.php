<?php

namespace Tests\Feature\Api;

use App\Mail\OrderConfirmationMail;
use App\Mail\OrderNotificationMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SiteSetting;
use Tests\TestCase;

class OrderMailContentTest extends TestCase
{
    public function test_customer_receives_all_entered_data_and_public_shop_contacts(): void
    {
        $mail = new OrderConfirmationMail($this->order(), $this->settings());
        $html = $mail->render();

        foreach (['Уважаемый(ая) Иван &lt;Тест&gt;!', 'Благодарим вас за заказ в AgatCeramic',
            'AC-20261105-TEST123456', 'Иван &lt;Тест&gt;', '+7 (999) 123-45-67', 'buyer@example.test',
            'Москва, улица Тестовая, 42', 'Позвонить &lt;до доставки&gt;', 'Пожалуйста, проверьте указанные данные',
            'Тестовый магазин', '+7 (495) 120-45-67', 'tel:+74951204567', 'mailto:shop@example.test',
            'Москва, улица Магазина, 1', 'AGAT', 'CERAMIC', '#f2f1ee', '#fafaf8'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        $this->assertStringNotContainsString('private-staff@example.test', $html);
        $this->assertStringNotContainsString('private-partner@example.test', $html);
        $this->assertStringNotContainsString('INTERNAL_PAYMENT_REFERENCE', $html);
        $this->assertSame('shop@example.test', $mail->envelope()->replyTo[0]->address);
        $this->assertStringContainsString('AC-20261105-TEST123456', $mail->envelope()->subject);
    }

    public function test_both_messages_use_escaped_order_snapshots_and_exact_russian_money(): void
    {
        foreach ([new OrderConfirmationMail($this->order()), new OrderNotificationMail($this->order())] as $mail) {
            $html = $mail->render();
            foreach (['Белый мрамор &lt;тест&gt;', 'SKU-&quot;001&quot;', 'Количество', 'Итого за товары'] as $expected) {
                $this->assertStringContainsString($expected, $html);
            }
            foreach (["1\u{00A0}250,05\u{00A0}₽", "2\u{00A0}500,10\u{00A0}₽"] as $expected) {
                $this->assertStringContainsString($expected, strip_tags($html));
            }
            $this->assertStringNotContainsString('<тест>', $html);
            $this->assertStringNotContainsString('<до доставки>', $html);
            $this->assertStringNotContainsString('<script>', $html);
            $this->assertStringNotContainsString('https://', $html);
        }
    }

    public function test_staff_message_has_its_own_subject_and_all_order_details(): void
    {
        $mail = new OrderNotificationMail($this->order(), $this->settings());
        $this->assertSame('Новый заказ AC-20261105-TEST123456 — AgatCeramic', $mail->envelope()->subject);
        foreach (['Новый заказ', 'Данные покупателя', 'buyer@example.test', '+7 (999) 123-45-67', 'Москва, улица Тестовая, 42', 'Позвонить &lt;до доставки&gt;'] as $expected) {
            $this->assertStringContainsString($expected, $mail->render());
        }
    }

    public function test_missing_optional_fields_and_unconfigured_contacts_are_honest(): void
    {
        $order = $this->order();
        $order->customer_email = null;
        $order->customer_comment = null;
        $mail = new OrderConfirmationMail($order, new SiteSetting([
            'phones' => ['invalid'], 'email' => 'invalid', 'address' => null,
        ]));
        $html = $mail->render();
        $this->assertStringContainsString('Не указан', $html);
        $this->assertStringNotContainsString('mailto:', $html);
        $this->assertStringNotContainsString('tel:', $html);
        $this->assertSame([], $mail->envelope()->replyTo);
    }

    public function test_comment_zero_is_not_replaced_with_a_missing_value(): void
    {
        $order = $this->order();
        $order->customer_comment = '0';
        $this->assertStringContainsString('Комментарий</span><br>0</p>', (new OrderConfirmationMail($order))->render());
    }

    public function test_large_money_preserves_its_exact_value_with_safe_thousand_group_breaks(): void
    {
        $order = $this->order();
        $order->total_amount = '2962969318.77';
        $html = (new OrderConfirmationMail($order))->render();
        $this->assertStringContainsString("2\u{00A0}962\u{00A0}969\u{00A0}318,77\u{00A0}₽", strip_tags($html));
        $this->assertStringContainsString('<wbr>', $html);
    }

    private function order(): Order
    {
        $order = new Order([
            'order_number' => 'AC-20261105-TEST123456', 'customer_name' => 'Иван <Тест>',
            'customer_phone' => '+79991234567', 'customer_email' => 'buyer@example.test',
            'delivery_address' => 'Москва, улица Тестовая, 42', 'customer_comment' => 'Позвонить <до доставки>',
            'total_amount' => '2500.10', 'payment_reference' => 'INTERNAL_PAYMENT_REFERENCE',
        ]);
        $order->setRelation('items', collect([new OrderItem([
            'product_name' => 'Белый мрамор <тест>', 'product_sku' => 'SKU-"001"',
            'unit_price' => '1250.05', 'quantity' => 2, 'line_total' => '2500.10',
        ])]));

        return $order;
    }

    private function settings(): SiteSetting
    {
        return new SiteSetting([
            'seller_name' => 'Тестовый магазин', 'phones' => ['+74951204567'],
            'email' => 'shop@example.test', 'address' => 'Москва, улица Магазина, 1',
            'notification_email' => 'private-staff@example.test', 'partner_email' => 'private-partner@example.test',
        ]);
    }
}
