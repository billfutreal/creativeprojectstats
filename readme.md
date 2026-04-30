# Creative Projects Monthly Summary

A simple WordPress plugin that automatically publishes a monthly post counting how many posts from the prior month were tagged **Creative Projects**.

## What It Does

On the 1st of each month, the plugin creates a new published post in the format:

> Posted 7 creative project posts during the 30 days in April 2025.

## Installation

1. Create a folder named `creative-projects-summary` inside `/wp-content/plugins/`
2. Place `creative-projects-summary.php` inside that folder
3. Go to **WP Admin → Plugins** and activate **Creative Projects Monthly Summary**

## Configuration

Open the `.php` file and update these two constants near the top before activating:

| Constant | Description |
|---|---|
| `CPS_AUTHOR_ID` | Your WordPress user ID. Find it at WP Admin → Users → hover your username and look for `user_id=N` in the URL. |
| `CPS_TAG_NAME` | The exact tag name as it appears in WordPress. Case-sensitive. Default: `Creative Projects` |

## Confirming It's Working

Once activated, a blue notice will appear at the top of every WP Admin page showing the date and time of the next scheduled post.

## Notes

- The plugin uses WP-Cron, which fires when someone visits the site. If your site has very low traffic, consider setting up a real server cron job to trigger WP-Cron reliably.
- The summary post is itself tagged **Creative Projects Summary** for easy reference.