# Changelog

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
