<?php

namespace App\Services;

use App\Jobs\SendCallbackMail;
use App\Mail\CallbackRequestMail;
use App\Models\CallbackMailDelivery;
use App\Models\ContactRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class CallbackMailService
{
    public function schedule(ContactRequest $contact): void
    {
        $delivery = CallbackMailDelivery::query()->create([
            'contact_request_id' => $contact->id, 'recipient' => 'admin',
        ]);
        DB::afterCommit(fn () => $this->dispatch($delivery));
    }

    public function dispatch(CallbackMailDelivery $delivery): void
    {
        try {
            $delivery->forceFill(['last_dispatched_at' => now()])->save();
            SendCallbackMail::dispatch($delivery->id);
        } catch (Throwable) {
            // The durable row remains retryable; transport exceptions can contain PII or credentials.
            Log::warning('Callback email dispatch failed.', ['delivery_id' => $delivery->id]);
        }
    }

    public function send(int $deliveryId): void
    {
        $success = DB::transaction(function () use ($deliveryId): bool {
            // Serialize duplicate workers. SMTP timeout is below the queue timeout/retry_after.
            $delivery = CallbackMailDelivery::query()->lockForUpdate()->find($deliveryId);
            if ($delivery === null || $delivery->status !== 'pending' || $delivery->attempts >= 9) {
                return true;
            }
            $contact = ContactRequest::query()->find($delivery->contact_request_id);
            if ($delivery->recipient !== 'admin' || $contact === null || $contact->phone === null || $contact->type !== 'callback') {
                $delivery->update(['status' => 'skipped']);

                return true;
            }
            $address = config('mail.callback_admin_address');
            if (! is_string($address) || ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                // Keep an unconfigured admin destination pending for recovery after configuration.
                return true;
            }
            $delivery->update(['attempts' => $delivery->attempts + 1, 'last_attempted_at' => now()]);
            try {
                Mail::to($address)->send(new CallbackRequestMail($contact));
            } catch (Throwable) {
                return false;
            }
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);

            return true;
        });
        if (! $success) {
            throw new RuntimeException('Callback email delivery failed.');
        }
    }

    public function resetExhausted(CallbackMailDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery): void {
            $current = CallbackMailDelivery::query()->lockForUpdate()->find($delivery->id);
            if ($current !== null && $current->status === 'pending' && $current->attempts >= 9) {
                $current->update(['attempts' => 0, 'last_dispatched_at' => null]);
            }
        });
    }
}
