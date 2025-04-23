<?php

namespace Cargonomica\Service\ModuleOptions;

use Bitrix\Main\Config\Option;

/**
 * Сервис для работы с настройками модуля cargonomica.main
 */
class CargonomicaMainOptionsService
{
    /**
     * ID модуля
     * @var string
     */
    public const MODULE_ID = 'cargonomica.main';

    /**
     * Возвращает значение настройки модуля
     * @param string $optionName Название настройки
     * @param string|null $default Значение, возвращаемое по умолчанию, если значение настройки не установлено
     * @return string|null
     */
    public static function getOption(string $optionName, string $default = null): string|null
    {
        return Option::get(self::MODULE_ID, $optionName, $default);
    }

    /**
     * Возвращает массив значений настроек модуля
     * @param array $optionsNames
     * @return array Массив вида ['optionName' => 'optionValue']
     */
    public static function getOptions(array $optionsNames): array
    {
        $optionsValues = [];
        foreach ($optionsNames as $option) {
            $optionsValues[$option] = self::getOption($option);
        }
        return $optionsValues;
    }

    /**
     * Устанавливает значение настройки модуля
     * @param string $optionName Название настройки
     * @param mixed $optionValue Значение настройки
     * @return void
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     */
    public static function setOption(string $optionName, mixed $optionValue): void
    {
        Option::set(self::MODULE_ID, $optionName, $optionValue);
    }

    /**
     * Возвращает строку с кодом элемента для заголовка вкладки options.php модуля
     * @param string $name Название вкладки
     * @return string
     */
    public static function setTitle(string $name): string
    {
        return "<span>{$name}</span>";
    }

    /**
     * Устанавливает несколько значений настроек модуля
     * @param array $optionsArray Массив настроек вида ['optionName' => 'optionValue']
     * @return void
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     */
    public static function setOptions(array $optionsArray): void
    {
        foreach ($optionsArray as $option => $value) {
            self::setOption($option, $value);
        }
    }
}
