<?php require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");

$APPLICATION->IncludeComponent("test:cars.list", "", [
        'CARS_IBLOCK_ID' => 116,
        'POSTS_IBLOCK_ID' => 118,
        'RESERVS_HL_ID' => 43,
        'COMFORT_CATEGORIES_HL_ID' => 41,
    ],
    false
);

