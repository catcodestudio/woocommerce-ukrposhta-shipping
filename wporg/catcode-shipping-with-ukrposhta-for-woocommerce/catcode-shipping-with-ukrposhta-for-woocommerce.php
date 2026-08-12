<?php
/**
 * Plugin Name: CatCode Shipping with Ukrposhta for WooCommerce
 * Plugin URI: https://catcode.com.ua/modules/ukrposhta-shipping-for-woocommerce/
 * Description: Ukrposhta delivery for WooCommerce: the customer picks region, city and post office at checkout from the official Address Classifier, and the delivery price is quoted live. Shipment (barcode) creation and sticker printing come in a later update.
 * Version: 1.1.1
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: CatCode
 * Author URI: https://catcode.com.ua
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: catcode-shipping-with-ukrposhta-for-woocommerce
 * Domain Path: /languages
 * WC requires at least: 6.0
 * WC tested up to: 10.7
 *
 * @package CcUkrposhtaWC
 */

defined( 'ABSPATH' ) || exit;

define( 'UPWC_VERSION', '1.1.1' );
define( 'UPWC_FILE', __FILE__ );
define( 'UPWC_DIR', plugin_dir_path( __FILE__ ) );
define( 'UPWC_URL', plugin_dir_url( __FILE__ ) );
define( 'UPWC_BASENAME', plugin_basename( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'CatCode\\UkrposhtaWC\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$path     = UPWC_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\CatCode\\UkrposhtaWC\\Core\\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\CatCode\\UkrposhtaWC\\Core\\Installer', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'CatCode Shipping with Ukrposhta for WooCommerce requires an active WooCommerce installation.', 'catcode-shipping-with-ukrposhta-for-woocommerce' ) . '</p></div>';
				}
			);
			return;
		}
		\CatCode\UkrposhtaWC\Core\Plugin::instance()->boot();
	}
);
