<?php

namespace Ziphp\Recipes;

use Symfony\Component\String\ByteString;
use Yii;
use yii\web\Application as WebApplication;

final class Qs
{
    public static function strictQsHas(string $key): bool
    {
        return self::strictQsGet($key) !== null;
    }

    public static function strictQsGet(string $key): ?string
    {
        if (!(Yii::$app instanceof WebApplication)) {
            return null;
        }

        $value = Yii::$app->request->get($key);

        if (pf_is_string_filled($value)) {
            return trim($value);
        }

        return null;
    }

    public static function hashtagGii(int $length): string
    {
        return ByteString::fromRandom($length, 'QWERTYUADFHXVN2356789')->toString();
    }
}
