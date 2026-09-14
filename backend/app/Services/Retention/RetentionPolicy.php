<?php

namespace App\Services\Retention;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final readonly class RetentionPolicy
{
    /** @param array<string, mixed> $configuration */
    private function __construct(private array $configuration) {}

    public static function fromConfig(): self
    {
        $configuration = config('retention');

        if (! is_array($configuration)) {
            throw new InvalidArgumentException('Retention configuration is missing.');
        }

        $typedConfiguration = [];
        foreach ($configuration as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Retention configuration keys must be strings.');
            }

            $typedConfiguration[$key] = $value;
        }

        $policy = new self($typedConfiguration);
        $policy->validate();

        return $policy;
    }

    public function version(): string
    {
        return $this->string('policy_version');
    }

    public function serviceIdentity(): string
    {
        return $this->string('service_identity');
    }

    public function batchSize(): int
    {
        return $this->positiveInt('batch_size', 1000);
    }

    public function orderPiiCutoff(): CarbonImmutable
    {
        return CarbonImmutable::now()
            ->subYears($this->positiveInt('orders.pii_years'))
            ->subDays($this->positiveInt('orders.processing_grace_days'));
    }

    public function orderReviewCutoff(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays($this->positiveInt('orders.active_review_days'));
    }

    public function commercialDisposition(): string
    {
        return $this->string('orders.commercial_disposition');
    }

    public function commercialRetentionUntil(CarbonImmutable $anchor): CarbonImmutable
    {
        return $anchor->endOfYear()->addYears($this->positiveInt('orders.commercial_years'));
    }

    public function contactCompletedCutoff(): CarbonImmutable
    {
        return CarbonImmutable::now()
            ->subDays($this->positiveInt('contacts.completed_days'))
            ->subDays($this->positiveInt('contacts.processing_grace_days'));
    }

    public function contactRejectedCutoff(): CarbonImmutable
    {
        return CarbonImmutable::now()
            ->subDays($this->positiveInt('contacts.rejected_days'))
            ->subDays($this->positiveInt('contacts.processing_grace_days'));
    }

    public function contactReviewCutoff(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays($this->positiveInt('contacts.active_review_days'));
    }

    public function contactMaximumCutoff(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays($this->positiveInt('contacts.active_max_days'));
    }

    public function failedJobsCutoff(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays($this->positiveInt('technical.failed_jobs_days'));
    }

    public function sessionLastActivityCutoff(int $lifetimeMinutes): int
    {
        if ($lifetimeMinutes <= 0) {
            throw new InvalidArgumentException('Session lifetime must be positive.');
        }

        $graceMinutes = $this->positiveInt('technical.session_expiry_grace_hours') * 60;

        return CarbonImmutable::now()->subMinutes($lifetimeMinutes + $graceMinutes)->getTimestamp();
    }

    public function passwordResetCutoff(int $expiryMinutes): CarbonImmutable
    {
        if ($expiryMinutes <= 0) {
            throw new InvalidArgumentException('Password reset expiry must be positive.');
        }

        return CarbonImmutable::now()
            ->subMinutes($expiryMinutes)
            ->subHours($this->positiveInt('technical.password_reset_expiry_grace_hours'));
    }

    public function assertApplyAllowed(string $scope): void
    {
        if ($this->string('policy_status') !== 'accepted' || ! $this->boolean('apply_enabled')) {
            throw new RetentionApplyBlocked('policy_not_accepted');
        }

        if ($this->tombstoneKey() === '') {
            throw new RetentionApplyBlocked('tombstone_key_missing');
        }

        if ($scope === 'orders' && $this->commercialDisposition() === 'pending') {
            throw new RetentionApplyBlocked('order_disposition_pending');
        }
    }

    public function tombstoneKey(): string
    {
        $value = Arr::get($this->configuration, 'tombstone_key');

        return is_string($value) ? $value : '';
    }

    public function tombstoneKeyId(): string
    {
        return $this->string('tombstone_key_id');
    }

    public function tombstoneKeyFor(string $keyId): ?string
    {
        if (hash_equals($this->tombstoneKeyId(), $keyId)) {
            return $this->tombstoneKey();
        }

        $encoded = Arr::get($this->configuration, 'previous_tombstone_keys');
        if (! is_string($encoded)) {
            return null;
        }

        $decoded = json_decode($encoded, true);

        return is_array($decoded) && isset($decoded[$keyId]) && is_string($decoded[$keyId])
            ? $decoded[$keyId]
            : null;
    }

    private function validate(): void
    {
        $this->string('policy_version');
        $this->string('service_identity');
        $this->string('tombstone_key_id');
        $this->positiveInt('batch_size', 1000);
        $this->positiveInt('orders.pii_years');
        $this->positiveInt('orders.processing_grace_days');
        $this->positiveInt('orders.active_review_days');
        $this->positiveInt('orders.commercial_years');
        $this->positiveInt('contacts.completed_days');
        $this->positiveInt('contacts.rejected_days');
        $this->positiveInt('contacts.processing_grace_days');
        $this->positiveInt('contacts.active_review_days');
        $this->positiveInt('contacts.active_max_days');
        $this->positiveInt('technical.failed_jobs_days');
        $this->positiveInt('technical.session_expiry_grace_hours');
        $this->positiveInt('technical.password_reset_expiry_grace_hours');

        if (! in_array($this->string('policy_status'), ['proposed', 'accepted'], true)) {
            throw new InvalidArgumentException('Retention policy status is invalid.');
        }

        if (! in_array($this->commercialDisposition(), ['pending', 'retain_commercial', 'delete_all'], true)) {
            throw new InvalidArgumentException('Order commercial disposition is invalid.');
        }

        if ($this->positiveInt('contacts.active_max_days') < $this->positiveInt('contacts.active_review_days')) {
            throw new InvalidArgumentException('Contact maximum age cannot be shorter than its review age.');
        }
    }

    private function string(string $key): string
    {
        $value = Arr::get($this->configuration, $key);

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Retention configuration {$key} must be a non-empty string.");
        }

        return $value;
    }

    private function positiveInt(string $key, ?int $maximum = null): int
    {
        $value = filter_var(Arr::get($this->configuration, $key), FILTER_VALIDATE_INT);

        if (! is_int($value) || $value <= 0 || ($maximum !== null && $value > $maximum)) {
            throw new InvalidArgumentException("Retention configuration {$key} must be a positive integer.");
        }

        return $value;
    }

    private function boolean(string $key): bool
    {
        $value = filter_var(Arr::get($this->configuration, $key), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if (! is_bool($value)) {
            throw new InvalidArgumentException("Retention configuration {$key} must be boolean.");
        }

        return $value;
    }
}
