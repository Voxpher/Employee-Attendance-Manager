<?php
/**
 * Uninstall cleanup for Employee Attendance Manager.
 *
 * @package EmployeeAttendanceManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'eam_attendance';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Static custom table name with WordPress prefix only.
$rows = $wpdb->get_results( "SELECT check_in_photo, check_out_photo FROM {$table_name}" );

if ( $rows ) {
    foreach ( $rows as $row ) {
        if ( ! empty( $row->check_in_photo ) ) {
            wp_delete_attachment( (int) $row->check_in_photo, true );
        }

        if ( ! empty( $row->check_out_photo ) ) {
            wp_delete_attachment( (int) $row->check_out_photo, true );
        }
    }
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Static custom table name with WordPress prefix only during uninstall.
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

delete_option( 'eam_db_version' );
