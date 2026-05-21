# Creative Projects Monthly Summary

A WordPress plugin that automatically publishes a monthly post counting how many posts from the prior month were tagged **Creative Projects**, along with total post counts, a percentage, and an inline bar chart.

## What It Does

On the 1st of each month, the plugin creates a new published post containing:

1. **Tagged post count** — *"Posted 7 creative project posts during the 30 days in April 2026."*
2. **Total + percentage** — *"7 of 23 total posts this month were tagged Creative Projects — 30.4% of all posts."*
3. **Inline bar chart** — a pure SVG chart showing the last 12 months of total posts (grey) vs tagged posts (orange), embedded directly in the post with no external dependencies.

## Installation

1. Create a folder named `creative-projects-summary` inside `/wp-content/plugins/`
2. Place `creative-projects-summary.php` inside that folder
3. Go to **WP Admin → Plugins** and activate **Creative Projects Monthly Summary**

A **CP Summary** item will appear in the left admin sidebar.

## Configuration

Open the `.php` file and update these constants near the top before activating:

| Constant | Description |
|---|---|
| `CPS_AUTHOR_ID` | Your WordPress user ID. Find it at WP Admin → Users → hover your username and look for `user_id=N` in the URL. |
| `CPS_TAG_SLUG` | The tag **slug** to count by default. WordPress slugs are lowercase with hyphens — e.g. `creative-projects`. Find slugs at WP Admin → Posts → Tags. |
| `CPS_CHART_MONTHS` | How many months of history to show in the chart. Default: `12`. |

## Manual Run

Go to **WP Admin → CP Summary** to generate a summary post on demand for any tag and any month. Use this to backfill past months or test a different tag without waiting for the scheduled run.

- **Tag slug** — pre-filled with your default from `CPS_TAG_SLUG`
- **Month / Year** — pick any month going back 5 years

After generating, a link to edit or view the new post appears immediately on the page.

## The Chart

The bar chart is rendered as a pure SVG element embedded in the post content — no JavaScript, no external libraries, no CDN dependencies. It works on any theme and won't conflict with security plugins. Hovering a bar shows a tooltip with the exact count. The chart always covers the last `CPS_CHART_MONTHS` months relative to the post's target month, so backfilled historical posts show the correct window for their period.

## Confirming Scheduled Runs

Once activated, a blue notice appears at the top of every WP Admin page showing the date and time of the next scheduled automatic post.

## Notes

- **Tag slugs, not display names** — the plugin queries by slug (e.g. `creative-projects`), which is case-insensitive and matches regardless of how the tag display name is capitalised.
- **WP-Cron reliability** — WP-Cron fires when someone visits the site. If your site has very low traffic around the 1st of the month, consider setting up a real server cron job to trigger WP-Cron reliably.
- **Menu access** — the CP Summary menu item is visible to any user with the `edit_posts` capability (Editor role and above).