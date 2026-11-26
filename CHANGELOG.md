# Changelog

## 6.3.0

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

## 6.2.0

- Right under Matomo's Campaign Names report a line shows the Campaign Score of the period with its number of findings and a link to them (no link when there are none).
- Admins accept a value with a visible "Accept value" button in rows without a suggestion; the hover action stays.
- The 30-day pause of the place beside the score card now comes from the shared notes of all Feinwerk plugins; a place closed with 6.1.0 stays empty for the rest of its 30 days.
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

## 6.1.0

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

## 6.0.0

The first stable version for Matomo 6. It brings together everything of the 6.0.0 betas:

- Acquisition › Campaign Score shows how clean your campaign values are, as one score with its basis ("650 of 800 campaign visits with clean values"), the change to the previous period and a small line with the last periods. "Campaign Score over time" shows the score as a graph, with no point for a period without campaign visits, and can be used in scheduled email reports and alerts.
- Findings with a suggestion for each value: spelling variants, source aliases such as "fb" for facebook, values with capital letters, spaces or other characters, the separator rule (hyphen or underscore), an optional pattern for campaign names, unusual mediums, missing campaign names, sources or mediums, and hints on possible typos. Each finding shows where the value occurs (campaign name, source and medium), marks findings that are new against the previous period and opens Matomo's visitor log with exactly these visits.
- Checks campaign name and keyword with Matomo's campaign report, and with the free plugin Marketing Campaigns Reporting also source, medium, content, group and placement. Without it, an info box above the report explains how to get these checks.
- Admins can accept a value directly in the findings table; it then counts as clean for the website. Settings for all websites and per website: accepted values, usual mediums, separator, pattern for campaign names, source aliases and typo hints.
- "Campaign Score of all websites" lists the score of every website for users with access to more than one; on large instances it loads on a click and shows the 100 websites with the most visits.
- Download the correction list as CSV, and export the clean values as a starting naming convention for Feinwerk Campaigns.
- Dashboard widgets: "Campaign Score" with the score and the three largest problems, and "Campaign Score over time".
- For admins, a short guide to Feinwerk Campaigns stands beside the score card, and a link under the findings; after the guide is hidden, the paid Feinwerk plugins this Matomo does not have yet follow in the same place. Only admins and super users see these notes, never in exports or embedded widgets, and `promotions = 0` switches them off.
- Super users who do not have the free Feinwerk Marketplace Connector active install and activate it with one click under Administration › Feinwerk Plugins, after Matomo's password check. Where Matomo cannot install plugins itself, the page explains the manual way; where plugin administration is switched off and the files are there, it shows the command that activates the connector. `connector_installer = 0` in the section `[FeinwerkMarketplaceConnector]` removes the entry for all Feinwerk plugins.
- Matomo for WordPress: the info box on Marketing Campaigns Reporting leads to the Marketplace in WordPress, and no paid Feinwerk plugins are recommended there.
- Wrong input to the plugin's API (for example a date range for the overview of all websites) answers with HTTP 400.
- Light and dark mode; English, German, French, Spanish and Italian.
- Versions: the plugin for Matomo 6 carries version 6.x, the one for Matomo 5 version 5.x.
