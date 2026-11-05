<?php

namespace App\Services;

use App\Jobs\SendOrderMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderNotificationMail;
use App\Models\Order;
use App\Models\OrderMailDelivery;
use App\Models\SiteSetting;
use App\Support\MailTransportSafety;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class OrderConfirmationService
{
    public function __construct(private readonly MailTransportSafety $transportSafety) {}

    public function queue(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $recipients = $order->customer_email === null ? ['admin'] : ['admin', 'customer'];
            foreach ($recipients as $recipient) {
                $delivery = OrderMailDelivery::query()->firstOrCreate(['order_id' => $order->id, 'recipient' => $recipient]);
                if ($delivery->wasRecentlyCreated) {
                    DB::afterCommit(fn () => $this->dispatch($delivery));
                }
            }
        });
    }

    public function dispatch(OrderMailDelivery $delivery): void
    {
        try {
            $delivery->update(['last_dispatched_at' => now()]);
            SendOrderMail::dispatch($delivery->id);
        } catch (Throwable) {
            // Keep the durable intent; queue errors may contain credentials or personal data.
            Log::warning('Order email dispatch failed.', ['delivery_id' => $delivery->id]);
        }
    }

    public function send(int $deliveryId): void
    {
        $mailer = config('mail.default');
        if (is_string($mailer) && $this->transportSafety->usesLogTransport($mailer)) {
            return;
        }
        $success = DB::transaction(function () use ($deliveryId): bool {
            $pending = OrderMailDelivery::query()->find($deliveryId);
            if ($pending === null) {
                return true;
            }
            // Match retention's order-first locking to avoid deadlocks during cascade deletion.
            $order = Order::query()->with('items')->lockForUpdate()->find($pending->order_id);
            $delivery = OrderMailDelivery::query()->lockForUpdate()->find($deliveryId);
            if ($delivery === null || $delivery->status !== 'pending' || $delivery->attempts >= 9) {
                return true;
            }
            if ($order === null || $order->anonymized_at !== null) {
                $delivery->update(['status' => 'skipped']);

                return true;
            }
            $settings = SiteSetting::query()->find(1);
            $address = $delivery->recipient === 'admin' ? $settings?->notification_email : $order->customer_email;
            if (! is_string($address) || ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                if ($delivery->recipient === 'customer') {
                    $delivery->update(['status' => 'skipped']);
                }

                return true;
            }
            $delivery->update(['attempts' => $delivery->attempts + 1, 'last_attempted_at' => now()]);
            try {
                $message = $delivery->recipient === 'admin'
                    ? new OrderNotificationMail($order, $settings)
                    : new OrderConfirmationMail($order, $settings);
                Mail::to($address)->send($message);
            } catch (Throwable) {
                return false;
            }
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);

            return true;
        });
        if (! $success) {
            throw new RuntimeException('Order email delivery failed.');
        }
    }

    public function sendLegacyConfirmation(int $orderId): void
    {
        // Previously queued jobs only requested customer confirmation; do not backfill admin mail.
        $delivery = DB::transaction(function () use ($orderId): ?OrderMailDelivery {
            $order = Order::query()->lockForUpdate()->find($orderId);
            if ($order === null || $order->customer_email === null || $order->anonymized_at !== null) {
                return null;
            }

            return OrderMailDelivery::query()->firstOrCreate(['order_id' => $orderId, 'recipient' => 'customer']);
        });
        if ($delivery !== null) {
            $this->send($delivery->id);
        }
    }

    public function resetExhausted(OrderMailDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery): void {
            $current = OrderMailDelivery::query()->lockForUpdate()->find($delivery->id);
            if ($current !== null && $current->status === 'pending' && $current->attempts >= 9) {
                $current->update(['attempts' => 0, 'last_dispatched_at' => null]);
            }
        });
    }
}
