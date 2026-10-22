<?php

namespace App\Services;

use App\Jobs\SendCallbackMail;
use App\Mail\CallbackRequestMail;
use App\Mail\ContactConfirmationMail;
use App\Mail\ContactRequestMail;
use App\Models\CallbackMailDelivery;
use App\Models\ContactRequest;
use App\Models\SiteSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class CallbackMailService
{
    public function schedule(ContactRequest $contact): void
    {
        $recipients = in_array($contact->type, ['partner', 'email'], true) ? ['admin', 'customer'] : ['admin'];
        foreach ($recipients as $recipient) {
            $delivery = CallbackMailDelivery::query()->create([
                'contact_request_id' => $contact->id, 'recipient' => $recipient,
            ]);
            DB::afterCommit(fn () => $this->dispatch($delivery));
        }
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
        // Log transport neither delivers email nor keeps its personal data out of application logs.
        $mailer = config('mail.default');
        if (is_string($mailer) && $this->usesLogTransport($mailer)) {
            return;
        }
        $success = DB::transaction(function () use ($deliveryId): bool {
            // Serialize duplicate workers. SMTP timeout is below the queue timeout/retry_after.
            $delivery = CallbackMailDelivery::query()->lockForUpdate()->find($deliveryId);
            if ($delivery === null || $delivery->status !== 'pending' || $delivery->attempts >= 9) {
                return true;
            }
            $contact = ContactRequest::query()->find($delivery->contact_request_id);
            if ($contact === null || ! $this->canNotify($contact, $delivery->recipient)) {
                $delivery->update(['status' => 'skipped']);

                return true;
            }
            $settings = SiteSetting::query()->find(1);
            $address = $delivery->recipient === 'admin' ? $settings?->notification_email : $contact->email;
            if (! is_string($address) || ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                // Keep the delivery pending until a valid notification address is configured in site settings.
                return true;
            }
            $delivery->update(['attempts' => $delivery->attempts + 1, 'last_attempted_at' => now()]);
            try {
                Mail::to($address)->send($this->messageFor($contact, $settings, $delivery->recipient));
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

    private function canNotify(ContactRequest $contact, string $recipient): bool
    {
        if ($contact->type === 'callback') {
            return $recipient === 'admin' && $contact->phone !== null;
        }

        return in_array($contact->type, ['partner', 'email'], true)
            && in_array($recipient, ['admin', 'customer'], true)
            && is_string($contact->email) && filter_var($contact->email, FILTER_VALIDATE_EMAIL);
    }

    private function messageFor(ContactRequest $contact, ?SiteSetting $settings, string $recipient): Mailable
    {
        if ($recipient === 'customer') {
            return new ContactConfirmationMail($contact, $settings);
        }

        return $contact->type === 'callback' ? new CallbackRequestMail($contact) : new ContactRequestMail($contact);
    }

    /** @param list<string> $visited */
    private function usesLogTransport(string $name, array $visited = []): bool
    {
        if (in_array($name, $visited, true)) {
            return true;
        }
        $transport = config("mail.mailers.{$name}.transport");
        if ($transport === 'log') {
            return true;
        }
        if (! in_array($transport, ['failover', 'roundrobin'], true)) {
            return false;
        }
        $children = config("mail.mailers.{$name}.mailers", []);
        if (is_array($children)) {
            foreach ($children as $child) {
                if (is_string($child) && $this->usesLogTransport($child, [...$visited, $name])) {
                    return true;
                }
            }
        }

        return false;
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
