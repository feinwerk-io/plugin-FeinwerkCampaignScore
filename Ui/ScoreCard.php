<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\FeinwerkCampaignScore\Ui;

/**
 * The variables of templates/_scoreCard.twig: the score with its basis, the fields checked, the visits that could not
 * be checked, a hint without Marketing Campaigns Reporting, and (dashboard widget) the largest problems.
 */
final class ScoreCard
{
    /** size of the sparkline in the card (SVG viewBox) */
    public const SPARK_WIDTH = 120;
    public const SPARK_HEIGHT = 28;

    /** from this score on the card shows "good", below OK_FROM "poor" */
    public const GOOD_FROM = 90.0;
    public const OK_FROM = 70.0;

    /**
     * @param array<string, mixed> $score the columns of the API method getScore
     * @param list<array<string, mixed>> $findings rows of getFindings (columns plus the metadata "values")
     * @param bool $mcrActivated whether Marketing Campaigns Reporting is activated
     * @param callable(string, mixed...): string $translate
     * @param callable(int|float): string $formatNumber
     * @param callable(int|float): string $formatPercent
     * @return array<string, mixed>
     */
    public static function variables(
        array $score,
        array $findings,
        bool $mcrActivated,
        callable $translate,
        callable $formatNumber,
        callable $formatPercent
    ): array {
        $withSourceMedium = !empty($score['with_source_medium']);
        $unchecked = (int) ($score['unchecked_visits'] ?? 0);
        $hasData = isset($score['score']);
        $value = $hasData ? (float) $score['score'] : 0.0;

        $problems = [];
        foreach ($findings as $finding) {
            $type = (string) ($finding['type'] ?? '');
            $problems[] = [
                'problem' => FindingTexts::type($type, $translate),
                'field' => FindingTexts::field((string) ($finding['field'] ?? ''), $translate),
                'value' => FindingTexts::value($type, (string) ($finding['label'] ?? ''), (array) ($finding['values'] ?? []), $translate, $formatNumber),
                'visits' => $formatNumber((int) ($finding['nb_visits'] ?? 0)),
            ];
        }

        $delta = null;
        $deltaDirection = null;
        if ($hasData && isset($score['previous_score'])) {
            $points = (int) round($value) - (int) round((float) $score['previous_score']);
            $deltaDirection = $points > 0 ? 'up' : ($points < 0 ? 'down' : 'same');
            $delta = $points === 0
                ? $translate('FeinwerkCampaignScore_DeltaSame')
                : $translate('FeinwerkCampaignScore_DeltaChange', ($points > 0 ? '+' : '-') . abs($points));
        }

        return [
            'hasData' => $hasData,
            'delta' => $delta,
            'deltaDirection' => $deltaDirection,
            'noData' => $translate('FeinwerkCampaignScore_ScoreNoData'),
            'score' => $hasData ? $formatPercent((int) round($value)) : '',
            'level' => $value >= self::GOOD_FROM ? 'good' : ($value >= self::OK_FROM ? 'ok' : 'poor'),
            'basis' => $translate(
                'FeinwerkCampaignScore_ScoreBasis',
                $formatNumber((int) ($score['clean_visits'] ?? 0)),
                $formatNumber((int) ($score['checked_visits'] ?? 0))
            ),
            'fields' => $translate($withSourceMedium ? 'FeinwerkCampaignScore_ScoreFieldsAll' : 'FeinwerkCampaignScore_ScoreFieldsName'),
            'unchecked' => $unchecked > 0 ? $translate('FeinwerkCampaignScore_ScoreUnchecked', $formatNumber($unchecked)) : null,
            'hint' => !$withSourceMedium && !$mcrActivated ? $translate('FeinwerkCampaignScore_HintSourceMedium') : null,
            'problems' => $problems,
        ];
    }

    /**
     * The points of the sparkline (SVG polyline) for the scores of the last periods, oldest first, on a fixed scale
     * from 0 to 100 percent; periods without a score are left out. Null with fewer than two scores.
     *
     * @param list<float|null> $scores percent
     */
    public static function sparkline(array $scores): ?string
    {
        $scores = array_values(array_filter($scores, static function ($score): bool {
            return $score !== null;
        }));
        $count = count($scores);
        if ($count < 2) {
            return null;
        }
        $points = [];
        foreach ($scores as $i => $score) {
            $x = $i * self::SPARK_WIDTH / ($count - 1);
            $y = self::SPARK_HEIGHT - max(0.0, min(100.0, (float) $score)) / 100 * self::SPARK_HEIGHT;
            $points[] = self::number($x) . ',' . self::number($y);
        }
        return implode(' ', $points);
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }
}
