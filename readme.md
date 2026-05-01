# Creative Projects Monthly Summary

A simple WordPress plugin that automatically publishes a monthly post counting how many posts from the prior month were tagged **Creative Projects**.

## What It Does

On the 1st of each month, the plugin creates a new published post in the format:

> Posted 7 creative project posts during the 30 days in April 2026.

## Installation

1. Create a folder named `creative-projects-summary` inside `/wp-content/plugins/`
2. Place `creative-projects-summary.php` inside that folder
3. Go to **WP Admin → Plugins** and activate **Creative Projects Monthly Summary**

A **CP Summary** item will appear in the left admin sidebar.

## Configuration

Open the `.php` file and update these two constants near the top before activating:

| Constant | Description |
|---|---|
| `CPS_AUTHOR_ID` | Your WordPress user ID. Find it at WP Admin → Users → hover your username and look for `user_id=N` in the URL. |
| `CPS_TAG_SLUG` | The tag **slug** (not display name) to count by default. WordPress slugs are lowercase with hyphens — e.g. `creative-projects`. Find slugs at WP Admin → Posts → Tags. |

## Manual Run

Go to **WP Admin → CP Summary** to generate a summary post on demand for any tag and any month. Use this to backfill past months or test a different tag without waiting for the scheduled run.

- **Tag slug** — pre-filled with your default from `CPS_TAG_SLUG`
- **Month / Year** — pick any month going back 5 years

After generating, a link to edit or view the new post appears immediately on the page.

## Confirming Scheduled Runs

Once activated, a blue notice will appear at the top of every WP Admin page showing the date and time of the next scheduled automatic post.

## Notes

- **Tag slugs, not display names** — the plugin queries by slug (e.g. `creative-projects`), which is case-insensitive and matches regardless of how the tag display name is capitalised.
- **WP-Cron reliability** — WP-Cron fires when someone visits the site. If your site has very low traffic around the 1st of the month, consider setting up a real server cron job to trigger WP-Cron reliably.
- **Menu access** — the CP Summary menu item is visible to any user with the `edit_posts` capability (Editor role and above).