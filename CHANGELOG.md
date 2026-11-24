# Changelog

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
