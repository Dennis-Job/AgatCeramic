<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderCommentService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function add(User $author, Order $order, string $body): OrderComment
    {
        return DB::transaction(function () use ($author, $order, $body): OrderComment {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $comment = $order->comments()->create([
                'author_id' => $author->id,
                'author_snapshot' => ['name' => $author->name],
                'body' => $body,
            ]);
            $this->auditLogService->record($author, 'order.comment-added', $order);

            return $comment;
        });
    }
}
