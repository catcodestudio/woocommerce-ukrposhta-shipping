<?php
/**
 * Uninstall: drop the classifier cache table and options. Order meta with
 * shipment barcodes is preserved on purpose.
 *
 * @package CcUkrposhtaWC
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$table = $wpdb->prefix . 'upwc_offices';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB

delete_option( 'upwc_version' );
delete_transient( 'upwc_regions' );

wp_clear_scheduled_hook( 'upwc_poll_status' );
