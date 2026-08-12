<?php
/**
 * Activation / deactivation: offices cache table + version bookkeeping.
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Core;

defined( 'ABSPATH' ) || exit;

class Installer {

	public static function activate(): void {
		global $wpdb;
		$table   = $wpdb->prefix . 'upwc_offices';
		$collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$sql = "CREATE TABLE {$table} (
			office_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			city_id VARCHAR(16) NOT NULL,
			postindex VARCHAR(10) NOT NULL,
			name VARCHAR(512) NULL,
			address VARCHAR(512) NULL,
			is_postomat TINYINT(1) NOT NULL DEFAULT 0,
			updated_at DATETIME NULL,
			PRIMARY KEY (office_id),
			UNIQUE KEY city_pi (city_id, postindex),
			KEY updated_at (updated_at)
		) {$collate};";
		dbDelta( $sql );

		update_option( 'upwc_version', UPWC_VERSION, false );
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'upwc_poll_status' );
	}
}
