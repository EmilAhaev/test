<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
    die();

use Bitrix\Highloadblock\HighloadBlockTable as HLBT;
use Bitrix\Main\Context;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Query\Filter\ConditionTree;

class CarsListComponent extends CBitrixComponent
{
    private array $errors;
    private array $filter;

    public function addError($errorCode, $errorMess)
    {
        if (!isset($this->errors))  {
            $this->errors = [];
        }
        $this->errors[] = ['CODE' => $errorCode, 'MESS' => $errorMess];
    }

    public function hasErrors(): bool
    {
        if (isset($this->errors) && count($this->errors)) {
            return true;
        }
        return false;
    }

    public function getErrors()
    {
        $this->arResult['ERRORS'] = $this->errors;
    }

    public function GetData()
    {
        Loader::includeModule('iblock');

        $entityComfortCategories = HLBT::compileEntity(HLBT::getById($this->arParams['COMFORT_CATEGORIES_HL_ID'])->fetch());
        $entityCarReservations = HLBT::compileEntity(HLBT::getById($this->arParams['RESERVS_HL_ID'])->fetch());

        //получим информацию о должности

        $arPosts = \Bitrix\Iblock\Iblock::wakeUp($this->arParams['POSTS_IBLOCK_ID'])->getEntityDataClass()::getList([
            'select' => [
                'ID',
                'NAME',
                'CAT_COMFORT_PERMISSION_VAL' => 'CAT_COMFORT_PERMISSION.VALUE',
                'CAR_CAT_COMFORT_NAME' => 'CAR_CAT_COMFORT.UF_NAME'

            ],
            'filter' => ['ID' => $this->filter['post_id']],
            'runtime' => [
                'CAR_CAT_COMFORT' => [
                    'data_type' => $entityComfortCategories->getDataClass(),
                    'reference' => ['=this.CAT_COMFORT_PERMISSION_VAL' => 'ref.UF_XML_ID'], 'join_type' => 'LEFT',
                ],
            ]
        ])->fetchAll();

        $postComfortPermissions = [];
        foreach ($arPosts as $postRow) {
            $postComfortPermissions[$postRow['CAT_COMFORT_PERMISSION_VAL']] = $postRow['CAR_CAT_COMFORT_NAME'];
        }

        $formatDateFrom = new \Bitrix\Main\Type\DateTime($this->filter['date-from'],"d.m.Y H:i:s");
        $formatDateTo = new \Bitrix\Main\Type\DateTime($this->filter['date-to'],"d.m.Y H:i:s");

        $subQueryHasReservIds = $entityCarReservations->getDataClass()::query()
            ->setSelect(['UF_CAR'])
            ->where(
                ConditionTree::createFromArray([
                    'logic' => 'or',
                    [
                        ['UF_DATE_FROM', '<=', $formatDateFrom],
                        ['UF_DATE_TO', '>=', $formatDateFrom]
                    ],
                    [
                        ['UF_DATE_FROM', '>=', $formatDateFrom],
                        ['UF_DATE_TO', '<=', $formatDateTo]
                    ],
                    [
                        ['UF_DATE_FROM', '<=', $formatDateTo],
                        ['UF_DATE_TO', '>=', $formatDateTo]
                    ],
                    [
                        ['UF_DATE_FROM', '<=', $formatDateFrom],
                        ['UF_DATE_TO', '>=', $formatDateTo]
                    ]
                ])
            )
            ->setGroup(['UF_CAR']);

        $cars = \Bitrix\Iblock\Iblock::wakeUp($this->arParams['CARS_IBLOCK_ID'])->getEntityDataClass()::getList([
            'select' => [
                'ID',
                'NAME',
                'DRIVER_NAME' => 'DRIVER.ELEMENT.NAME',
                'CAT_COMFORT_VAL' => 'COMFORT_CATEGORY.VALUE',
            ],
            'filter' => \Bitrix\Main\ORM\Query\Query::filter()
                ->whereNotIn('ID', $subQueryHasReservIds)
                ->whereIn('COMFORT_CATEGORY.VALUE', array_keys($postComfortPermissions))
        ])->fetchAll();

        foreach ($cars as $car) {
            $car['CAT_COMFORT_NAME'] = $postComfortPermissions[$car['CAT_COMFORT_VAL']];
            $this->arResult['ITEMS'][] = $car;
        }
    }

    public function PrepareFilterParam()
    {
        $context = Context::getCurrent();
        $request = $context->getRequest();

        $this->filter['date-from'] = $request->getQuery("date-from");
        $this->filter['date-to'] = $request->getQuery("date-to");

        $curUserId = CurrentUser::get()->getId();
        $rsUser = CUser::GetByID($curUserId);
        $arUser = $rsUser->Fetch();

        $this->filter['post_id'] = $arUser['UF_POST'];

        if (!$this->filter['date-from']) {
            $this->addError('date', 'Отсутствует дата начала');
        }
        if (!$this->filter['date-to']) {
            $this->addError('date', 'Отсутствует дата окончания');
        }
        if (!$this->filter['post_id']) {
            $this->addError('user', 'Не заполнена должность у пользователя');
        }
    }

    public function executeComponent()
    {
        $this->PrepareFilterParam();
        if ($this->hasErrors()) {
            $this->getErrors();
        } else {
            $this->GetData();
        }

        echo "<pre>"; var_export($this->arResult['ITEMS']); echo "</pre>";
    }
}