<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die;
}

use Bitrix\Main;
use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Cargonomica\Service\ModuleOptions\CargonomicaMainOptionsService;

Loc::loadMessages(__FILE__);

global $USER, $APPLICATION;
$moduleId = 'cargonomica.main';

$modulePermissions = $APPLICATION->GetGroupRight($moduleId);
if ($modulePermissions < "R") {
    return;
}

IncludeModuleLangFile(__FILE__);

$tabs = [
    [
        'DIV' => 'cron',
        'TAB' => Loc::getMessage('CRON_TAB_NAME'),
        'TITLE' => Loc::getMessage('CRON_TAB_TITLE'),
    ],
];
$tabControl = new CAdminTabControl('tabControl', $tabs);

/**
 * Небольшой гайд по настройкам модуля
 *
 * формат массива, элементы:
 * 1) ID опции (id инпута)
 * 2) Отображаемое имя опции
 * 3) Значение по умолчанию (так же берется если первый элемент равен пустой строке), зависит от типа:
 *      checkbox - Y если выбран
 *      text/password - htmlspecialcharsbx($val)
 *      selectbox - одно из значений, указанных в массиве опций
 *      multiselectbox - значения через запятую, указанные в массиве опций
 * 4) Тип поля (массив)
 *      1) Тип (multiselectbox, textarea, statictext, statichtml, checkbox, text, password, selectbox)
 *      2) Зависит от типа:
 *         text/password - атрибут size
 *         textarea - атрибут rows
 *         selectbox/multiselectbox - массив опций формата ["Значение"=>"Название"]
 *      3) Зависит от типа:
 *         checkbox - доп атрибут для input (просто вставляется строкой в атрибуты input)
 *         textarea - атрибут cols
 *
 *      noautocomplete) для text/password, если true то атрибут autocomplete="new-password"
 *
 * 5) Disabled = 'Y' || 'N';
 * 6) $sup_text - текст маленького красного примечания над названием опции
 * 7) $isChoiceSites - Нужно ли выбрать сайт (флаг Y или N)
 */

$options = [
    'cron' => [
        CargonomicaMainOptionsService::setTitle(Loc::getMessage('CRON_TAB_WON_DEALS_REPORT_TITLE')),
        [
            'report_send_daily',
            Loc::getMessage('CRON_TAB_REPORT_SEND_DAILY'),
            CargonomicaMainOptionsService::getOption('report_send_daily'),
            ['checkbox', 'N'],
        ],
        [
            'report_emails',
            Loc::getMessage('CRON_TAB_REPORT_EMAIL'),
            CargonomicaMainOptionsService::getOption('report_emails'),
            ['text'],
        ],
        [
            'last_report_timestamp',
            Loc::getMessage('CRON_TAB_LAST_REPORT_TIMESTAMP'),
            CargonomicaMainOptionsService::getOption('last_report_timestamp'), // 'Y-m-d H:i:s'
            ['text'],
            'Y', // Disabled=
        ],
        [
            'last_report_deals_count',
            Loc::getMessage('CRON_TAB_LAST_REPORT_DEALS_COUNT'),
            CargonomicaMainOptionsService::getOption('last_report_deals_count', 0),
            ['text'],
            'Y', // Disabled=
        ],
    ],
];

$request = Application::getInstance()->getContext()->getRequest();
$save = $request->get('save');

if ($request->isPost() && !empty($save) && check_bitrix_sessid()) {
    try {
        foreach ($options as $ops) {
            __AdmSettingsSaveOptions($moduleId, $ops);
        }

        CAdminMessage::ShowMessage([
            'TYPE' => 'OK',
            'MESSAGE' => Loc::getMessage('CRON_SAVE_SUCCESS'),
        ]);
    }
    catch (Exception $e) {
        CAdminMessage::ShowMessage([
            'TYPE' => 'ERROR',
            'MESSAGE' => $e->getMessage(),
        ]);
    }
}
?>

<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?lang=<?= LANG ?>&mid=<?= $moduleId ?>">
    <?= bitrix_sessid_post() ?>
    <?php
    $tabControl->Begin();

    foreach ($tabs as $tab) {
        $tabControl->beginNextTab();
        __AdmSettingsDrawList($moduleId, $options[$tab['DIV']]);
        $tabControl->EndTab();
    }

    $tabControl->Buttons([
        'btnApply' => false,
    ]);

    $tabControl->End();
    ?>
</form>
