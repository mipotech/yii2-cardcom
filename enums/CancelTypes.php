<?php

namespace mipotech\cardcom\enums;

use yii2mod\enum\helpers\BaseEnum;

class CancelTypes extends BaseEnum
{
    const NOT_SHOWN = 0;
    const EMULATE_BACK_BTN = 1;
    const RETURN_URL = 2;

    /**
     * @inheritdoc
     */
    public static function getList()
    {
        return [
            self::NOT_SHOWN => 'Not shown',
            self::EMULATE_BACK_BTN => 'Emulate browser back button',
            self::RETURN_URL=> 'Return to the return URL',
        ];
    }
}
