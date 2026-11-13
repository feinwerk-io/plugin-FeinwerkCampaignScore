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
use Piwik\Plugins\FeinwerkCampaignScore\Data\ConventionFactory;
use Piwik\Settings\FieldConfig;
use Piwik\Settings\Setting;

/**
 * The naming convention of one website (admins of the website, Websites > Manage): its own mediums, the values it
 * accepts, its separator and name pattern. Empty or "inherit": the system settings apply.
 */
class MeasurableSettings extends \Piwik\Settings\Measurable\MeasurableSettings
{
    /** @var Setting */
    public $mediums;

    /** @var Setting */
    public $accepted;

    /** @var Setting */
    public $separator;

    /** @var Setting */
    public $namePattern;

    protected function init()
    {
        // the same heading in every language, never translated (house rule)
        $this->title = 'Feinwerk Campaign Score';

        $this->accepted = $this->makeSetting(
            'accepted',
            '',
            FieldConfig::TYPE_STRING,
            static function (FieldConfig $field) {
                $field->title = Piwik::translate('FeinwerkCampaignScore_SettingAcceptedTitle');
                $field->description = Piwik::translate('FeinwerkCampaignScore_SettingAcceptedDescription');
                $field->uiControl = FieldConfig::UI_CONTROL_TEXTAREA;
            }
        );

        $this->mediums = $this->makeSetting(
            'mediums',
            '',
            FieldConfig::TYPE_STRING,
            static function (FieldConfig $field) {
                $field->title = Piwik::translate('FeinwerkCampaignScore_SettingMediumsTitle');
                $field->description = Piwik::translate('FeinwerkCampaignScore_SettingSiteMediumsDescription');
                $field->uiControl = FieldConfig::UI_CONTROL_TEXTAREA;
            }
        );

        $this->separator = $this->makeSetting(
            'separator',
            ConventionFactory::SEPARATOR_INHERIT,
            FieldConfig::TYPE_STRING,
            static function (FieldConfig $field) {
                $field->title = Piwik::translate('FeinwerkCampaignScore_SettingSeparatorTitle');
                $field->description = Piwik::translate('FeinwerkCampaignScore_SettingSeparatorDescription');
                $field->uiControl = FieldConfig::UI_CONTROL_SINGLE_SELECT;
                $field->availableValues = [ConventionFactory::SEPARATOR_INHERIT => Piwik::translate('FeinwerkCampaignScore_SeparatorInherit')]
                    + SystemSettings::separatorOptions();
            }
        );

        $this->namePattern = $this->makeSetting(
            'namePattern',
            '',
            FieldConfig::TYPE_STRING,
            static function (FieldConfig $field) {
                $field->title = Piwik::translate('FeinwerkCampaignScore_SettingNamePatternTitle');
                $field->description = Piwik::translate('FeinwerkCampaignScore_SettingSiteNamePatternDescription');
                $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
                // Matomo calls only a Closure here (Setting::validateValue checks instanceof \Closure)
                $field->validate = static function ($value): void {
                    SystemSettings::validatePattern($value);
                };
            }
        );
    }

    /** at most this many accepted values per website (the setting is one text value in Matomo's database) */
    public const MAX_ACCEPTED = 500;

    /**
     * Adds values to the accepted ones (the row action "accept" of the findings table) and saves.
     *
     * @param list<string> $values
     * @return int the number of accepted values afterwards
     * @throws \LengthException beyond MAX_ACCEPTED values
     */
    public function accept(array $values): int
    {
        try {
            $merged = self::mergeAccepted((string) $this->accepted->getValue(), $values);
        } catch (\LengthException $e) {
            throw new \LengthException(Piwik::translate('FeinwerkCampaignScore_ErrorTooManyAccepted', [self::MAX_ACCEPTED]));
        }
        $this->accepted->setValue($merged);
        $this->save();
        return count(Convention::acceptedFromText($merged));
    }

    /**
     * The stored list (one value per line) with the new values added once. Pure.
     *
     * @param list<string> $values
     * @throws \LengthException beyond MAX_ACCEPTED values
     */
    public static function mergeAccepted(string $existing, array $values): string
    {
        $accepted = Convention::acceptedFromText($existing);
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $accepted[$value] = true;
            }
        }
        if (count($accepted) > self::MAX_ACCEPTED) {
            throw new \LengthException('more than ' . self::MAX_ACCEPTED . ' accepted values');
        }
        return implode("\n", array_map('strval', array_keys($accepted)));
    }
}
