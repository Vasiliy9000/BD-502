<?php
use Bitrix\Main\ModuleManager;

/**
 * Инсталлятор/деинсталлятор модуля.
 */
class cargonomica_main extends CModule
{
    /**
     * @var string
     */
    public $MODULE_ID = 'cargonomica.main';

    /**
     * @var string
     */
    public $MODULE_NAME = 'Cargonomica Main';

    /**
     * @var string
     */
    public $MODULE_DESCRIPTION = 'Модуль для отображения и изменения настроек';

    /**
     * @var mixed|string
     */
    public $MODULE_VERSION = '1.0.0';

    /**
     * @var mixed|string
     */
    public $MODULE_VERSION_DATE = '2024-08-12 12:00:00';

    public function __construct()
    {
        $arModuleVersion = [];

        include __DIR__ . '/version.php';

        if ($arModuleVersion) {
            $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        }
    }

    /**
     * @return void
     */
    public function DoInstall(): void
    {
        ModuleManager::registerModule($this->MODULE_ID);
    }

    /**
     * @return void
     */
    public function DoUninstall(): void
    {
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
}
