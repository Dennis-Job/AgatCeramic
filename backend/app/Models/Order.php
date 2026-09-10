<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_number', 'customer_name', 'customer_phone', 'customer_email', 'delivery_address', 'customer_comment', 'status', 'payment_status', 'payment_amount', 'payment_method', 'payment_reference', 'total_amount', 'paid_at', 'completed_at'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /** @return HasMany<OrderComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(OrderComment::class);
    }

    /** @return BelongsTo<OrderStatus, $this> */
    public function statusDefinition(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'status', 'code');
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'payment_status' => PaymentStatus::class,
            'payment_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
