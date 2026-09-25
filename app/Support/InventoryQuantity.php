<?php

namespace App\Support;

final class InventoryQuantity
{
    public static function format(int|float|string $quantity): string
    {
        return rtrim(rtrim(number_format((float) $quantity, 3, '.', ''), '0'), '.');
    }
}
