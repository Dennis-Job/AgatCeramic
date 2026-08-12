<?php

namespace App\Enums;

enum AdminUserStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
}
