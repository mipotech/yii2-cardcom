<?php

namespace mipotech\cardcom\enums;

use yii2mod\enum\helpers\BaseEnum;

class OperationTypes extends BaseEnum
{
    const DEBIT= 1;
    const DEBIT_AND_TOKEN = 2;
    const TOKEN = 3;

    /**
     * @inheritdoc
     */
    public static function getList()
    {
        return [
            self::DEBIT => 'Debit',
            self::DEBIT_AND_TOKEN => 'Debit and token',
            self::TOKEN=> 'Token only',
        ];
    }
}
