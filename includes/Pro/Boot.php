<?php
/**
 * Pro bootstrap: licence re-check, delivery status cron, admin and customer parts.
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Pro;

defined( 'ABSPATH' ) || exit;

class Boot {

	public const CRON_TRACK   = 'upwc_poll_status';
	public const CRON_LICENSE = 'upwc_license_verify';

	public static function init(): void {
		add_action( self::CRON_TRACK, array( Shipments::class, 'cron' ) );
		add_action( self::CRON_LICENSE, array( License::class, 'verify' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		Customer::register();
		if ( is_admin() ) {
			Admin::register();
		}
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_LICENSE ) && License::has_license() ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_LICENSE );
		}
		if ( ! wp_next_scheduled( self::CRON_TRACK ) && License::is_pro() ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::CRON_TRACK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_TRACK );
		wp_clear_scheduled_hook( self::CRON_LICENSE );
	}
}
