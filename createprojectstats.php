<?php
/**
 * Plugin Name: Creative Projects Monthly Summary
 * Description: On the 1st of each month, publishes a post counting how many posts
 *              from the prior month were tagged "Creative Projects".
 * Version:     1.0
 * Author:      Bill Futreal
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// CONFIGURATION — edit these two values before activating
// ---------------------------------------------------------------------------

// The WordPress user ID that will appear as the post author.
// To find your ID: WP Admin → Users → hover your username → note the "user_id=N" in the URL.
define( 'CPS_AUTHOR_ID', 1 );

// The exact tag name as it appears in WordPress (case-sensitive).
define( 'CPS_TAG_NAME', 'creative projects' );

// ---------------------------------------------------------------------------
// SCHEDULE — register a monthly cron event on plugin activation
// ---------------------------------------------------------------------------

register_activation_hook( __FILE__, 'cps_activate' );
register_deactivation_hook( __FILE__, 'cps_deactivate' );

function cps_activate() {
    if ( ! wp_next_scheduled( 'cps_monthly_event' ) ) {
        // Schedule the first run at midnight on the 1st of next month (site timezone).
        $next_first = cps_next_first_of_month();
        wp_schedule_event( $next_first, 'cps_monthly', 'cps_monthly_event' );
    }
}

function cps_deactivate() {
    $timestamp = wp_next_scheduled( 'cps_monthly_event' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'cps_monthly_event' );
    }
}

// Register a custom "monthly" recurrence interval.
add_filter( 'cron_schedules', 'cps_add_monthly_schedule' );
function cps_add_monthly_schedule( $schedules ) {
    $schedules['cps_monthly'] = array(
        'interval' => 30 * DAY_IN_SECONDS, // approximate — the real anchor is the activation timestamp
        'display'  => __( 'Once a month (Creative Projects Summary)' ),
    );
    return $schedules;
}

// Hook the actual work to the cron event.
add_action( 'cps_monthly_event', 'cps_create_summary_post' );

// ---------------------------------------------------------------------------
// CORE FUNCTION — count posts and publish the summary
// ---------------------------------------------------------------------------

function cps_create_summary_post() {
    // Figure out the previous month's date range in the site's local timezone.
    $tz           = wp_timezone();
    $now          = new DateTimeImmutable( 'now', $tz );
    $first_of_last = $now->modify( 'first day of last month' )->setTime( 0, 0, 0 );
    $last_of_last  = $now->modify( 'last day of last month' )->setTime( 23, 59, 59 );

    $month_label = $first_of_last->format( 'F Y' ); // e.g. "April 2025"

    // Query posts tagged "Creative Projects" published in that month.
    $query = new WP_Query( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1,           // get all of them so we can count
        'fields'         => 'ids',        // only fetch IDs — lighter query
        'tag'            => CPS_TAG_NAME,
        'date_query'     => array(
            array(
                'after'     => $first_of_last->format( 'Y-m-d H:i:s' ),
                'before'    => $last_of_last->format( 'Y-m-d H:i:s' ),
                'inclusive' => true,
                'column'    => 'post_date',
            ),
        ),
    ) );

    $count = $query->found_posts;

    // Number of days in the previous month.
    $days_in_month = (int) $first_of_last->format( 't' );

    // Build a simple post title and body.
    $title   = 'Creative Projects — ' . $month_label;
    $content = sprintf(
        '<p>Posted %d creative project post%s during the %d days in %s.</p>',
        $count,
        $count === 1 ? '' : 's',
        $days_in_month,
        esc_html( $month_label )
    );

    // Insert (publish) the summary post.
    $post_id = wp_insert_post( array(
        'post_title'   => $title,
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_author'  => CPS_AUTHOR_ID,
        'post_type'    => 'post',
        'post_date'    => current_time( 'mysql' ), // site local time
        'tags_input'   => array( 'Creative Projects Summary' ), // optional tag on the summary itself
    ), true );

    if ( is_wp_error( $post_id ) ) {
        error_log( 'Creative Projects Summary plugin: failed to insert post — ' . $post_id->get_error_message() );
    }
}

// ---------------------------------------------------------------------------
// HELPER — calculate Unix timestamp for midnight on the 1st of next month
// ---------------------------------------------------------------------------

function cps_next_first_of_month() {
    $tz         = wp_timezone();
    $next_first = new DateTimeImmutable( 'first day of next month midnight', $tz );
    return $next_first->getTimestamp();
}

// ---------------------------------------------------------------------------
// ADMIN NOTICE — confirm the next scheduled run so you know it's working
// ---------------------------------------------------------------------------

add_action( 'admin_notices', 'cps_admin_notice' );
function cps_admin_notice() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $next = wp_next_scheduled( 'cps_monthly_event' );
    if ( $next ) {
        $tz          = wp_timezone();
        $dt          = ( new DateTimeImmutable() )->setTimestamp( $next )->setTimezone( $tz );
        $formatted   = $dt->format( 'F j, Y \a\t g:i a T' );
        echo '<div class="notice notice-info is-dismissible"><p>'
            . '<strong>Creative Projects Summary:</strong> next post will be created on '
            . esc_html( $formatted ) . '.</p></div>';
    }
}