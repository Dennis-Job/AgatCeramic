<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case NotPaid = 'not_paid';
    case Pending = 'pending';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case PartiallyPaid = 'partially_paid';
}
