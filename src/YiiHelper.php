<?php

namespace Ziphp\Recipes;

final class YiiHelper
{
    public static function count2int(mixed $count): int
    {
        if ($count === null) {
            return 0;
        }

        if ($count === false) {
            return 0;
        }

        if (is_string($count) && is_numeric($count)) {
            return (int)$count;
        }

        return $count;
    }
}
