<?php
/**
 * Uninstall: drop the classifier cache table and options. Order meta with
 * shipment barcodes is preserved on purpose.
 *
 * @package CcUkrposhtaWC
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$upwc_table = $wpdb->prefix . 'upwc_offices';
// Table names cannot be bound as placeholders; the name is built from $wpdb->prefix.
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
$wpdb->query( "DROP TABLE IF EXISTS {$upwc_table}" );

delete_option( 'upwc_version' );
delete_transient( 'upwc_regions' );

wp_clear_scheduled_hook( 'upwc_poll_status' );
