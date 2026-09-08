<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderPaymentManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function register(User $actor, Order $order, PaymentStatus $paymentStatus, array $attributes): Order
    {
        return DB::transaction(function () use ($actor, $order, $paymentStatus, $attributes): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment = $this->paymentAttributes($order, $paymentStatus, $attributes);
            $previousStatus = $order->payment_status;
            $order->fill($payment);

            if (! $order->isDirty()) {
                return $order;
            }

            $order->save();
            $this->auditLogService->record($actor, 'order.payment-registered', $order, [
                'from_payment_status' => $previousStatus->value,
                'to_payment_status' => $paymentStatus->value,
                'payment_amount' => $payment['payment_amount'],
            ]);

            return $order;
        });
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function paymentAttributes(Order $order, PaymentStatus $paymentStatus, array $attributes): array
    {
        if (in_array($paymentStatus, [PaymentStatus::NotPaid, PaymentStatus::Pending], true)) {
            return [
                'payment_status' => $paymentStatus,
                'payment_amount' => null,
                'payment_method' => null,
                'payment_reference' => null,
                'paid_at' => null,
            ];
        }

        $amount = $this->normaliseAmount((string) $attributes['payment_amount']);
        $total = $this->normaliseAmount((string) $order->total_amount);
        $comparison = $this->compareAmounts($amount, $total);
        $isPositive = $this->compareAmounts($amount, '0.00') > 0;

        $isValidAmount = match ($paymentStatus) {
            PaymentStatus::Paid => $comparison === 0,
            PaymentStatus::PartiallyPaid => $isPositive && $comparison < 0,
            PaymentStatus::Refunded => $isPositive && $comparison <= 0,
            default => false,
        };

        if (! $isValidAmount) {
            throw ValidationException::withMessages([
                'payment_amount' => [match ($paymentStatus) {
                    PaymentStatus::Paid => 'Сумма полной оплаты должна совпадать с итоговой суммой заказа.',
                    PaymentStatus::PartiallyPaid => 'Сумма частичной оплаты должна быть больше нуля и меньше итоговой суммы заказа.',
                    PaymentStatus::Refunded => 'Сумма возвращённого платежа должна быть больше нуля и не превышать итоговую сумму заказа.',
                    default => 'Сумма платежа должна быть больше нуля.',
                }],
            ]);
        }

        return [
            'payment_status' => $paymentStatus,
            'payment_amount' => $amount,
            'payment_method' => $attributes['payment_method'],
            'payment_reference' => $attributes['payment_reference'] ?? null,
            'paid_at' => $attributes['paid_at'],
        ];
    }

    private function normaliseAmount(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ltrim($whole, '0') === '' ? '0.'.str_pad($fraction, 2, '0') : ltrim($whole, '0').'.'.str_pad($fraction, 2, '0');
    }

    private function compareAmounts(string $left, string $right): int
    {
        [$leftWhole, $leftFraction] = explode('.', $left, 2);
        [$rightWhole, $rightFraction] = explode('.', $right, 2);

        return strlen($leftWhole) <=> strlen($rightWhole)
            ?: strcmp($leftWhole, $rightWhole)
            ?: strcmp($leftFraction, $rightFraction);
    }
}
