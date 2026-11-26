# Changelog

## 5.9.0

- Campaign names made only of digits, such as "12345" or an ad platform's campaign ID, are a finding ("Number only") and lower the score. Switch it off in the settings for all websites if your team names campaigns by ID on purpose.
- API: the finding type `numeric` (also as `findingType`), and `getRules` reports `numeric_names`.

## 5.8.0

- Every finding shows how many score points it costs, and the findings are sorted by them: fix the top ones first. All points together add up to what the score misses.
- The score card names the result in words (good, needs work, poor), shows where the missing points are (campaign name, source and medium, in points with their findings), and counts the findings fixed since the previous period.
- Filter the findings by type with one click above the table.
- Spelling variants show as small labels with their visits, the clean spelling marked.
- Rows without a suggestion say what to do instead of showing "-".
- "Mark as correct" replaces "Accept value", and an unusual medium that is right for your website goes into its usual mediums with one click ("Add to usual mediums", new website setting "Additional usual mediums").
- One click in the score card sends you the Campaign Score every week by email. When the score falls by 5 points or more, the email opens with that news and marks the new findings.
- The overview of all websites can compare each website with the previous period.
- The setting "Usual mediums" lists the whole default list below the field.
- The small score trend shows each period as a dot; the dashboard widget lists the findings worth the most points, with their points and what fixing them brings.
- On phones the findings table shows the value with its fix below it and the points, without sideways scrolling.
- API: `getFindings` has `score_gain` and the filter `findingType`; `getScore` has `previous_score`, `score_name`, `score_source`, `score_medium` and `fixed_findings`; new `addUsualMediums`, `removeUsualMediums` and `addEmailReport`; `getScoresForAllSites` takes `withPreviousPeriod=1`.
- On narrow screens the tip under a report shows a shorter link, so it no longer makes the page wider than the screen.
- The tip about campaigns now offers the free plugin Feinwerk Campaign Manager; where it is installed, the tip leads to its campaign overview, and once it is connected, the tip no longer shows.
- The label of the "Install and activate" button of the Feinwerk Connector page sits centred.

## 5.7.0

- Right under Matomo's Campaign Names report a line shows the Campaign Score of the period with its number of findings and a link to them (no link when there are none).
- Admins accept a value with a visible "Accept value" button in rows without a suggestion; the hover action stays.
- The 30-day pause of the place beside the score card now comes from the shared notes of all Feinwerk plugins; a place closed with 5.6.0 stays empty for the rest of its 30 days.
- API error messages show the value given without quotes, so they read plainly in every output format.
- API: `getRules` shows the rules a website is checked by and whether each comes from the website, the settings for all websites or the default; `removeAcceptedValues` takes accepted values back; `acceptValues` and `removeAcceptedValues` offer `dryRun=1` and report which values changed. A wrong `idSite` or `dryRun` answers with a message that names the parameter.
- The links of the score card and of the overview of all websites keep the selected segment.
- The correction list is also a report in Matomo's report list ("Campaign value corrections"), so it can be sent as a scheduled email report and found by API clients.
- The report help explains that a value shown as "(not set)" is missing in the campaign link, and that there is no score without campaign visits that can be checked.
- The small score trend in the score card keeps every period in its place; periods without a score show as a faint dotted line.
- Without campaign visits in a period, the score card links to the last period with campaign visits, or else to the whole month or year, so one click leads to a score.
- The accept dialog shows the value in quotes, and the keyboard focus returns to the row when it closes.
- On phones the picture of the guide to Feinwerk Campaigns sits above its text.
- The score card and the guide keep the same space to the next report as Matomo's own reports, and the overview of all websites lines up with its title.
- Widgets embedded in other pages no longer show the link "Show all findings".
- The help of the setting "Usual mediums" matches the pre-filled list: empty the field and save to go back to the default list.
- The notes and the connector installer work together with the newest copies in other Feinwerk plugins.

## 5.6.0

- The findings table stays readable on tablets and phones: values and where they occur wrap at word boundaries, and every column starts at the top.
- "Campaign Score of all websites" no longer makes the page scroll sideways on phones; its headers wrap and the table scrolls within its box.
- Dashboard widget: each of the largest problems shows the value and its visits on the first line and the problem below, also in a narrow dashboard column.
- Without campaign visits in the period the findings say so instead of "No problems found" (and where campaign visits could not be checked, they say that and why), the score card names the next step, and the guide to Feinwerk Campaigns shows no steps for a file that cannot be exported.
- Accepting a value uses Matomo's own dialog, which follows dark mode, and an error is shown with its reason in every language.
- Better contrast for the amber score and the first step of the guide.
- The findings table shows the fix right beside the value: Value, Suggestion and Visits, with the problem and the field as a small line under the value; exports and the API keep every column. French puts its space before the colon.
- Dashboard widget: each of the largest problems also shows its suggestion.
- Accepting a value asks a short question with the explanation below it.
- Beside the score card, "×" now empties the place for 30 days; only then the next note follows there.
- API: every method describes its parameters with their allowed values, and wrong input gets a message that names the parameter, the value given and what is allowed (HTTP 400); the README has an example call for each method.
- "Install Feinwerk Connector": the button shows "Installing the connector…" and takes no second click, the page speaks the language of Matomo, connector files that do not fit this Matomo are installed again instead of activated, and "Feinwerk Plugins" in the administration menu has its icon.
- The tip on Matomo's administration home now sits below Matomo's own notices, and the small Feinwerk Campaigns card in the dashboard widget wraps in a narrow column; every "×" of the notes is easier to hit and keeps the keyboard focus on the page.

## 5.5.1

- "Install Feinwerk Connector" under Administration › Feinwerk Plugins: where plugin administration is switched off in Matomo and the connector's files are already there, the page now shows the command that activates it.

## 5.5.0

- New: super users who do not have the free Feinwerk Marketplace Connector active find "Install Feinwerk Connector" under Administration › Feinwerk Plugins. One click installs and activates it from Matomo's Marketplace, after Matomo's usual password check. Where Matomo cannot install plugins itself, the page explains the manual way; inside Matomo for WordPress it leads to the Marketplace in WordPress. `connector_installer = 0` in the section `[FeinwerkMarketplaceConnector]` of config.ini.php removes the entry for all Feinwerk plugins; in `[FeinwerkCampaignScore]` it removes only Campaign Score's own.
- Matomo for WordPress: the info box on Marketing Campaigns Reporting now leads super users to the Marketplace in WordPress instead of Matomo pages that are switched off there, and no paid Feinwerk plugins are recommended there.
- Notes on Feinwerk offers no longer get in each other's way when several Feinwerk plugins show them on the same page.
- Campaign Score is now also available in French, Spanish and Italian.

## 5.4.0

- Demo mode for test instances: `promotions_demo = 1` in the section `[FeinwerkMarketplaceConnector]` shows every note and every paid plugin, also the installed ones, and "×" hides them for the browser session only. Every off switch still wins.
- After the guide beside the score card is hidden, admins now also see the paid Feinwerk plugins they do not have yet, with a link to the plugin's page on plugins.feinwerk.io and a line that their super user can install it; super users keep the link to the Feinwerk Plugins screen. With the Feinwerk Marketplace Connector 5.5.0 or newer the list comes from the store; without the connector the plugin shows them from a list it ships itself.
- The notes on Feinwerk offers (the guide beside the score card, the banner on the administration start page and the boxes on Matomo's reports) show the Feinwerk Plugins logo.

## 5.3.1

- Wrong input to the plugin's API now answers with HTTP 400 on Matomo 5.0 as well (it answered 200 there with the error message).
- Campaign Score over time shows no point for a period without campaign visits instead of 0 %; the line runs from one period with campaign visits to the next.

## 5.3.0

- For admins, a short guide to Feinwerk Campaigns now stands beside the score card: why the score misses points, then export the naming convention, upload it to Feinwerk Campaigns and build clean links. The score card is larger and centred, with the correction list under its text. "×" hides the guide for 30 days; super users then see the paid Feinwerk plugins they do not have yet in the same place, one at a time (with a recent Feinwerk Marketplace Connector).
- Under the findings, a link with the mark of Feinwerk Campaigns replaces the tip line.
- Without campaign visits in the period the score card offers no downloads.
- The scripts and styles of the notes are the same as in the other Feinwerk plugins, so they no longer interfere with each other on one page.

## 5.2.0

- Without the free plugin Marketing Campaigns Reporting, an info box above the report explains how to get the checks of source, medium, content, group and placement (on the dashboard widget the short note stays).
- Wrong input to the plugin's API (for example a date range for the overview of all websites) now answers with HTTP 400 instead of a server error.
- The note on Matomo's Administration home page now sits at the top of the page instead of at its end.
- For admins, the dashboard widget shows a small card on Feinwerk Campaigns below its link; its × hides only the card for 30 days, and `promotions = 0` switches it off like the other notes.

## 5.1.1

- Fixed: on Matomo instances with many large websites, Campaign Score of all websites could fail to load; it now loads there too.
- Campaign Score of all websites on large instances: with more than ten websites it loads on a click, it shows the 100 websites with the most visits (before: the first 100 by ID), reads them ten at a time and keeps the result for 15 minutes; for a date range it asks for a day, week, month or year.
- Campaign Score of all websites reads Matomo's campaign report without its keywords where Marketing Campaigns Reporting has the values, and a very large website on its own.
- The score over time and the small line on the score card read a few periods at a time and only the reports the score needs, with far less memory on large websites (API: for several periods the score has no findings count).
- Next to "Export as naming convention for Feinwerk Campaigns" admins find a link to the page of Feinwerk Campaigns that takes the file.
- The tip below the report keeps its hide control right after its link and no longer wraps next to older Feinwerk plugins.

## 5.1.0

- New: below each finding you see where the value occurs (campaign name, source and medium), and a click opens Matomo's visitor log with exactly these visits.
- New: findings that were not there in the previous period are marked "new".
- New: the score card shows the change to the previous period and a small line with the last eight periods; the report "Campaign Score over time" shows the score as a graph and can be used in scheduled email reports and alerts.
- New: admins can accept a value directly in the findings table; it then counts as clean for the website.
- New: settings per website (accepted values, usual mediums, separator, pattern for campaign names) on top of the settings for all websites.
- New checks: source aliases such as "fb" for facebook, the separator rule (hyphen or underscore, and values that mix both), an optional pattern for campaign names, hints on possible typos (they do not lower the score), and with Marketing Campaigns Reporting also campaign content, group and placement.
- New: "Campaign Score of all websites", a table with the score of every website for users with access to more than one website.
- New: download the correction list as CSV, and export the clean values as a starting naming convention for Feinwerk Campaigns.
- The feedback question and the tip below the report take one low line each and never wrap.

## 5.0.0

- Versions: Matomo 5: version 5.x of this plugin, Matomo 6: version 6.x.
- First version: Acquisition > Campaign Score shows how clean your campaign values are, as one score with its basis ("650 of 800 campaign visits with clean values").
- Findings with a suggestion for each value: spelling variants, values with capital letters, spaces or other characters, unusual mediums and missing campaign names, sources or mediums.
- Checks campaign name and keyword with Matomo's campaign report, and also source and medium with the free plugin Marketing Campaigns Reporting.
- List of usual mediums in the general settings, by default the mediums that Feinwerk Channel Grouping assigns to a channel.
- Dashboard widget with the score and the three largest problems.
- Works in light and dark mode, German and English.
