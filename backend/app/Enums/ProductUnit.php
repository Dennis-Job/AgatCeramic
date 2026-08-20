<?php

namespace App\Enums;

enum ProductUnit: string
{
    case Piece = 'piece';
    case SquareMeter = 'square_meter';
    case LinearMeter = 'linear_meter';
    case Package = 'package';
    case Kilogram = 'kilogram';
    case Liter = 'liter';
    case Set = 'set';
}
