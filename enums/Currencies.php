<?php

namespace mipotech\cardcom\enums;

use yii2mod\enum\helpers\BaseEnum;

class Currencies extends BaseEnum
{
    const ILS = 1;
    const USD = 2;

    /**
     * @inheritdoc
     */
    public static function getList()
    {
        return [
            self::ILS => 'ILS',
            self::USD => 'USD',
        ];
    }
}
