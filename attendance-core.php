<?php
/**
 * Plugin Name: Employee Attendance Manager
 * Description: Employee attendance tracking with clock-in/out, photo capture, notes, calendars, and administrator reports.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Tested up to: 7.0
 * Requires PHP: 8.1
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author: Voxpher
 * Text Domain: employee-attendance-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'EAM_PLUGIN_VERSION', '1.0.0' );
define( 'EAM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EAM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EAM_DB_VERSION', '1.0.0' );

/**
 * Activation: create DB tables.
 */
function eam_activate_plugin() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . 'eam_attendance';
    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        att_date DATE NOT NULL,
        check_in DATETIME NULL,
        check_out DATETIME NULL,
        total_seconds INT(11) DEFAULT 0,
        check_in_note TEXT NULL,
        check_out_note TEXT NULL,
        check_in_photo VARCHAR(255) NULL,
        check_out_photo VARCHAR(255) NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY user_date (user_id, att_date)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    update_option( 'eam_db_version', EAM_DB_VERSION );
}
register_activation_hook( __FILE__, 'eam_activate_plugin' );

/**
 * Check whether a string is a valid Y-m-d date.
 *
 * @param string $date Date string.
 * @return bool
 */
function eam_is_valid_date( $date ) {
    if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        return false;
    }

    $parts = explode( '-', $date );
    return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
}

/**
 * Sanitize an input date field.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function eam_sanitize_date( $value ) {
    $date = sanitize_text_field( $value );
    return eam_is_valid_date( $date ) ? $date : '';
}

/**
 * Enqueue plugin assets only where the dashboard is rendered.
 *
 * @param string $hook Admin screen hook.
 */
function eam_enqueue_assets( $hook = '' ) {
    $post              = is_singular() ? get_post() : null;
    $is_shortcode_page = $post instanceof WP_Post && has_shortcode( $post->post_content, 'employee_attendance' );
    $is_admin_page     = is_admin() && 'toplevel_page_eam-attendance' === $hook;
    
    if ( ! $is_shortcode_page && ! $is_admin_page ) {
        return;
    }

    wp_enqueue_style(
        'fullcalendar-css',
        EAM_PLUGIN_URL . 'assets/css/fullcalendar.min.css',
        array(),
        '5.11.5'
    );

    wp_enqueue_style(
        'eam-style',
        EAM_PLUGIN_URL . 'assets/css/style.css',
        array(),
        EAM_PLUGIN_VERSION
    );

    wp_enqueue_script(
        'fullcalendar-js',
        EAM_PLUGIN_URL . 'assets/js/fullcalendar.min.js',
        array(),
        '5.11.5',
        true
    );

    wp_enqueue_script(
        'eam-app',
        EAM_PLUGIN_URL . 'assets/js/app.js',
        array( 'jquery', 'fullcalendar-js' ),
        EAM_PLUGIN_VERSION,
        true
    );

    $ajax_data = array(
        'ajax_url'      => admin_url( 'admin-ajax.php' ),
        'nonce'         => wp_create_nonce( 'eam_nonce' ),
        'current_user'  => get_current_user_id(),
        'is_admin'      => current_user_can( 'manage_options' ) ? 1 : 0,
        'date_format'   => get_option( 'date_format' ),
        'time_format'   => get_option( 'time_format' ),
        'i18n'          => array(
            'already_checked_in' => __( 'Already checked in for today.', 'employee-attendance-manager' ),
            'check_in_first'     => __( 'Please check in first before checking out.', 'employee-attendance-manager' ),
            'camera_unavailable' => __( 'Camera access is not available in this browser.', 'employee-attendance-manager' ),
            'camera_denied'      => __( 'Camera permission was denied or the camera is unavailable.', 'employee-attendance-manager' ),
            'camera_start'       => __( 'Please start the camera first.', 'employee-attendance-manager' ),
            'camera_loading'     => __( 'Camera is still loading. Please try again.', 'employee-attendance-manager' ),
            'note_required'      => __( 'Note is required.', 'employee-attendance-manager' ),
            'photo_required'     => __( 'Photo is required.', 'employee-attendance-manager' ),
            'submitting'         => __( 'Submitting...', 'employee-attendance-manager' ),
            'submit'             => __( 'Submit', 'employee-attendance-manager' ),
            'attendance_saved'   => __( 'Attendance saved.', 'employee-attendance-manager' ),
            'save_failed'        => __( 'Attendance could not be saved.', 'employee-attendance-manager' ),
            'network_error'      => __( 'Network error. Please try again.', 'employee-attendance-manager' ),
            'no_record'          => __( 'No attendance record found for this date.', 'employee-attendance-manager' ),
            'load_failed'        => __( 'Failed to load details.', 'employee-attendance-manager' ),
            'attendance_for'     => __( 'Attendance for', 'employee-attendance-manager' ),
            'employee'           => __( 'Employee:', 'employee-attendance-manager' ),
            'check_in'           => __( 'Check-In', 'employee-attendance-manager' ),
            'check_out'          => __( 'Check-Out', 'employee-attendance-manager' ),
            'time'               => __( 'Time:', 'employee-attendance-manager' ),
            'note'               => __( 'Note:', 'employee-attendance-manager' ),
            'not_available'      => __( 'N/A', 'employee-attendance-manager' ),
            'total_duration'     => __( 'Total Duration:', 'employee-attendance-manager' ),
            'details_title'      => __( 'Attendance Details', 'employee-attendance-manager' ),
            'no_records'         => __( 'No records', 'employee-attendance-manager' ),
            'view'               => __( 'View', 'employee-attendance-manager' ),
        ),
    );
    wp_localize_script( 'eam-app', 'EAM', $ajax_data );
}

add_action( 'wp_enqueue_scripts', 'eam_enqueue_assets' );
add_action( 'admin_enqueue_scripts', 'eam_enqueue_assets' );

/**
 * Shortcode: frontend dashboard for employees and admins.
 */
function eam_employee_shortcode() {
    if ( ! is_user_logged_in() ) {
        ob_start();
        ?>
        <div class="eam-card">
            <h2 class="eam-title">Attendance</h2>
            <p class="eam-text">Please log in to access your attendance dashboard.</p>
        </div>
        <?php
        return ob_get_clean();
    }

    $user = wp_get_current_user();
    $is_admin = current_user_can( 'manage_options' );

    ob_start();
    ?>
    <div class="eam-wrapper">
        <div class="eam-card">
            <div class="eam-header">
                <div>
                    <h2 class="eam-title">My Attendance</h2>
                    <p class="eam-subtitle">
                        Hello, <?php echo esc_html( $user->display_name ); ?>
                        <?php if ( $is_admin ) : ?>
                            <span class="eam-role-pill">Administrator</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="eam-header-actions">
                    <button id="eam-open-checkin" class="eam-btn-primary">Check-In</button>
                    <button id="eam-open-checkout" class="eam-btn-secondary">Check-Out</button>
                </div>
            </div>
            <div id="eam-employee-calendar" class="eam-calendar"></div>
        </div>

        <?php if ( $is_admin ) : ?>
        <div class="eam-card" style="margin-top:18px;">
            <div class="eam-header">
                <div>
                    <h2 class="eam-title">Employees</h2>
                    <p class="eam-subtitle">View all employees' attendance.</p>
                </div>
            </div>
            <div class="eam-admin-filters">
                <div class="eam-form-group">
                    <label for="eam-filter-employee">Employee</label>
                    <select id="eam-filter-employee" class="eam-select">
                        <option value="">All Employees</option>
                        <?php
                        $users = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );
                        foreach ( $users as $u ) : ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>">
                                <?php echo esc_html( $u->display_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="eam-form-group">
                    <label for="eam-filter-from">From</label>
                    <input type="date" id="eam-filter-from" class="eam-input">
                </div>
                <div class="eam-form-group">
                    <label for="eam-filter-to">To</label>
                    <input type="date" id="eam-filter-to" class="eam-input">
                </div>
                <button id="eam-filter-apply" class="eam-btn-primary">Apply Filter</button>
            </div>
            <div id="eam-admin-calendar" class="eam-calendar"></div>
            <h2 class="eam-subsection-title">Day-wise Details</h2>
            <div class="eam-table-wrapper">
                <table class="eam-table" id="eam-admin-table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Check-In</th>
                        <th>Check-Out</th>
                        <th>Total Hours</th>
                        <th>IN Photo</th>
                        <th>OUT Photo</th>
                        <th>IN Note</th>
                        <th>OUT Note</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr><td colspan="9">Select a date or apply filters to view records.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Modal template is escaped via wp_kses
echo wp_kses(
    eam_modal_template(),
    array(
        'div' => array(
            'id' => true,
            'class' => true,
            'style' => true,
            'aria-hidden' => true,
        ),
        'h3' => array( 'id' => true, 'class' => true ),
        'button' => array( 'type' => true, 'id' => true, 'class' => true, 'aria-label' => true ),
        'video' => array( 'id' => true, 'class' => true, 'playsinline' => true ),
        'canvas' => array( 'id' => true, 'class' => true, 'style' => true ),
        'img' => array( 'id' => true, 'class' => true, 'style' => true, 'alt' => true, 'src' => true ),
        'label' => array( 'for' => true, 'class' => true ),
        'textarea' => array( 'id' => true, 'class' => true, 'rows' => true, 'placeholder' => true ),
        'input' => array( 'type' => true, 'id' => true, 'value' => true ),
        'p' => array( 'id' => true, 'class' => true, 'style' => true ),
    )
);
?>
    <?php
    return ob_get_clean();
}
add_shortcode( 'employee_attendance', 'eam_employee_shortcode' );

function eam_register_admin_page() {
    add_menu_page(
        'Employee Attendance',
        'Attendance',
        'manage_options',
        'eam-attendance',
        'eam_render_admin_page',
        'dashicons-calendar-alt',
        26
    );
}
add_action( 'admin_menu', 'eam_register_admin_page' );

function eam_render_admin_page() {
    echo do_shortcode( '[employee_attendance]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is escaped inside its renderer.
}

function eam_modal_template() {
    ob_start();
    ?>
    <div id="eam-modal-backdrop" class="eam-modal-backdrop" style="display:none !important; visibility:hidden !important; opacity:0 !important;"></div>
    <div id="eam-modal" class="eam-modal" style="display:none !important; visibility:hidden !important; opacity:0 !important;" aria-hidden="true">
        <div class="eam-modal-dialog">
            <div class="eam-modal-header">
                <h3 id="eam-modal-title" class="eam-modal-title">Mark Attendance</h3>
                <button type="button" id="eam-modal-close" class="eam-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="eam-modal-body" id="eam-modal-body">
                <div class="eam-modal-section" id="eam-camera-section">
                    <video id="eam-video" class="eam-video" playsinline></video>
                    <canvas id="eam-canvas" class="eam-canvas" style="display:none;"></canvas>
                    <img id="eam-photo-preview" class="eam-photo-preview" style="display:none;" alt="Captured photo">
                </div>
                <div class="eam-modal-section" id="eam-note-section">
                    <label for="eam-note" class="eam-label">Note (required)</label>
                    <textarea id="eam-note" class="eam-textarea" rows="3" placeholder="Add a short note..."></textarea>
                </div>
                <input type="hidden" id="eam-action-type" value="check_in">
                <input type="hidden" id="eam-photo-data" value="">
                <div class="eam-modal-footer" id="eam-form-footer">
                    <button type="button" id="eam-capture-photo" class="eam-btn-secondary">Start Camera / Capture</button>
                    <button type="button" id="eam-submit-attendance" class="eam-btn-primary">Submit</button>
                    <button type="button" id="eam-cancel-modal" class="eam-btn-ghost">Cancel</button>
                </div>
                <p id="eam-modal-error" class="eam-error-message" style="display:none;"></p>
                <p id="eam-modal-success" class="eam-success-message" style="display:none;"></p>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * AJAX: events - FIXED DB QUERIES (Plugin Check SAFE)
 */
function eam_ajax_get_events() {
    check_ajax_referer( 'eam_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Not logged in.' ), 401 );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'eam_attendance';

    $start = isset( $_GET['start'] ) ? eam_sanitize_date( sanitize_text_field( wp_unslash( $_GET['start'] ) ) ) : '';
    $end   = isset( $_GET['end'] ) ? eam_sanitize_date( sanitize_text_field( wp_unslash( $_GET['end'] ) ) ) : '';

    $is_admin = current_user_can( 'manage_options' );
    $user_id  = ( $is_admin && ! empty( $_GET['user_id'] ) ) ? absint( $_GET['user_id'] ) : 0;
    $where    = array();
    $params   = array();

    if ( ! $is_admin || $user_id ) {
        $where[] = 'user_id = %d';
        $params[] = $user_id ? $user_id : get_current_user_id();
    }

    if ( $start ) {
        $where[] = 'att_date >= %s';
        $params[] = $start;
    }

    if ( $end ) {
        $where[] = 'att_date <= %s';
        $params[] = $end;
    }

    $sql = "SELECT * FROM {$table_name}";
    if ( $where ) {
        $sql .= ' WHERE ' . implode( ' AND ', $where );
    }
    $sql .= ' ORDER BY att_date ASC, user_id ASC';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query assembled from static clauses and prepared when values exist.
    $rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
    $events = array();

    foreach ( $rows as $row ) {
        $date = $row->att_date;

        $employee_name = $is_admin ? get_the_author_meta( 'display_name', $row->user_id ) : '';
        $title_prefix  = $employee_name ? $employee_name . ' - ' : '';

        if ( $row->check_in ) {
            $events[] = array(
                'title' => $title_prefix . 'IN ' . date_i18n( 'h:i:s a', strtotime( $row->check_in ) ),
                'start' => $date,
                'display' => 'list-item',
                'backgroundColor' => '#10b981',
                'borderColor' => '#10b981',
                'textColor' => '#ffffff',
            );
        }

        if ( $row->check_out ) {
            $events[] = array(
                'title' => $title_prefix . 'OUT ' . date_i18n( 'h:i:s a', strtotime( $row->check_out ) ),
                'start' => $date,
                'display' => 'list-item',
                'backgroundColor' => '#ef4444',
                'borderColor' => '#ef4444',
                'textColor' => '#ffffff',
            );
        }
    }

    wp_send_json_success( $events );
}

add_action( 'wp_ajax_eam_get_events', 'eam_ajax_get_events' );

/**
 * AJAX: day details - FIXED
 */
function eam_ajax_get_day_details() {
    check_ajax_referer( 'eam_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Not logged in.' ), 401 );
    }

    $date = isset( $_POST['date'] ) ? eam_sanitize_date( sanitize_text_field( wp_unslash( $_POST['date'] ) ) ) : '';
    if ( ! $date ) {
        wp_send_json_error( array( 'message' => 'Missing date.' ), 400 );
    }

    global $wpdb;

    $is_admin = current_user_can( 'manage_options' );
    $user_id = $is_admin && ! empty( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : get_current_user_id();

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom attendance table read.
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eam_attendance WHERE user_id = %d AND att_date = %s LIMIT 1",
            $user_id,
            $date
        )
    );

    if ( ! $row ) {
        wp_send_json_success( array( 'exists' => false ) );
    }

    $total_hours = '';
    if ( $row->total_seconds > 0 ) {
        $hours = floor( $row->total_seconds / 3600 );
        $minutes = floor( ( $row->total_seconds % 3600 ) / 60 );
        $total_hours = sprintf( '%02dh %02dm', $hours, $minutes );
    }

    $user_display = get_the_author_meta( 'display_name', $row->user_id );

    $data = array(
        'exists'        => true,
        'user_id'       => intval( $row->user_id ),
        'user_name'     => esc_html( $user_display ? $user_display : '' ),
        'date'          => esc_html( $row->att_date ),
        'check_in'      => $row->check_in ? esc_html( date_i18n( 'h:i:s a', strtotime( $row->check_in ) ) ) : '',
        'check_out'     => $row->check_out ? esc_html( date_i18n( 'h:i:s a', strtotime( $row->check_out ) ) ) : '',
        'total_hours'   => esc_html( $total_hours ),
        'check_in_note' => esc_html( $row->check_in_note ),
        'check_out_note'=> esc_html( $row->check_out_note ),
        'check_in_photo'=> $row->check_in_photo ? esc_url( wp_get_attachment_url( $row->check_in_photo ) ) : '',
        'check_out_photo'=> $row->check_out_photo ? esc_url( wp_get_attachment_url( $row->check_out_photo ) ) : '',
    );

    wp_send_json_success( $data );
}
add_action( 'wp_ajax_eam_get_day_details', 'eam_ajax_get_day_details' );

/**
 * AJAX: today status - FIXED
 */
function eam_ajax_today_status() {
    check_ajax_referer( 'eam_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Not logged in.' ), 401 );
    }

    $user_id = get_current_user_id();
    $today = current_time( 'Y-m-d' );

    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom attendance table read.
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT check_in, check_out FROM {$wpdb->prefix}eam_attendance WHERE user_id = %d AND att_date = %s LIMIT 1",
            $user_id,
            $today
        )
    );

    wp_send_json_success( array(
        'check_in'  => ( $row && $row->check_in ) ? $row->check_in : false,
        'check_out' => ( $row && $row->check_out ) ? $row->check_out : false,
    ) );
}
add_action( 'wp_ajax_eam_today_status', 'eam_ajax_today_status' );

/**
 * AJAX: submit attendance - FIXED SANITIZATION
 */
function eam_ajax_submit_attendance() {
    check_ajax_referer( 'eam_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Not logged in.' ), 401 );
    }

    $user_id = get_current_user_id();
    $action_type = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';
    $note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Base64 image data, sanitized in eam_save_base64_image
    $photo_data = isset( $_POST['photo'] ) ? wp_unslash( $_POST['photo'] ) : '';

    if ( ! in_array( $action_type, array( 'check_in', 'check_out' ), true ) ) {
        wp_send_json_error( array( 'message' => 'Invalid action.' ), 400 );
    }
    if ( empty( $note ) ) {
        wp_send_json_error( array( 'message' => 'Note is required.' ), 400 );
    }
    if ( empty( $photo_data ) ) {
        wp_send_json_error( array( 'message' => 'Photo is required.' ), 400 );
    }

    $today = current_time( 'Y-m-d' );
    $now = current_time( 'mysql' );

    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom attendance table read.
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eam_attendance WHERE user_id = %d AND att_date = %s LIMIT 1",
            $user_id,
            $today
        )
    );

    $photo_id = eam_save_base64_image( $photo_data, $user_id, $action_type, $today );
    if ( is_wp_error( $photo_id ) ) {
        wp_send_json_error( array( 'message' => $photo_id->get_error_message() ), 500 );
    }

    if ( 'check_in' === $action_type ) {
        if ( $row && $row->check_in ) {
            wp_send_json_error( array( 'message' => 'Check-in already recorded for today.' ), 400 );
        }

        if ( $row ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom attendance table write.
            $wpdb->update(
                $wpdb->prefix . 'eam_attendance',
                array(
                    'check_in'       => $now,
                    'check_in_note'  => $note,
                    'check_in_photo' => $photo_id,
                    'updated_at'     => $now,
                ),
                array( 'id' => $row->id ),
                array( '%s', '%s', '%d', '%s' ),
                array( '%d' )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom attendance table write.
            $wpdb->insert(
                $wpdb->prefix . 'eam_attendance',
                array(
                    'user_id'        => $user_id,
                    'att_date'       => $today,
                    'check_in'       => $now,
                    'check_in_note'  => $note,
                    'check_in_photo' => $photo_id,
                    'total_seconds'  => 0,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ),
                array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
            );
        }
        wp_send_json_success( array( 'message' => 'Check-in saved.' ) );
    }

    // CHECK-OUT
    if ( ! $row || ! $row->check_in ) {
        wp_send_json_error( array( 'message' => 'Check-in not found for today.' ), 400 );
    }

    // If already checked out, delete the previous photo to keep media library clean
    if ( ! empty( $row->check_out_photo ) ) {
        wp_delete_attachment( $row->check_out_photo, true );
    }

    $check_in_ts = strtotime( $row->check_in );
    $check_out_ts = strtotime( $now );
    $total_secs = $check_out_ts > $check_in_ts ? ( $check_out_ts - $check_in_ts ) : 0;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom attendance table write.
    $wpdb->update(
        $wpdb->prefix . 'eam_attendance',
        array(
            'check_out'       => $now,
            'check_out_note'  => $note,
            'check_out_photo' => $photo_id,
            'total_seconds'   => $total_secs,
            'updated_at'      => $now,
        ),
        array( 'id' => $row->id ),
        array( '%s', '%s', '%d', '%d', '%s' ),
        array( '%d' )
    );

    wp_send_json_success( array( 'message' => 'Check-out updated successfully.' ) );
}
add_action( 'wp_ajax_eam_submit_attendance', 'eam_ajax_submit_attendance' );

/**
 * Save base64 image as attachment.
 */
function eam_save_base64_image( $data, $user_id, $action_type, $date ) {
    if ( ! function_exists( 'wp_handle_sideload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if ( ! function_exists( 'wp_insert_attachment' ) ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    if ( strpos( $data, 'base64,' ) !== false ) {
        $parts = explode( 'base64,', $data );
        $data = end( $parts );
    }

    if ( ! preg_match( '/^[A-Za-z0-9+\/=\r\n]+$/', $data ) ) {
        return new WP_Error( 'eam_invalid_image', 'Invalid image data.' );
    }

    $decoded = base64_decode( $data, true );
    if ( ! $decoded ) {
        return new WP_Error( 'eam_invalid_image', 'Could not decode image data.' );
    }

    if ( strlen( $decoded ) > 2 * MB_IN_BYTES ) {
        return new WP_Error( 'eam_image_too_large', 'Image is too large. Please capture a smaller photo.' );
    }

    $image_info = @getimagesizefromstring( $decoded );
    if ( ! is_array( $image_info ) || 'image/png' !== $image_info['mime'] ) {
        return new WP_Error( 'eam_invalid_image_type', 'Only PNG camera images are supported.' );
    }

    $upload_dir = wp_upload_dir();
    if ( ! empty( $upload_dir['error'] ) ) {
        return new WP_Error( 'eam_upload_dir_error', $upload_dir['error'] );
    }

    $file_name = 'attendance-' . absint( $user_id ) . '-' . sanitize_file_name( $action_type ) . '-' . sanitize_file_name( $date ) . '-' . time() . '.png';
    $upload    = wp_upload_bits( $file_name, null, $decoded );

    if ( ! empty( $upload['error'] ) ) {
        return new WP_Error( 'eam_file_write_error', $upload['error'] );
    }

    $file_type = wp_check_filetype( $file_name, null );
    $attachment_data = array(
        'post_mime_type' => $file_type['type'],
        'post_title'     => sanitize_file_name( $file_name ),
        'post_content'   => '',
        'post_status'    => 'inherit',
    );

    $attach_id = wp_insert_attachment( $attachment_data, $upload['file'] );
    if ( ! $attach_id ) {
        return new WP_Error( 'eam_attachment_error', 'Could not create attachment.' );
    }

    $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
    wp_update_attachment_metadata( $attach_id, $attach_data );

    return $attach_id;
}

/**
 * AJAX: admin table - FIXED
 */
function eam_ajax_admin_table() {
    check_ajax_referer( 'eam_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
    }

    $employee_id = isset( $_POST['employee_id'] ) ? absint( wp_unslash( $_POST['employee_id'] ) ) : 0;
    $from        = isset( $_POST['from'] ) ? eam_sanitize_date( sanitize_text_field( wp_unslash( $_POST['from'] ) ) ) : '';
    $to          = isset( $_POST['to'] ) ? eam_sanitize_date( sanitize_text_field( wp_unslash( $_POST['to'] ) ) ) : '';
    $date        = isset( $_POST['date'] ) ? eam_sanitize_date( sanitize_text_field( wp_unslash( $_POST['date'] ) ) ) : '';

    global $wpdb;

    $table_name = $wpdb->prefix . 'eam_attendance';
    $where      = array();
    $params     = array();

    if ( $employee_id ) {
        $where[]  = 'user_id = %d';
        $params[] = $employee_id;
    }

    if ( $date ) {
        $where[]  = 'att_date = %s';
        $params[] = $date;
    } else {
        if ( $from ) {
            $where[]  = 'att_date >= %s';
            $params[] = $from;
        }

        if ( $to ) {
            $where[]  = 'att_date <= %s';
            $params[] = $to;
        }
    }

    $sql = "SELECT * FROM {$table_name}";
    if ( $where ) {
        $sql .= ' WHERE ' . implode( ' AND ', $where );
    }
    $sql .= ' ORDER BY att_date DESC, user_id ASC';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query assembled from static clauses and prepared when values exist.
    $rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );

    $data = array();
    foreach ( $rows as $row ) {
        $user_name = esc_html( get_the_author_meta( 'display_name', $row->user_id ) );
        $date_disp = esc_html( date_i18n( get_option( 'date_format' ), strtotime( $row->att_date ) ) );
        $in_time = $row->check_in ? esc_html( date_i18n( 'h:i:s a', strtotime( $row->check_in ) ) ) : '';
        $out_time = $row->check_out ? esc_html( date_i18n( 'h:i:s a', strtotime( $row->check_out ) ) ) : '';

        $total_hours = '';
        if ( $row->total_seconds > 0 ) {
            $hours = floor( $row->total_seconds / 3600 );
            $minutes = floor( ( $row->total_seconds % 3600 ) / 60 );
            $total_hours = esc_html( sprintf( '%02dh %02dm', $hours, $minutes ) );
        }

        $in_photo_url = $row->check_in_photo ? esc_url( wp_get_attachment_url( $row->check_in_photo ) ) : '';
        $out_photo_url = $row->check_out_photo ? esc_url( wp_get_attachment_url( $row->check_out_photo ) ) : '';

        $data[] = array(
            'date' => $date_disp,
            'employee' => $user_name,
            'check_in' => $in_time,
            'check_out' => $out_time,
            'total_hours' => $total_hours,
            'check_in_photo' => $in_photo_url,
            'check_out_photo' => $out_photo_url,
            'check_in_note' => esc_html( $row->check_in_note ),
            'check_out_note' => esc_html( $row->check_out_note ),
        );
    }

    wp_send_json_success( $data );
}
add_action( 'wp_ajax_eam_admin_table', 'eam_ajax_admin_table' );
