<?php
/**
 * Plugin Name: Creative Projects Monthly Summary
 * Description: On the 1st of each month, publishes a post counting how many posts
 *              from the prior month were tagged "Creative Projects", shows total posts,
 *              percentage, and an inline bar chart of tagged vs total posts by month.
 * Version:     1.3
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

// How many months of history to show in the chart (including current month).
define( 'CPS_CHART_MONTHS', 12 );

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
// HELPERS — count posts for a given month
// ---------------------------------------------------------------------------

/**
 * Count all published posts in a given month.
 */
function cps_count_all_posts( DateTimeImmutable $first, DateTimeImmutable $last ) {
    $q = new WP_Query( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'date_query'     => array(
            array(
                'after'     => $first->format( 'Y-m-d H:i:s' ),
                'before'    => $last->format( 'Y-m-d H:i:s' ),
                'inclusive' => true,
                'column'    => 'post_date',
            ),
        ),
    ) );
    return (int) $q->found_posts;
}

/**
 * Count published posts with a specific tag slug in a given month.
 */
function cps_count_tagged_posts( $slug, DateTimeImmutable $first, DateTimeImmutable $last ) {
    $q = new WP_Query( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tag'            => $slug,
        'date_query'     => array(
            array(
                'after'     => $first->format( 'Y-m-d H:i:s' ),
                'before'    => $last->format( 'Y-m-d H:i:s' ),
                'inclusive' => true,
                'column'    => 'post_date',
            ),
        ),
    ) );
    return (int) $q->found_posts;
}

/**
 * Build chart history data: last N months ending with $target_first.
 * Returns array of [ 'label' => 'Jan 26', 'tagged' => N, 'total' => N ]
 */
function cps_build_chart_data( $slug, DateTimeImmutable $target_first, $num_months ) {
    $tz   = wp_timezone();
    $data = array();

    for ( $i = $num_months - 1; $i >= 0; $i-- ) {
        $first = $target_first->modify( "-$i months" );
        $last  = $first->modify( 'last day of this month' )->setTime( 23, 59, 59 );

        $data[] = array(
            'label'  => $first->format( 'M y' ),
            'tagged' => cps_count_tagged_posts( $slug, $first, $last ),
            'total'  => cps_count_all_posts( $first, $last ),
        );
    }

    return $data;
}

// ---------------------------------------------------------------------------
// CHART HTML — self-contained inline SVG bar chart (no external dependencies)
// ---------------------------------------------------------------------------

function cps_render_chart( array $chart_data, $tag_label ) {
    $num      = count( $chart_data );
    $w        = 680;
    $h        = 260;
    $pad_l    = 40;
    $pad_r    = 20;
    $pad_t    = 20;
    $pad_b    = 50;
    $chart_w  = $w - $pad_l - $pad_r;
    $chart_h  = $h - $pad_t - $pad_b;

    $max_total = max( array_column( $chart_data, 'total' ) );
    $max_val   = max( $max_total, 1 );

    $group_w  = $chart_w / $num;
    $bar_w    = max( 6, floor( $group_w * 0.32 ) );
    $gap      = max( 2, floor( $group_w * 0.06 ) );

    // Y-axis gridlines — 4 lines
    $grid_lines = '';
    $y_labels   = '';
    for ( $g = 0; $g <= 4; $g++ ) {
        $val   = round( $max_val * $g / 4 );
        $y_pos = $pad_t + $chart_h - ( $chart_h * $g / 4 );
        $grid_lines .= sprintf(
            '<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="#e0e0e0" stroke-width="1"/>',
            $pad_l, $y_pos, $w - $pad_r, $y_pos
        );
        $y_labels .= sprintf(
            '<text x="%d" y="%.1f" text-anchor="end" font-size="10" fill="#999" font-family="Georgia,serif">%d</text>',
            $pad_l - 4, $y_pos + 4, $val
        );
    }

    // Bars and x-axis labels
    $bars   = '';
    $labels = '';
    foreach ( $chart_data as $i => $d ) {
        $cx         = $pad_l + $group_w * $i + $group_w / 2;
        $x_total    = $cx - $bar_w - $gap / 2;
        $x_tagged   = $cx + $gap / 2;

        $h_total  = $d['total']  > 0 ? ( $chart_h * $d['total']  / $max_val ) : 0;
        $h_tagged = $d['tagged'] > 0 ? ( $chart_h * $d['tagged'] / $max_val ) : 0;

        $y_total  = $pad_t + $chart_h - $h_total;
        $y_tagged = $pad_t + $chart_h - $h_tagged;

        // Total bar (muted blue-grey)
        $bars .= sprintf(
            '<rect x="%.1f" y="%.1f" width="%d" height="%.1f" fill="#b0bec5" rx="2">
                <title>%s: %d total posts</title>
            </rect>',
            $x_total, $y_total, $bar_w, $h_total,
            esc_attr( $d['label'] ), $d['total']
        );

        // Tagged bar (warm accent)
        $bars .= sprintf(
            '<rect x="%.1f" y="%.1f" width="%d" height="%.1f" fill="#e07b39" rx="2">
                <title>%s: %d tagged posts</title>
            </rect>',
            $x_tagged, $y_tagged, $bar_w, $h_tagged,
            esc_attr( $d['label'] ), $d['tagged']
        );

        // X label
        $labels .= sprintf(
            '<text x="%.1f" y="%d" text-anchor="middle" font-size="10" fill="#666" font-family="Georgia,serif">%s</text>',
            $cx, $h - $pad_b + 14, esc_html( $d['label'] )
        );
    }

    // Legend
    $legend_y   = $h - 10;
    $legend_x1  = $pad_l;
    $legend_x2  = $pad_l + 100;
    $tag_escaped = esc_html( $tag_label );

    $legend = '
        <rect x="' . $legend_x1 . '" y="' . ( $legend_y - 8 ) . '" width="12" height="12" fill="#b0bec5" rx="2"/>
        <text x="' . ( $legend_x1 + 16 ) . '" y="' . $legend_y . '" font-size="11" fill="#555" font-family="Georgia,serif">Total posts</text>
        <rect x="' . $legend_x2 . '" y="' . ( $legend_y - 8 ) . '" width="12" height="12" fill="#e07b39" rx="2"/>
        <text x="' . ( $legend_x2 + 16 ) . '" y="' . $legend_y . '" font-size="11" fill="#555" font-family="Georgia,serif">' . $tag_escaped . '</text>
    ';

    return sprintf(
        '<figure style="margin:2em 0;">
            <svg viewBox="0 0 %d %d" xmlns="http://www.w3.org/2000/svg"
                 style="width:100%%;max-width:%dpx;height:auto;display:block;font-family:Georgia,serif;">
                %s
                %s
                %s
                %s
                %s
                <line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#ccc" stroke-width="1"/>
            </svg>
        </figure>',
        $w, $h, $w,
        $grid_lines,
        $y_labels,
        $bars,
        $labels,
        $legend,
        $pad_l, $pad_t + $chart_h, $w - $pad_r, $pad_t + $chart_h  // x-axis baseline
    );
}

// ---------------------------------------------------------------------------
// CORE FUNCTION — count posts and publish the summary
// ---------------------------------------------------------------------------

/**
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

    // Resolve tag.
    $slug    = $tag_slug ? sanitize_title( $tag_slug ) : CPS_TAG_SLUG;
    $tag_obj = get_term_by( 'slug', $slug, 'post_tag' );
    $tag_label = $tag_obj ? $tag_obj->name : $slug;

    // Count tagged and total posts for the target month.
    $tagged_count = cps_count_tagged_posts( $slug, $first_of_target, $last_of_target );
    $total_count  = cps_count_all_posts( $first_of_target, $last_of_target );
    $percentage   = $total_count > 0 ? round( ( $tagged_count / $total_count ) * 100, 1 ) : 0;

    // Build chart data across the last N months.
    $chart_data = cps_build_chart_data( $slug, $first_of_target, CPS_CHART_MONTHS );
    $chart_html = cps_render_chart( $chart_data, $tag_label );

    // Compose post content.
    $title = $tag_label . ' — ' . $month_label;

    $content  = sprintf(
        '<p>Posted %d creative project post%s during the %d days in %s.</p>',
        $tagged_count,
        $tagged_count === 1 ? '' : 's',
        $days_in_month,
        esc_html( $month_label )
    );
    $content .= sprintf(
        '<p>%d of %d total post%s this month %s tagged <strong>%s</strong> — <strong>%s%%</strong> of all posts.</p>',
        $tagged_count,
        $total_count,
        $total_count === 1 ? '' : 's',
        $tagged_count === 1 ? 'was' : 'were',
        esc_html( $tag_label ),
        $percentage
    );
    $content .= "\n\n" . $chart_html;

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
// ADMIN MENU — top-level menu item
// ---------------------------------------------------------------------------

add_action( 'admin_menu', 'cps_add_admin_page', 5 );

function cps_add_admin_page() {
    add_menu_page(
        'CP Summary',
        'CP Summary',
        'edit_posts',
        'cps-manual-run',
        'cps_admin_page_html',
        'dashicons-list-view',
        30
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