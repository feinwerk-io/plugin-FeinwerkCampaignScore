# Feinwerk Campaign Score

## Description

**How clean is your campaign tracking? Campaign Score answers with one number, and shows you what to fix.**

"Newsletter", "newsletter" and "news_letter" are one campaign, but Matomo reports three. Values like these split your
campaign reports and make every comparison wrong. Campaign Score reads the campaign reports Matomo already has and
gives you the share of campaign visits with clean values, for any website, period and segment.

- **One score with context:** the score of the period with a verdict, its change to the previous period, a small trend
  line and a bar per field that shows where points are lost, also as a dashboard widget with the top findings.
- **What to fix first:** every finding shows the score points it costs, and the findings are sorted by them.
- **The trend:** Campaign Score over time shows whether your campaign data gets cleaner. One click sends you the score
  every week by email, and a drop of 5 points or more opens that email.
- **Every finding with a fix:** spelling variants, possible typos, unusual mediums, missing values, source aliases
  such as "fb" for facebook, separators and names outside your pattern, each with the field, the visits and a
  suggestion.
- **Context for each finding:** where the value occurs, what is new since the previous period, and the matching visits
  in the visitor log with one click.
- **Your rules per website:** values marked as correct, usual mediums (one click adds an unusual one), the separator and
  an optional pattern for campaign names.
- **All websites at once:** the score of every website in one table, compared with the previous period on request, a
  correction list as CSV and your clean values as a naming convention file.
- **More fields with Marketing Campaigns Reporting:** with this free plugin, Campaign Score also checks source, medium,
  content, group and placement.

Campaign Score reads only Matomo's archived reports, never the raw visits, adds no archiving and no tables of its own,
and sends nothing anywhere, apart from an optional thumbs up or down on whether the report helps you.

**Works with Feinwerk Campaigns:** keep new campaign links clean from the start with Feinwerk Campaigns, which takes
your naming convention from Campaign Score.

**Get started:** open *Acquisition › Campaign Score*.

**More Plugins for Matomo by Feinwerk:** install [Feinwerk Connector](https://plugins.matomo.org/FeinwerkMarketplaceConnector)
from the Matomo Marketplace to discover, install and update all Feinwerk plugins, free and paid, right in your Matomo.
Documentation: [plugins.feinwerk.io](https://plugins.feinwerk.io/en/plugins/FeinwerkCampaignScore).

## Requirements

- Matomo 5 (5.0 or later), PHP 8.1 or later
- Versions: Matomo 5: version 5.x of this plugin, Matomo 6: version 6.x of this plugin
- Optional: the free plugin Marketing Campaigns Reporting. With it, Campaign Score also checks campaign source and
  medium; without it, the campaign name and keyword. While it is not active, an info box above the report says how to
  install or activate it.

## Installation

From Matomo's own Marketplace (**Administration → Platform → Marketplace**, search for "Campaign Score"), with one
click through the Feinwerk Marketplace Connector (**Administration → Feinwerk Plugins → Discover**; the connector itself
is on the Matomo Marketplace too, search for "Feinwerk") or as a ZIP file, see [Installing plugins](https://plugins.feinwerk.io/en/docs/installing-plugins). To install the ZIP
by hand, copy the folder to `plugins/FeinwerkCampaignScore` and activate it under
**Administration → Plugins → Manage Plugins** or on the command line:

```
./console plugin:activate FeinwerkCampaignScore
```

## Usage

Open **Acquisition → Campaign Score** (right after Campaigns). The page follows the website, period and segment you
selected.

**The score** is the share of campaign visits whose values are all clean, shown together with its basis, for example
"650 of 800 campaign visits with clean values". With Marketing Campaigns Reporting the campaign name, source and medium
count; without it, the campaign name. A value is clean when it

- is written in lowercase,
- uses only the letters a to z, the digits 0 to 9, underscore, hyphen and full stop,
- is not a deviating spelling of another value and no known alias of a source (see below),
- follows the separator and, for campaign names, the pattern of your convention, if you set them,
- and, for the medium, is in the list of usual mediums,
- or when an admin marked it as correct for the website.

Next to the score you see a verdict (good, needs work, poor), how it changed compared to the previous period ("+6
points compared to the previous period"), a small line with the score of the last eight periods and, with Marketing
Campaigns Reporting, a bar for campaign name, source and medium that shows where the points are lost. The card also
counts the findings fixed since the previous period: those whose clean spelling is in use now. Below the score card,
**Campaign Score over time** shows the score as a graph. *Send me the Campaign Score every week by email* creates a
Matomo email report with the score and the findings; when the score fell by 5 points or more, the email opens with that
news and marks the new findings.

**The findings** list every problem with the score points it costs, the most first: fix the top ones first. All points
together add up to what the score misses (a visit with two problems counts half for each). Spelling variants show as
small labels with their visits, the clean spelling marked. Under each value you see where it occurs (campaign name,
source and medium), and findings that were not there in the previous period are marked "new". The labels above the
table filter the findings by type. Rows without a suggestion say what to do.

| Problem | What it means | Suggestion |
|---|---|---|
| Spelling variants | Values that only differ in case, spaces, underscores, hyphens or full stops, such as "Newsletter", "news_letter" and "newsletter". Only the suggested spelling counts as clean. | The most visited clean variant |
| Source alias | Another spelling of a large platform as source, such as "fb" or "m.facebook.com" for facebook. | The usual name, here "facebook" |
| Format | A value with capital letters, spaces or other characters, such as "Black Friday 2026". | The clean form, here "black-friday-2026" |
| Unusual medium | A medium that is not in the list of usual mediums, such as "Facebook Ads". | Use a medium from the list, or add yours to it |
| Separator | A value with the other separator than your convention, or one that mixes hyphen and underscore, such as "black-friday_2026". | The value with the right separator |
| Outside the pattern | A campaign name that does not match the pattern of your convention. | Rename it to fit the pattern |
| Missing value | Campaign visits without a campaign name, source or medium (with Marketing Campaigns Reporting). | Add the missing parameter to the links |
| Possible typo | Two values that differ by one or two letters, such as "newsleter" and "newsletter". A hint only: it does not lower the score. | The more visited value |

Campaign keywords (`utm_term`, `mtm_kwd`) and content are only checked for spelling variants: they often hold search
terms or ad texts with spaces. With Marketing Campaigns Reporting, campaign group and placement are checked as well.
Keyword, content, group and placement do not count in the score.

**Rows of the findings table** have two actions when you move the mouse over them, and a button where there is no
suggestion:

- *Visits log* (Matomo's segmented visitor log) shows the visits with exactly these values, so you find the links that
  produce them.
- *Mark as correct* (admins of the website): the value counts as clean from now on and no longer appears as a finding.
  Matomo asks for confirmation first. These values are listed in the website settings under *Values marked as correct*,
  where you can remove them (at most 500 per website). Such a value counts as clean in every field.
- *Add to usual mediums* (admins, for an unusual medium): the medium counts as usual for the website from now on, in its
  clean spelling (Facebook Ads becomes facebook-ads). It is listed in the website settings under *Additional usual
  mediums*.

**Downloads** with the score card (only for a period with campaign visits):

- *Download correction list (CSV)*: one line per value to fix, with field, problem, visits and suggestion, to work
  through or to pass on to the team. Opens in Excel, Numbers and LibreOffice.
- *Export as naming convention for Feinwerk Campaigns*: the clean values of the period as a file that Feinwerk
  Campaigns can take over as a starting convention. It holds only values and their visit counts, nothing about
  visitors, and is not sent anywhere: you download it and decide what to do with it. For admins it is the first step of
  the guide to Feinwerk Campaigns beside the score card (see "Tips from Feinwerk"); once that guide is hidden, the
  export is a link under the score again. In Feinwerk Campaigns, *Suggest a naming convention*
  (https://app.campaigns.feinwerk.io/convention/suggest, the guide's second step) takes the file: you review its values,
  and only the ones you select are added; nothing existing is changed or deleted, and the file is deleted after at most
  30 minutes.

**Dashboard widgets:** under **Dashboard → Add a widget → Acquisition** you find *Campaign Score* (the score, the
three largest problems and a link to the full report) and *Campaign Score over time*. Users with access to more than
one website also get **Campaign Score of all websites**, a table with the score, campaign visits and findings of
every website for the selected day, week, month or year, at the end of the report page and as a dashboard widget. With
more than ten websites it loads on a click, with more than 100 it shows the 100 websites with the most visits.

**Alerts and email reports:** *Campaign Score over time* is a regular Matomo report, so you can add it to scheduled
email reports, and, where the plugin Custom Alerts is installed, create an alert when the Campaign Score falls below a
value of your choice.

**Settings for all websites** (super users, **Administration → System → General settings → Feinwerk Campaign Score**):

- *Usual mediums*: one medium per line. The default list holds the mediums that the plugin Feinwerk Channel Grouping
  assigns to a channel (for example `cpc`, `email`, `social`, `display`, `affiliate`, `referral`), so both plugins
  agree on what a clean medium is; the whole default list is shown below the field. Empty the field and save to go back
  to the default list.
- *Separator in values*: either (only values that mix both are a finding), hyphen or underscore.
- *Pattern for campaign names*: optional, a regular expression every campaign name should match, for example
  `^[0-9]{4}-` for names that start with a year.
- *Recognise source aliases* and *Show possible typos*: both on by default.

**Settings per website** (admins of the website, **Administration → Websites → Manage**, edit the website, section
Feinwerk Campaign Score): *Values marked as correct*, *Additional usual mediums* (added to the list that applies), and
the website's own *Usual mediums*, *Separator* and *Pattern for campaign names*. Empty fields use the settings for all
websites.

Campaign Score changes no tracking data. It reads Matomo's existing campaign reports when you open the page, and it
stores only your settings and the values you marked as correct.

## FAQ

**Do I need Marketing Campaigns Reporting?**
No. Without it Campaign Score checks the campaign name and keyword; with this free plugin also source, medium, content,
group and placement.

**Does Campaign Score change my data?**
No. It only reads the campaign reports Matomo already archived and stores your settings per website.

**Where do I find more Feinwerk plugins?**
Install Feinwerk Connector from the Matomo Marketplace. It lists all Plugins for Matomo by Feinwerk, free and paid, and
installs and updates them with one click.

**How does Campaign Score handle capitalisation?**
Without Marketing Campaigns Reporting, Matomo writes campaign names and keywords in lowercase. Marketing Campaigns
Reporting keeps the original capitalisation by default (its setting "Keep campaign parameter capitalisation"), also in
Matomo's own campaign report, so Campaign Score can show values such as "Black Friday 2026" as not clean. Values that
differ only in capitalisation ("Newsletter" and "newsletter") are usually grouped into one row per day by Matomo's
database, under one of the spellings; Campaign Score then sees only that spelling, and finds such variants only where
different days used different spellings. If you switch the setting of Marketing Campaigns Reporting off, it writes all
values in lowercase from then on, and only variants such as "news_letter" and "newsletter" remain.

**What does "could not be checked" mean?**
For large accounts Matomo groups rare rows of its campaign reports as "Others" (by default after 1,000 rows, and after
50 campaign names per source and medium). The values in these rows are unknown, so their visits are left out of the
basis of the score. The same applies to values masked by Matomo's privacy setting for campaign parameters, and to
campaign visits from before Marketing Campaigns Reporting was activated: Matomo knows their campaign name, but not
their source and medium.

**Why did the score change after I edited a setting or accepted a value?**
The score is computed when you open the report, so a change applies at once, also to past periods.

**Does Campaign Score work on large Matomo instances?**
It reads only Matomo's archived reports, never the raw visit data, and adds no archiving of its own. The table of all
websites reads the reports of at most 100 websites, ten at a time, keeps the result for 15 minutes and, with more than
ten websites, loads only when you click its button. For a date range it asks for a day, week, month or year, because
Matomo would compute the range for every website. As everywhere in Matomo, reports load fastest when the archiving runs
as a cron job (browser-triggered archiving switched off).

**Which source aliases does Campaign Score know?**
Only spellings that cannot mean anything else: for example fb, facebook.com and m.facebook.com (facebook), ig and
instagram.com (instagram), adwords and google ads (google), linkedin.com and lnkd.in (linkedin), yt and youtube.com
(youtube), bing ads and microsoft ads (bing). If your team uses one of them on purpose, accept it for the website or
switch the aliases off.

## API

- `FeinwerkCampaignScore.getScore`: the score in percent (`score`), the score of the previous period
  (`previous_score`, for a single period) and the basis (`clean_visits`, `checked_visits`, `campaign_visits`,
  `unchecked_visits`), `with_source_medium`, the clean share of each field (`score_name`, `score_source`,
  `score_medium`), the number of `findings` and the findings fixed since the previous period (`fixed_findings`). For several periods (as in the score over
  time) each period has the score and its basis, without `findings`: they are read for the score only, a few periods at
  a time.
- `FeinwerkCampaignScore.getFindings`: one row per finding, the most score points first, with `label` (the value or
  the spelling variants), `nb_visits`, `score_gain` (the score points the finding costs; all together add up to what the
  score misses), `type` (`variants`, `alias`, `format`, `unusual_medium`, `separator`, `pattern`, `incomplete`,
  `similar`), `field` (`name`, `keyword`, `source`, `medium`, `content`, `group`, `placement`), `suggestion`, `values`
  (the visits per spelling), `contexts` (where the values occur), `new` and `segment`. `findingType` filters by type
  (comma separated).
- `FeinwerkCampaignScore.getCorrections`: one row per value to fix with field, type, visits and suggestion.
- `FeinwerkCampaignScore.getConvention`: the clean values as a starting convention (format
  `feinwerk-campaign-convention`, version 1).
- `FeinwerkCampaignScore.getScoresForAllSites`: score, campaign visits and findings of every website you can view (at
  most 100, those with the most visits), for one day, week, month or year (`period`, `date`, `segment`); with
  `withPreviousPeriod=1` also `previous_score` and `score_change`.
- `FeinwerkCampaignScore.getRules`: the rules a website is checked by (`idSite` only): `separator`, `name_pattern` and
  `mediums`, each with its `value` and its `source` (`website`, `all_websites` or `default`), the `accepted_values` of the
  website, and whether `source_aliases` and `typo_hints` are on.
- `FeinwerkCampaignScore.acceptValues`: accepts values for a website (`idSite`, `values`, one per line); admins of the
  website only.
- `FeinwerkCampaignScore.removeAcceptedValues`: takes accepted values back (`idSite`, `values`, one per line); admins of
  the website only.
- `FeinwerkCampaignScore.addUsualMediums` and `removeUsualMediums`: add mediums to a website's usual mediums or take
  them back (`idSite`, `values`; stored in the clean spelling, Facebook Ads becomes facebook-ads); admins of the website
  only.
- `FeinwerkCampaignScore.addEmailReport`: sends the user the score and the findings by email (`idSite`, `period` day,
  week or month, default week), as a Matomo email report; once per user and website. A drop of 5 points or more leads
  the email.

Unless noted otherwise they take `idSite` (one website), `period` (`day`, `week`, `month`, `year` or `range`), `date`
(`YYYY-MM-DD`, `today`, `yesterday`, a range `YYYY-MM-DD,YYYY-MM-DD`, or `lastN`/`previousN` for several periods) and an
optional `segment`, and need view access to the website. Wrong input answers with HTTP 400 and a message that names the
parameter and the allowed values.

Examples (add `&token_auth=...` or call them from within Matomo):

    index.php?module=API&method=FeinwerkCampaignScore.getScore&idSite=1&period=month&date=yesterday&format=json
    index.php?module=API&method=FeinwerkCampaignScore.getScore&idSite=1&period=week&date=last12&format=json
    index.php?module=API&method=FeinwerkCampaignScore.getFindings&idSite=1&period=month&date=yesterday&format=json
    index.php?module=API&method=FeinwerkCampaignScore.getCorrections&idSite=1&period=month&date=yesterday&format=csv
    index.php?module=API&method=FeinwerkCampaignScore.getConvention&idSite=1&period=month&date=yesterday&format=json
    index.php?module=API&method=FeinwerkCampaignScore.getScoresForAllSites&period=month&date=yesterday&format=json
    index.php?module=API&method=FeinwerkCampaignScore.getRules&idSite=1&format=json

`acceptValues`, `removeAcceptedValues`, `addUsualMediums`, `removeUsualMediums` and `addEmailReport` take POST only, for example
`module=API&method=FeinwerkCampaignScore.acceptValues&idSite=1&values=e-mail&dryRun=1` in the request body. With
`dryRun=1` (or `true`) they save nothing and only report what would change; `dryRun` takes only `1`, `0`, `true` or
`false`. They answer with the number of accepted values of the
website afterwards (`accepted`) and the values that changed: `added` and `already` (accepted before), or `removed` and
`not_found` (not accepted).

## Privacy features

- Campaign Score reads only Matomo's archived reports through Matomo's own API. It never reads the raw visit data, and
  it sees no visitor, IP address or user ID.
- It stores only its settings and the values admins accept, in Matomo's settings tables. It has no tables of its own.
- Campaign values that Matomo masks (privacy setting for campaign parameters) stay masked: they are counted but never
  shown or checked.
- The correction list and the naming convention export are files you download. They hold campaign values and visit
  counts, nothing about visitors, and are not sent anywhere.
- Nothing leaves your Matomo except the optional feedback vote described below, sent by your Matomo server.
- Notes on other Feinwerk offers are part of the plugin, load nothing from outside and are shown only to admins and
  super users; see "Tips from Feinwerk" for how to switch them off.

## Feedback and support

Below the findings and in the dashboard widget you can tell us whether the report helps you (thumbs up or down). Your
Matomo server sends the answer to Feinwerk together with the plugin and Matomo version, the browser family and version
and the language of Matomo, nothing about you, your website or your visitors. To switch it off, add to
`config/config.ini.php`:

    [FeinwerkCampaignScore]
    feedback = 0

With `enable_internet_features = 0` in Matomo's `[General]` section nothing is sent either, and the question is not
shown. "Support" opens the Feinwerk contact form in a new tab.

## Tips from Feinwerk

The plugin shows short tips on other Feinwerk offers, for example Feinwerk Campaigns or Matomo hosting. Only users with
admin access to at least one website and super users see them, and only in Matomo's interface: never in exports, API
results, email reports or embedded widgets. You see at most one tip per place and page:

- beside the score card on the report page, a short guide to Feinwerk Campaigns (why, export the convention, upload
  it, build clean links). Its "×" empties the place for 30 days, and the score card takes the whole width; after that
  the paid Feinwerk plugins this Matomo does not have yet follow in the same place, one at a time, and each "×" again
  empties the place for 30 days. Super users open them in the Feinwerk Plugins screen of their Matomo
  (Feinwerk Marketplace Connector 5.5.0 or newer); admins, and everyone without the connector, get the plugin's page on
  plugins.feinwerk.io in a new tab. Without the connector the plugin uses a list it ships itself (no prices, nothing
  loaded),
- one link to Feinwerk Campaigns under the findings of Campaign Score,
- one small card in the dashboard widget Campaign Score,
- one banner on the start page of Matomo's administration,
- one box at the end of the Campaigns, Ecommerce overview and Goals overview reports.

A tip for a Feinwerk plugin you already have installed is not shown. "×" hides a tip for 30 days in your browser. The
tips are part of the plugin: nothing is loaded from outside and nothing is sent. Links open feinwerk.io pages in a new
tab and carry only the campaign parameters `mtm_campaign`, `mtm_source` and `mtm_content`.

To switch the tips off for this plugin, add to `config/config.ini.php`:

    [FeinwerkCampaignScore]
    promotions = 0

The same line in the section `[FeinwerkMarketplaceConnector]` switches them off for all Feinwerk plugins. Recent
versions of the Feinwerk Marketplace Connector (on the Matomo Marketplace) also have the setting "Show notes on other
Feinwerk offers".

In Matomo for WordPress the plugin recommends no paid Feinwerk plugins: they cannot be installed there yet.

### Installing the Feinwerk Connector

While the free Feinwerk Marketplace Connector is not active, super users find **Administration → Feinwerk Plugins →
Install Feinwerk Connector**. One click installs and activates it from Matomo's Marketplace, after Matomo's usual
password check. Where Matomo cannot install plugins itself (for example without internet access or with plugin
installs switched off), the page explains the manual way; in Matomo for WordPress it leads to the Marketplace in
WordPress. This entry is not a tip, so `promotions = 0` leaves it in place. To remove it for all Feinwerk plugins, add
to `config/config.ini.php`:

    [FeinwerkMarketplaceConnector]
    connector_installer = 0

The same line in `[FeinwerkCampaignScore]` removes only Campaign Score's entry; another Feinwerk plugin may then show it.

## License

GPL v3 or later, see `LICENSE`.

## Author

Peter Boehlke, [Feinwerk.io](https://feinwerk.io) · support@feinwerk.io
