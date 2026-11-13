<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore;

use Piwik\Piwik;
use Piwik\Plugins\FeinwerkCampaignScore\Check\Convention;
use Piwik\Plugins\FeinwerkCampaignScore\Check\MediumList;
use Piwik\Settings\FieldConfig;
use Piwik\Settings\Setting;

/**
 * The naming convention for all websites (super users, Administration > System > General settings). Each website can
 * override mediums, separator and name pattern and add accepted values (MeasurableSettings).
 */
class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    /** @var Setting */
    public $mediums;

    /** @var Setting */
    public $separator;

    /** @var Setting */
    public $namePattern;

    /** @var Setting */
    public $sourceAliases;

    /** @var Setting */
    public $typoHints;

    protected function init()
    {
        // the same heading in every language, never translated (house rule)
        $this->title = 'Feinwerk Campaign Score';

        $this->mediums = $this->makeSetting(
            'mediums',
            implode("\n", MediumList::DEFAULTS),
            FieldConfig::TYPE_STRING,
            static function (FieldConfig $field) {
                $field->title = Piwik::translate('FeinwerkCampaignScore_SettingMediumsTitle');
                $field->description = Piwik::translate('FeinwerkCampaignScore_SettingMediumsDescription');
                $field->uiControl = FieldConfig::UI_CONTROL_TEXTAREA;
            }
        );

        $this->separator = $this->makeSetting(
            'separator',
            Convention::SEPARATOR_ANY,
            FieldConfig::TYPE_STRING,
            static function (FieldConfig $field) {
                $field->title = Piwik::translate('FeinwerkCampaignScore_SettingSeparatorTitle');
                $field->description = Piwik::translate('FeinwerkCampaignScore_SettingSeparatorDescription');
                $field->uiControl = FieldConfig::UI_CONTROL_SINGLE_SELECT;
                $field->availableValues = self::separatorOptions();
            }
        );

        $this->namePattern = $this->makeSetting(
            'namePattern',
            '',
            FieldConfig::TYPE_STRING,
            static function (FieldConfig $field) {
                $field->title = Piwik::translate('FeinwerkCampaignScore_SettingNamePatternTitle');
                $field->description = Piwik::translate('FeinwerkCampaignScore_SettingNamePatternDescription');
                $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
                // Matomo calls only a Closure here (Setting::validateValue checks instanceof \Closure)
                $field->validate = static function ($value): void {
                    SystemSettings::validatePattern($value);
                };
            }
        );

        $this->sourceAliases = $this->makeSetting(
            'sourceAliases',
            true,
            FieldConfig::TYPE_BOOL,
            static function (FieldConfig $field) {
                $field->title = Piwik::translate('FeinwerkCampaignScore_SettingSourceAliasesTitle');
                $field->description = Piwik::translate('FeinwerkCampaignScore_SettingSourceAliasesDescription');
                $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
            }
        );

        $this->typoHints = $this->makeSetting(
            'typoHints',
            true,
            FieldConfig::TYPE_BOOL,
            static function (FieldConfig $field) {
                $field->title = Piwik::translate('FeinwerkCampaignScore_SettingTypoHintsTitle');
                $field->description = Piwik::translate('FeinwerkCampaignScore_SettingTypoHintsDescription');
                $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
            }
        );
    }

    /** @return array<string, string> */
    public static function separatorOptions(): array
    {
        return [
            Convention::SEPARATOR_ANY => Piwik::translate('FeinwerkCampaignScore_SeparatorAny'),
            Convention::SEPARATOR_HYPHEN => Piwik::translate('FeinwerkCampaignScore_SeparatorHyphen'),
            Convention::SEPARATOR_UNDERSCORE => Piwik::translate('FeinwerkCampaignScore_SeparatorUnderscore'),
        ];
    }

    /**
     * @param mixed $value
     * @throws \Exception for a pattern PHP cannot compile
     */
    public static function validatePattern($value): void
    {
        if (!Convention::isValidPattern(trim((string) $value))) {
            throw new \Exception(Piwik::translate('FeinwerkCampaignScore_ErrorNamePattern'));
        }
    }
}
