<?php

namespace App\Enums;

enum ContactRequestStatus: string
{
    case New = 'new';
    case Processing = 'processing';
    case AwaitingClient = 'awaiting_client';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Rejected => true,
            default => false,
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Processing, self::Rejected],
            self::Processing => [self::AwaitingClient, self::Completed, self::Rejected],
            self::AwaitingClient => [self::Processing, self::Completed, self::Rejected],
            self::Completed, self::Rejected => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новое',
            self::Processing => 'В обработке',
            self::AwaitingClient => 'Ожидает клиента',
            self::Completed => 'Завершено',
            self::Rejected => 'Отклонено',
        };
    }
}
