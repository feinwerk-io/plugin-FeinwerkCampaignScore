## FAQ

__How does Campaign Score handle capitalisation?__

Without Marketing Campaigns Reporting, Matomo writes campaign names and keywords in lowercase. Marketing Campaigns Reporting keeps the original capitalisation by default (its setting "Keep campaign parameter capitalisation"), also in Matomo's own campaign report, so Campaign Score can show values such as "Black Friday 2026" as not clean. Values that differ only in capitalisation ("Newsletter" and "newsletter") are usually grouped into one row per day by Matomo's database, under one of the spellings; Campaign Score then sees only that spelling, and finds such variants only where different days used different spellings. If you switch the setting of Marketing Campaigns Reporting off, it writes all values in lowercase from then on, and only variants such as "news_letter" and "newsletter" remain.

__What does "could not be checked" mean?__

For large accounts Matomo groups rare rows of its campaign reports as "Others" (by default after 1,000 rows, and after 50 campaign names per source and medium). The values in these rows are unknown, so their visits are left out of the basis of the score. The same applies to values masked by Matomo's privacy setting for campaign parameters, and to campaign visits from before Marketing Campaigns Reporting was activated: Matomo knows their campaign name, but not their source and medium.

__Why did the score change after I edited a setting or accepted a value?__

The score is computed when you open the report, so a change applies at once, also to past periods.

__Does Campaign Score work on large Matomo instances?__

It reads only Matomo's archived reports, never the raw visit data, and adds no archiving of its own. The table of all websites reads the reports of at most 100 websites, ten at a time, keeps the result for 15 minutes and, with more than ten websites, loads only when you click its button. For a date range it asks for a day, week, month or year, because Matomo would compute the range for every website. As everywhere in Matomo, reports load fastest when the archiving runs as a cron job (browser-triggered archiving switched off).

__Which source aliases does Campaign Score know?__

Only spellings that cannot mean anything else: for example fb, facebook.com and m.facebook.com (facebook), ig and instagram.com (instagram), adwords and google ads (google), linkedin.com and lnkd.in (linkedin), yt and youtube.com (youtube), bing ads and microsoft ads (bing). If your team uses one of them on purpose, accept it for the website or switch the aliases off.
