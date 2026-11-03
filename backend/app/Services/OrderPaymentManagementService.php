<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @phpstan-type PaymentAttributes array{payment_status: PaymentStatus, payment_amount: ?string, payment_method: ?string, payment_reference: ?string, paid_at: mixed} */
class OrderPaymentManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly MoneyCalculator $amountCalculator,
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
                'from_payment_status' => $previousStatus,
                'to_payment_status' => $paymentStatus->value,
                'payment_amount' => $payment['payment_amount'],
            ]);

            return $order;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return PaymentAttributes
     */
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

        $amount = $this->amountCalculator->normalize($this->requiredString($attributes, 'payment_amount'));
        $comparison = $this->amountCalculator->compare($amount, $order->total_amount);
        $isPositive = $this->amountCalculator->compare($amount, '0.00') > 0;

        $isValidAmount = match ($paymentStatus) {
            PaymentStatus::Paid => $comparison === 0,
            PaymentStatus::PartiallyPaid => $isPositive && $comparison < 0,
            PaymentStatus::Refunded => $isPositive && $comparison <= 0,
        };

        if (! $isValidAmount) {
            throw ValidationException::withMessages([
                'payment_amount' => [match ($paymentStatus) {
                    PaymentStatus::Paid => 'Сумма полной оплаты должна совпадать с итоговой суммой заказа.',
                    PaymentStatus::PartiallyPaid => 'Сумма частичной оплаты должна быть больше нуля и меньше итоговой суммы заказа.',
                    PaymentStatus::Refunded => 'Сумма возвращённого платежа должна быть больше нуля и не превышать итоговую сумму заказа.',
                }],
            ]);
        }

        return [
            'payment_status' => $paymentStatus,
            'payment_amount' => $amount,
            'payment_method' => $this->requiredString($attributes, 'payment_method'),
            'payment_reference' => $this->nullableString($attributes['payment_reference'] ?? null),
            'paid_at' => $attributes['paid_at'],
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function requiredString(array $attributes, string $field): string
    {
        $value = $attributes[$field] ?? null;
        if (! is_string($value) || $value === '') {
            throw new \LogicException("Validated payment field {$field} is missing.");
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new \LogicException('Validated optional payment field is invalid.');
        }

        return $value;
    }
}
