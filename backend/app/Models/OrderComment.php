<?php

namespace App\Models;

use Database\Factories\OrderCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'author_id', 'author_snapshot', 'body', 'created_at'])]
class OrderComment extends Model
{
    /** @use HasFactory<OrderCommentFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'author_snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
