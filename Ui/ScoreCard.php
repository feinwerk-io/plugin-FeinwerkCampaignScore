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
        // without a score: no campaign visits at all, or campaign visits none of which could be checked (release review
        // 2026-11-24: the second case got the advice to add mtm_/utm_ parameters, which the links already carry)
        $campaignVisits = (int) ($score['campaign_visits'] ?? 0);
        $value = $hasData ? (float) $score['score'] : 0.0;

        $problems = [];
        foreach ($findings as $finding) {
            $type = (string) ($finding['type'] ?? '');
            $problems[] = [
                'problem' => FindingTexts::type($type, $translate),
                'field' => FindingTexts::field((string) ($finding['field'] ?? ''), $translate),
                'value' => FindingTexts::value($type, (string) ($finding['label'] ?? ''), (array) ($finding['values'] ?? []), $translate, $formatNumber),
                'visits' => $formatNumber((int) ($finding['nb_visits'] ?? 0)),
                // the fix right in the widget (UI review 2026-11-24, design D2); '' where none can be given
                'suggestion' => (string) ($finding['suggestion'] ?? ''),
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
            'noData' => $translate($campaignVisits > 0 ? 'FeinwerkCampaignScore_ScoreNoneChecked' : 'FeinwerkCampaignScore_ScoreNoData'),
            'noDataHint' => $campaignVisits > 0
                ? $translate('FeinwerkCampaignScore_ScoreUnchecked', $formatNumber($unchecked > 0 ? $unchecked : $campaignVisits))
                : $translate('FeinwerkCampaignScore_ScoreNoDataHint'),
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
     * The sparkline (SVG path data) for the scores of the last periods, oldest first, on a fixed scale from 0 to 100
     * percent. Every period keeps its place: "line" joins neighbouring scores, "gaps" bridges periods without a score
     * (drawn faint and dotted), and a score with no neighbour is a dot (a segment of length 0 with round line caps).
     * Null with fewer than two scores.
     *
     * @param list<float|null> $scores percent
     * @return array{line: string, gaps: string}|null
     */
    public static function sparkline(array $scores): ?array
    {
        $scores = array_values($scores);
        $count = count($scores);
        $points = [];
        foreach ($scores as $i => $score) {
            if ($score !== null) {
                $x = $i * self::SPARK_WIDTH / max(1, $count - 1);
                $y = self::SPARK_HEIGHT - max(0.0, min(100.0, (float) $score)) / 100 * self::SPARK_HEIGHT;
                $points[$i] = self::number($x) . ',' . self::number($y);
            }
        }
        if (count($points) < 2) {
            return null;
        }
        $line = '';
        $gaps = '';
        $previous = null;
        foreach ($points as $i => $point) {
            if ($previous === null || $i - $previous > 1) {
                if ($previous !== null) {
                    $gaps .= 'M' . $points[$previous] . 'L' . $point;
                }
                $alone = !isset($points[$i + 1]);
                $line .= 'M' . $point . ($alone ? 'l0,0' : '');
            } else {
                $line .= 'L' . $point;
            }
            $previous = $i;
        }
        return ['line' => $line, 'gaps' => $gaps];
    }

    /**
     * The index of the latest period before the selected one (the last entry) that has a score; null when none has.
     *
     * @param list<float|null> $scores oldest first, the selected period last
     */
    public static function lastScoredBefore(array $scores): ?int
    {
        $scores = array_values($scores);
        for ($i = count($scores) - 2; $i >= 0; $i--) {
            if ($scores[$i] !== null) {
                return $i;
            }
        }
        return null;
    }

    /**
     * The next longer period to look at when the selected one has no campaign visits: the month for a day or a week,
     * the year for a month. Null for a year, a date range or anything else.
     */
    public static function longerPeriod(string $period): ?string
    {
        return ['day' => 'month', 'week' => 'month', 'month' => 'year'][$period] ?? null;
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }
}
