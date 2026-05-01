<?php
/**
 * Plugin Name: Creative Projects Monthly Summary
 * Description: On the 1st of each month, publishes a post counting how many posts
 *              from the prior month were tagged "Creative Projects". Also provides
 *              an admin page to re-run the summary for any tag and any month.
 * Version:     1.2
 * Author:      Bill Futreal
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// CONFIGURATION — edit these values before activating
// ---------------------------------------------------------------------------

// The WordPress user ID that will appear as the post author.
// To find your ID: WP Admin → Users → hover your username → note "user_id=N" in the URL.
define( 'CPS_AUTHOR_ID', 1 );

// The tag SLUG (not display name) to count by default.
// WordPress converts tag names to slugs: lowercase, spaces become hyphens.
// "Creative Projects" → slug is "creative-projects"
define( 'CPS_TAG_SLUG', 'creative-projects' );

// ---------------------------------------------------------------------------
// SCHEDULE — register a monthly cron event on plugin activation
// ---------------------------------------------------------------------------

register_activation_hook( __FILE__, 'cps_activate' );
register_deactivation_hook( __FILE__, 'cps_deactivate' );

function cps_activate() {
    if ( ! wp_next_scheduled( 'cps_monthly_event' ) ) {
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

add_filter( 'cron_schedules', 'cps_add_monthly_schedule' );
function cps_add_monthly_schedule( $schedules ) {
    $schedules['cps_monthly'] = array(
        'interval' => 30 * DAY_IN_SECONDS,
        'display'  => __( 'Once a month (Creative Projects Summary)' ),
    );
    return $schedules;
}

add_action( 'cps_monthly_event', 'cps_create_summary_post' );

// ---------------------------------------------------------------------------
// CORE FUNCTION — count posts and publish the summary
// ---------------------------------------------------------------------------

/**
 * Create a summary post.
 *
 * @param string|null $tag_slug  Tag slug to count. Defaults to CPS_TAG_SLUG.
 * @param int|null    $year      Year of the month to summarise. Defaults to previous month.
 * @param int|null    $month     Month number (1-12) to summarise. Defaults to previous month.
 */
function cps_create_summary_post( $tag_slug = null, $year = null, $month = null ) {
    $tz = wp_timezone();

    // Determine the target month.
    if ( $year && $month ) {
        $first_of_target = DateTimeImmutable::createFromFormat(
            'Y-n-j H:i:s', "$year-$month-1 00:00:00", $tz
        );
    } else {
        $now             = new DateTimeImmutable( 'now', $tz );
        $first_of_target = $now->modify( 'first day of last month' )->setTime( 0, 0, 0 );
    }

    $last_of_target = $first_of_target->modify( 'last day of this month' )->setTime( 23, 59, 59 );
    $month_label    = $first_of_target->format( 'F Y' );
    $days_in_month  = (int) $first_of_target->format( 't' );

    // Resolve the tag slug.
    $slug = $tag_slug ? sanitize_title( $tag_slug ) : CPS_TAG_SLUG;

    // Query by slug — works regardless of display-name capitalisation.
    $query = new WP_Query( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tag'            => $slug,
        'date_query'     => array(
            array(
                'after'     => $first_of_target->format( 'Y-m-d H:i:s' ),
                'before'    => $last_of_target->format( 'Y-m-d H:i:s' ),
                'inclusive' => true,
                'column'    => 'post_date',
            ),
        ),
    ) );

    $count = $query->found_posts;

    // Use the tag's display name if available.
    $tag_obj   = get_term_by( 'slug', $slug, 'post_tag' );
    $tag_label = $tag_obj ? $tag_obj->name : $slug;

    $title   = $tag_label . ' — ' . $month_label;
    $content = sprintf(
        '<p>Posted %d creative project post%s during the %d days in %s.</p>',
        $count,
        $count === 1 ? '' : 's',
        $days_in_month,
        esc_html( $month_label )
    );

    $post_id = wp_insert_post( array(
        'post_title'   => $title,
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_author'  => CPS_AUTHOR_ID,
        'post_type'    => 'post',
        'post_date'    => current_time( 'mysql' ),
        'tags_input'   => array( 'Creative Projects Summary' ),
    ), true );

    if ( is_wp_error( $post_id ) ) {
        error_log( 'Creative Projects Summary plugin: failed to insert post — ' . $post_id->get_error_message() );
        return false;
    }

    return $post_id;
}

// ---------------------------------------------------------------------------
// HELPER — next 1st-of-month timestamp
// ---------------------------------------------------------------------------

function cps_next_first_of_month() {
    $tz         = wp_timezone();
    $next_first = new DateTimeImmutable( 'first day of next month midnight', $tz );
    return $next_first->getTimestamp();
}

// ---------------------------------------------------------------------------
// ADMIN NOTICE — confirm next scheduled run
// ---------------------------------------------------------------------------

add_action( 'admin_notices', 'cps_admin_notice' );
function cps_admin_notice() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $next = wp_next_scheduled( 'cps_monthly_event' );
    if ( $next ) {
        $tz        = wp_timezone();
        $dt        = ( new DateTimeImmutable() )->setTimestamp( $next )->setTimezone( $tz );
        $formatted = $dt->format( 'F j, Y \a\t g:i a T' );
        echo '<div class="notice notice-info is-dismissible"><p>'
            . '<strong>Creative Projects Summary:</strong> next post will be created on '
            . esc_html( $formatted ) . '.</p></div>';
    }
}

// ---------------------------------------------------------------------------
// ADMIN MENU — top-level menu item (avoids submenu visibility issues)
// ---------------------------------------------------------------------------

// Use priority 5 to ensure it runs early, before some themes/plugins interfere.
add_action( 'admin_menu', 'cps_add_admin_page', 5 );

function cps_add_admin_page() {
    // Use 'edit_posts' instead of 'manage_options' — any editor or above can access.
    // Change back to 'manage_options' if you want to restrict to admins only.
    add_menu_page(
        'CP Summary',           // Page title
        'CP Summary',           // Menu label
        'edit_posts',           // Capability — editors and above
        'cps-manual-run',       // Menu slug
        'cps_admin_page_html',  // Callback
        'dashicons-list-view',  // Icon
        30                      // Position (after Comments)
    );
}

function cps_admin_page_html() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( 'You do not have permission to access this page.' );
    }

    $result_message = '';

    if (
        isset( $_POST['cps_run_nonce'] ) &&
        wp_verify_nonce( $_POST['cps_run_nonce'], 'cps_manual_run' )
    ) {
        $tag_slug = sanitize_title( $_POST['cps_tag_slug'] ?? CPS_TAG_SLUG );
        $year     = absint( $_POST['cps_year'] ?? 0 );
        $month    = absint( $_POST['cps_month'] ?? 0 );

        if ( $year < 2000 || $year > 2100 || $month < 1 || $month > 12 ) {
            $result_message = '<div class="notice notice-error inline"><p>Invalid year or month.</p></div>';
        } else {
            $post_id = cps_create_summary_post( $tag_slug, $year, $month );
            if ( $post_id ) {
                $edit_url       = get_edit_post_link( $post_id );
                $view_url       = get_permalink( $post_id );
                $result_message = '<div class="notice notice-success inline"><p>'
                    . 'Summary post created! '
                    . '<a href="' . esc_url( $edit_url ) . '">Edit post</a> | '
                    . '<a href="' . esc_url( $view_url ) . '" target="_blank">View post</a>'
                    . '</p></div>';
            } else {
                $result_message = '<div class="notice notice-error inline"><p>Failed to create post. Check error logs.</p></div>';
            }
        }
    }

    $current_year = (int) ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y' );
    $year_options = '';
    for ( $y = $current_year; $y >= $current_year - 5; $y-- ) {
        $year_options .= '<option value="' . $y . '">' . $y . '</option>';
    }

    $month_names   = array( 1 => 'January', 'February', 'March', 'April', 'May', 'June',
                            'July', 'August', 'September', 'October', 'November', 'December' );
    $month_options = '';
    foreach ( $month_names as $num => $name ) {
        $month_options .= '<option value="' . $num . '">' . $name . '</option>';
    }

    $default_tag = esc_attr( CPS_TAG_SLUG );
    $nonce_field = wp_create_nonce( 'cps_manual_run' );

    echo '
    <div class="wrap">
        <h1>Creative Projects Summary &mdash; Manual Run</h1>
        <p>Use this form to generate a summary post for any tag and any past month.
           The tag field is pre-filled with your default from the plugin configuration.</p>

        ' . $result_message . '

        <form method="post" style="margin-top:1.5rem;">
            <input type="hidden" name="cps_run_nonce" value="' . $nonce_field . '" />
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="cps_tag_slug">Tag slug</label></th>
                    <td>
                        <input type="text" id="cps_tag_slug" name="cps_tag_slug"
                               value="' . $default_tag . '" class="regular-text" />
                        <p class="description">
                            Lowercase with hyphens &mdash; e.g. <code>creative-projects</code>.
                            Find slugs at WP Admin &rarr; Posts &rarr; Tags.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="cps_month">Month</label></th>
                    <td><select id="cps_month" name="cps_month">' . $month_options . '</select></td>
                </tr>
                <tr>
                    <th scope="row"><label for="cps_year">Year</label></th>
                    <td><select id="cps_year" name="cps_year">' . $year_options . '</select></td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">Generate Summary Post</button>
            </p>
        </form>
    </div>';
}