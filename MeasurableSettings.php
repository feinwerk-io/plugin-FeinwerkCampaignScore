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
     * Adds values to the accepted ones (the row action "accept" of the findings table) and saves, unless $dryRun.
     *
     * @param list<string> $values
     * @return array{accepted: int, added: list<string>, already: list<string>} accepted: the number afterwards
     * @throws \LengthException beyond MAX_ACCEPTED values
     */
    public function accept(array $values, bool $dryRun = false): array
    {
        $existing = (string) $this->accepted->getValue();
        try {
            $merged = self::mergeAccepted($existing, $values);
        } catch (\LengthException $e) {
            throw new \LengthException(Piwik::translate('FeinwerkCampaignScore_ErrorTooManyAccepted', [self::MAX_ACCEPTED]));
        }
        if (!$dryRun) {
            $this->accepted->setValue($merged);
            $this->save();
        }

        return ['accepted' => count(Convention::acceptedFromText($merged))] + self::splitByPresence($existing, $values);
    }

    /**
     * Takes values out of the accepted ones and saves, unless $dryRun.
     *
     * @param list<string> $values
     * @return array{accepted: int, removed: list<string>, not_found: list<string>} accepted: the number afterwards
     */
    public function removeAccepted(array $values, bool $dryRun = false): array
    {
        $existing = (string) $this->accepted->getValue();
        [$remaining, $removed, $notFound] = self::withoutAccepted($existing, $values);
        if (!$dryRun && $removed !== []) {
            $this->accepted->setValue($remaining);
            $this->save();
        }

        return ['accepted' => count(Convention::acceptedFromText($remaining)), 'removed' => $removed, 'not_found' => $notFound];
    }

    /**
     * Which of the given values are new and which are accepted already. Pure.
     *
     * @param list<string> $values
     * @return array{added: list<string>, already: list<string>}
     */
    public static function splitByPresence(string $existing, array $values): array
    {
        $accepted = Convention::acceptedFromText($existing);
        $added = [];
        $already = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '' || in_array($value, $added, true) || in_array($value, $already, true)) {
                continue;
            }
            if (isset($accepted[$value])) {
                $already[] = $value;
            } else {
                $added[] = $value;
            }
        }

        return ['added' => $added, 'already' => $already];
    }

    /**
     * The stored list without the given values. Pure.
     *
     * @param list<string> $values
     * @return array{0: string, 1: list<string>, 2: list<string>} the list left, the values removed, the values not found
     */
    public static function withoutAccepted(string $existing, array $values): array
    {
        $accepted = Convention::acceptedFromText($existing);
        $removed = [];
        $notFound = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '' || in_array($value, $removed, true) || in_array($value, $notFound, true)) {
                continue;
            }
            if (isset($accepted[$value])) {
                unset($accepted[$value]);
                $removed[] = $value;
            } else {
                $notFound[] = $value;
            }
        }

        return [implode("\n", array_map('strval', array_keys($accepted))), $removed, $notFound];
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
