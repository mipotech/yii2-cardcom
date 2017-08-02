<?php

namespace mipotech\cardcom\enums;

use yii2mod\enum\helpers\BaseEnum;

class InvoiceOperationTypes extends BaseEnum
{
    const NO_CREATE = 0;
    const CREATE_AND_DISPALY = 1;
    const DISPLAY_WITHOUT_CREATE = 2;
}