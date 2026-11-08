<?php
/**
 * Pro admin: licence panel and Pro fields on the Ukrposhta settings screen,
 * the shipment box on the order screen, bulk actions in the order list and the
 * PDF downloads (labels, form 103a).
 *
 * @package CcUkrposhtaWC
 */

namespace CatCode\UkrposhtaWC\Pro;

use CatCode\UkrposhtaWC\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Admin {

	private const NONCE = 'upwc_pro';

	public static function register(): void {
		add_filter( 'upwc_secret_fields', array( __CLASS__, 'secret_fields' ) );
		add_filter( 'upwc_form_fields', array( __CLASS__, 'form_fields' ) );
		add_action( 'upwc_settings_before', array( __CLASS__, 'settings_panel' ) );
		add_action( 'wp_ajax_upwc_pro', array( __CLASS__, 'ajax' ) );
		add_action( 'admin_post_upwc_pdf', array( __CLASS__, 'pdf' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ), 30, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'bulk_notice' ) );

		foreach ( array( 'edit-shop_order', 'woocommerce_page_wc-orders' ) as $screen ) {
			add_filter( 'bulk_actions-' . $screen, array( __CLASS__, 'bulk_actions' ) );
			add_filter( 'handle_bulk_actions-' . $screen, array( __CLASS__, 'handle_bulk' ), 10, 3 );
		}
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'column' ), 20 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'column_legacy' ), 10, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'column_hpos' ), 10, 2 );
	}

	/**
	 * @param string[] $fields Secret fields.
	 * @return string[]
	 */
	public static function secret_fields( $fields ): array {
		$fields   = is_array( $fields ) ? $fields : array();
		$fields[] = 'token';
		$fields[] = 'tracking_bearer';
		return $fields;
	}

	/**
	 * Pro fields. Without a licence they are not in the form at all — saving
	 * the free settings then keeps whatever Pro values were stored before.
	 *
	 * @param array $fields Form fields.
	 * @return array
	 */
	public static function form_fields( $fields ): array {
		$fields = is_array( $fields ) ? $fields : array();
		$d      = 'ukrposhta-shipping-for-woocommerce';

		if ( ! License::is_pro() ) {
			$fields['pro_title'] = array(
				'title'       => __( 'Pro: shipments, labels, tracking', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'title',
				'description' => __( 'Shipment creation, cash on delivery, labels, the form 103a registry and delivery statuses open with a Pro licence or the 7-day trial — see the licence box at the top of this page. Everything above keeps working without it.', 'ukrposhta-shipping-for-woocommerce' ),
			);
			return $fields;
		}

		$statuses = array( '' => __( 'Do not change', 'ukrposhta-shipping-for-woocommerce' ) );
		foreach ( wc_get_order_statuses() as $k => $label ) {
			$statuses[ preg_replace( '/^wc-/', '', $k ) ] = $label;
		}

		$pro = array(
			'pro_title'             => array(
				'title'       => __( 'Pro: shipments', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'title',
				'description' => __( 'For shipments Ukrposhta issues a counterparty token together with the Bearer key once the contract is signed.', 'ukrposhta-shipping-for-woocommerce' ),
			),
			'token'                 => array(
				'title'       => __( 'Counterparty token (user token)', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'PROD_COUNTERPARTY TOKEN from the access table of the contract (SAND_COUNTERPARTY TOKEN for the sandbox). Stored encrypted. Leave empty to keep the current one.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => '',
			),
			'tracking_bearer'       => array(
				'title'       => __( 'Bearer StatusTracking', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'For delivery statuses. Empty — the Bearer eCom key is used. Stored encrypted.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => '',
			),
			'sender_type'           => array(
				'title'   => __( 'Sender type', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'INDIVIDUAL'           => __( 'Individual', 'ukrposhta-shipping-for-woocommerce' ),
					'PRIVATE_ENTREPRENEUR' => __( 'Private entrepreneur (FOP)', 'ukrposhta-shipping-for-woocommerce' ),
					'COMPANY'              => __( 'Company', 'ukrposhta-shipping-for-woocommerce' ),
				),
				'default' => 'INDIVIDUAL',
			),
			'sender_last_name'      => array(
				'title'   => __( 'Sender last name', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
			),
			'sender_first_name'     => array(
				'title'   => __( 'Sender first name', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
			),
			'sender_middle_name'    => array(
				'title'       => __( 'Sender middle name', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Required by Ukrposhta for cash on delivery to an individual sender.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => '',
			),
			'sender_company'        => array(
				'title'       => __( 'Company or FOP name', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'For a company sender.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => '',
			),
			'sender_code'           => array(
				'title'       => __( 'EDRPOU / tax number', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'EDRPOU for a company, the 10-digit tax number for a FOP.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => '',
			),
			'sender_phone'          => array(
				'title'   => __( 'Sender phone', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
			),
			'sender_iban'           => array(
				'title'       => __( 'IBAN for cash on delivery', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'For a FOP or a company: the cash on delivery is transferred to this account. An individual receives it at the post office.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => '',
			),
			'sender_uuid'           => array(
				'title'       => __( 'Sender ID at Ukrposhta', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Filled in by the "Create sender" button above. If you already have a sender uuid from Ukrposhta, paste it here.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => '',
			),
			'pro_paid_by_recipient' => array(
				'title'   => __( 'Who pays for delivery', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'yes' => __( 'The recipient, at the post office', 'ukrposhta-shipping-for-woocommerce' ),
					'no'  => __( 'The shop (sender)', 'ukrposhta-shipping-for-woocommerce' ),
				),
				'default' => 'yes',
			),
			'pro_cod'               => array(
				'title'       => __( 'Cash on delivery', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Add cash on delivery to the shipment for orders paid on collection', 'ukrposhta-shipping-for-woocommerce' ),
				'description' => __( 'The payment methods are the ones in "Cash-on-delivery payment methods" above.', 'ukrposhta-shipping-for-woocommerce' ),
				'default'     => 'yes',
			),
			'pro_cod_amount'        => array(
				'title'   => __( 'Cash on delivery amount', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'total' => __( 'Order total', 'ukrposhta-shipping-for-woocommerce' ),
					'goods' => __( 'Goods only, without shipping', 'ukrposhta-shipping-for-woocommerce' ),
				),
				'default' => 'total',
			),
			'pro_cod_fee_recipient' => array(
				'title'   => __( 'Cash on delivery fee', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'yes' => __( 'Paid by the recipient', 'ukrposhta-shipping-for-woocommerce' ),
					'no'  => __( 'Paid by the shop', 'ukrposhta-shipping-for-woocommerce' ),
				),
				'default' => 'yes',
			),
			'default_weight'        => array(
				'title'       => __( 'Weight when products have none, kg', 'ukrposhta-shipping-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '0.5',
			),
			'default_length'        => array(
				'title'   => __( 'Parcel length, cm', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'text',
				'default' => '30',
			),
			'default_width'         => array(
				'title'   => __( 'Parcel width, cm', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'text',
				'default' => '20',
			),
			'default_height'        => array(
				'title'   => __( 'Parcel height, cm', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'text',
				'default' => '10',
			),
			'pro_on_fail'           => array(
				'title'   => __( 'If the recipient does not collect it', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'RETURN'              => __( 'Return to the sender', 'ukrposhta-shipping-for-woocommerce' ),
					'RETURN_AFTER_7_DAYS' => __( 'Return after 7 days of storage', 'ukrposhta-shipping-for-woocommerce' ),
					'PROCESS_AS_REFUSAL'  => __( 'Treat as a refusal', 'ukrposhta-shipping-for-woocommerce' ),
				),
				'default' => 'RETURN',
			),
			'pro_sms'               => array(
				'title'   => __( 'SMS to the recipient', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Ukrposhta sends an SMS when the parcel arrives', 'ukrposhta-shipping-for-woocommerce' ),
				'default' => 'no',
			),
			'pro_label_size'        => array(
				'title'   => __( 'Label format', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					''        => __( '100x100 mm (label printer)', 'ukrposhta-shipping-for-woocommerce' ),
					'SIZE_A4' => __( 'A4 sheet', 'ukrposhta-shipping-for-woocommerce' ),
					'SIZE_A5' => __( 'A5 sheet', 'ukrposhta-shipping-for-woocommerce' ),
				),
				'default' => '',
			),
			'pro_tracking'          => array(
				'title'   => __( 'Delivery statuses', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Check the statuses of open shipments every hour', 'ukrposhta-shipping-for-woocommerce' ),
				'default' => 'yes',
			),
			'pro_status_delivered'  => array(
				'title'   => __( 'Order status when delivered', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'select',
				'options' => $statuses,
				'default' => 'completed',
			),
			'pro_status_returned'   => array(
				'title'   => __( 'Order status when returned', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'select',
				'options' => $statuses,
				'default' => '',
			),
			'pro_customer_tracking' => array(
				'title'   => __( 'Tracking for the customer', 'ukrposhta-shipping-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Show the tracking number and link in the order e-mails and the customer account', 'ukrposhta-shipping-for-woocommerce' ),
				'default' => 'yes',
			),
		);
		unset( $d );
		return array_merge( $fields, $pro );
	}

	/** Are we on the Ukrposhta settings section? */
	private static function on_settings_screen(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen detection only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen detection only.
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		return 'wc-settings' === $page && 'ukrposhta' === $section;
	}

	public static function assets( $hook ): void {
		$screen  = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$sid     = $screen ? (string) $screen->id : '';
		$is_ord  = in_array( $sid, array( 'shop_order', 'woocommerce_page_wc-orders', 'edit-shop_order' ), true );
		if ( ! $is_ord && ! self::on_settings_screen() ) {
			return;
		}
		wp_enqueue_script( 'upwc-pro', UPWC_URL . 'assets/js/pro-admin.js', array(), UPWC_VERSION, true );
		wp_localize_script(
			'upwc-pro',
			'upwcPro',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( self::NONCE ),
				'i18n'  => array(
					'working' => __( 'Working…', 'ukrposhta-shipping-for-woocommerce' ),
					'network' => __( 'No answer from the server. Check the connection and try again.', 'ukrposhta-shipping-for-woocommerce' ),
					'confirmDelete' => __( 'Delete this shipment at Ukrposhta?', 'ukrposhta-shipping-for-woocommerce' ),
				),
			)
		);
		wp_register_style( 'upwc-pro', false, array(), UPWC_VERSION );
		wp_enqueue_style( 'upwc-pro' );
		wp_add_inline_style( 'upwc-pro', self::css() );
	}

	private static function css(): string {
		return '.upwc-lic{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 20px;margin:16px 0;max-width:900px}'
			. '.upwc-lic h2{margin:0 0 6px;font-size:16px}.upwc-lic ul{margin:8px 0 12px 18px;list-style:disc}'
			. '.upwc-lic .upwc-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:8px 0}'
			. '.upwc-lic input[type=text],.upwc-lic input[type=email]{min-width:260px}'
			. '.upwc-msg{margin:8px 0 0;font-weight:600}.upwc-msg.is-error{color:#b32d2e}.upwc-msg.is-ok{color:#00703c}'
			. '.upwc-badge{display:inline-block;padding:2px 8px;border-radius:10px;background:#f0f0f1;font-size:12px}'
			. '.upwc-badge.is-pro{background:#e7f6ec;color:#00703c}.upwc-gaps{color:#8a4b00}'
			. '#upwc-pro-box .upwc-actions{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}#upwc-pro-box dl{margin:0}'
			. '#upwc-pro-box dt{font-weight:600;margin-top:6px}#upwc-pro-box dd{margin:0}';
	}

	/** Licence panel above the settings form. */
	public static function settings_panel(): void {
		if ( ! self::on_settings_screen() ) {
			return;
		}
		$is_pro = License::is_pro();
		echo '<div class="upwc-lic" id="upwc-lic">';
		echo '<h2>' . esc_html__( 'Ukrposhta Pro', 'ukrposhta-shipping-for-woocommerce' ) . ' <span class="upwc-badge' . ( $is_pro ? ' is-pro' : '' ) . '">'
			. esc_html( $is_pro ? __( 'Pro is on', 'ukrposhta-shipping-for-woocommerce' ) : __( 'Free', 'ukrposhta-shipping-for-woocommerce' ) ) . '</span></h2>';

		if ( License::has_license() ) {
			echo '<p>' . esc_html__( 'Licence:', 'ukrposhta-shipping-for-woocommerce' ) . ' <strong>' . esc_html( License::describe() ) . '</strong>';
			$exp = License::expires_at();
			if ( '' !== $exp && ! License::is_owned() && License::days_left() > 0 ) {
				/* translators: %s: date. */
				echo ' · ' . esc_html( sprintf( __( 'until %s', 'ukrposhta-shipping-for-woocommerce' ), date_i18n( get_option( 'date_format' ), (int) strtotime( $exp ) ) ) );
			}
			echo '</p>';
			if ( ! $is_pro && License::is_trial_key() ) {
				echo '<p>' . esc_html__( 'The trial has ended. Buy a licence to turn the Pro features back on — the settings are kept.', 'ukrposhta-shipping-for-woocommerce' ) . '</p>';
			}
		} else {
			echo '<p>' . esc_html__( 'Pro adds to the free picker and tariff:', 'ukrposhta-shipping-for-woocommerce' ) . '</p><ul>';
			foreach ( License::pro_features() as $name ) {
				echo '<li>' . esc_html( $name ) . '</li>';
			}
			echo '</ul>';
		}

		echo '<div class="upwc-row"><input type="text" id="upwc-lic-key" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off" value=""> ';
		echo '<button type="button" class="button button-primary" data-upwc="lic_activate">' . esc_html__( 'Activate key', 'ukrposhta-shipping-for-woocommerce' ) . '</button>';
		if ( License::has_license() ) {
			echo ' <button type="button" class="button" data-upwc="lic_verify">' . esc_html__( 'Check again', 'ukrposhta-shipping-for-woocommerce' ) . '</button>';
			echo ' <button type="button" class="button" data-upwc="lic_deactivate">' . esc_html__( 'Release the key on this site', 'ukrposhta-shipping-for-woocommerce' ) . '</button>';
		}
		echo '</div>';

		if ( License::trial_available() ) {
			echo '<div class="upwc-row"><input type="email" id="upwc-trial-email" placeholder="' . esc_attr__( 'E-mail for the trial key', 'ukrposhta-shipping-for-woocommerce' ) . '" value="' . esc_attr( (string) get_option( 'admin_email' ) ) . '"> ';
			echo '<button type="button" class="button" data-upwc="lic_trial">' . esc_html__( 'Try Pro for 7 days', 'ukrposhta-shipping-for-woocommerce' ) . '</button></div>';
		}
		echo '<p><a href="' . esc_url( License::MODULE_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Buy a licence', 'ukrposhta-shipping-for-woocommerce' ) . '</a></p>';

		if ( $is_pro ) {
			$gaps = Shipments::setup_gaps();
			if ( $gaps ) {
				echo '<p class="upwc-gaps"><strong>' . esc_html__( 'Before the first shipment:', 'ukrposhta-shipping-for-woocommerce' ) . '</strong> ' . esc_html( implode( ' ', $gaps ) ) . '</p>';
			}
			echo '<div class="upwc-row"><button type="button" class="button" data-upwc="sender">' . esc_html__( 'Create sender', 'ukrposhta-shipping-for-woocommerce' ) . '</button> <span class="description">' . esc_html__( 'Uses the saved sender details below; save the settings first.', 'ukrposhta-shipping-for-woocommerce' ) . '</span></div>';
		}
		echo '<p class="upwc-msg" id="upwc-lic-msg" role="status"></p>';
		echo '</div>';
	}

	/** One AJAX endpoint for the licence and the order box. */
	public static function ajax(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';

		$needs = in_array( $op, array( 'lic_activate', 'lic_trial', 'lic_deactivate', 'lic_verify', 'sender' ), true ) ? 'manage_woocommerce' : 'edit_shop_orders';
		if ( ! current_user_can( $needs ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'ukrposhta-shipping-for-woocommerce' ) ), 403 );
		}

		switch ( $op ) {
			case 'lic_activate':
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
				$r   = License::activate( $key );
				self::licence_reply( $r, __( 'The licence is active — the Pro features are on.', 'ukrposhta-shipping-for-woocommerce' ) );
				break;
			case 'lic_trial':
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
				$r     = License::start_trial( $email );
				$until = License::expires_at();
				self::licence_reply(
					$r,
					'' !== $until
						/* translators: %s: date. */
						? sprintf( __( 'The trial has started — Pro works until %s. The key has also been e-mailed to you.', 'ukrposhta-shipping-for-woocommerce' ), date_i18n( get_option( 'date_format' ), (int) strtotime( $until ) ) )
						: __( 'The trial has started. The key has also been e-mailed to you.', 'ukrposhta-shipping-for-woocommerce' )
				);
				break;
			case 'lic_verify':
				$r = License::verify();
				self::licence_reply( $r, __( 'The licence is active — the Pro features are on.', 'ukrposhta-shipping-for-woocommerce' ) );
				break;
			case 'lic_deactivate':
				License::deactivate();
				wp_send_json_success( array( 'message' => __( 'The key is released on this site. The free features keep working.', 'ukrposhta-shipping-for-woocommerce' ), 'reload' => true ) );
				break;
			case 'sender':
				if ( ! License::is_pro() ) {
					wp_send_json_error( array( 'message' => __( 'Creating shipments is a Pro feature.', 'ukrposhta-shipping-for-woocommerce' ) ) );
				}
				$r = Shipments::create_sender();
				$r['ok'] ? wp_send_json_success( array( 'message' => $r['message'], 'reload' => true ) ) : wp_send_json_error( array( 'message' => $r['message'] ) );
				break;
			case 'ship_create':
			case 'ship_delete':
			case 'ship_track':
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$order = wc_get_order( isset( $_POST['order'] ) ? absint( $_POST['order'] ) : 0 );
				if ( ! $order ) {
					wp_send_json_error( array( 'message' => __( 'Order not found.', 'ukrposhta-shipping-for-woocommerce' ) ) );
				}
				if ( 'ship_create' === $op ) {
					$r = Shipments::create( $order );
				} elseif ( 'ship_delete' === $op ) {
					$r = Shipments::delete( $order );
				} else {
					$changed = Shipments::track( array( $order ) );
					$fresh   = wc_get_order( $order->get_id() );
					$code    = $fresh ? (string) $fresh->get_meta( '_upwc_ship_status' ) : '';
					$r       = array(
						'ok'      => true,
						'message' => $changed
							/* translators: %s: status. */
							? sprintf( __( 'Status updated: %s', 'ukrposhta-shipping-for-woocommerce' ), Shipments::status_label( $code ) )
							: __( 'No new status yet. Ukrposhta shows a shipment after it is accepted at the post office.', 'ukrposhta-shipping-for-woocommerce' ),
					);
				}
				$r['ok'] ? wp_send_json_success( array( 'message' => $r['message'], 'reload' => true ) ) : wp_send_json_error( array( 'message' => $r['message'] ) );
				break;
			default:
				wp_send_json_error( array( 'message' => 'unknown op' ), 400 );
		}
	}

	/**
	 * @param array<string,mixed> $r  Licence answer.
	 * @param string              $ok Success text.
	 */
	private static function licence_reply( array $r, string $ok ): void {
		if ( ! empty( $r['ok'] ) ) {
			wp_send_json_success( array( 'message' => $ok, 'reload' => true ) );
		}
		wp_send_json_error( array( 'message' => License::error_message( (string) ( $r['error'] ?? '' ) ) ) );
	}

	/**
	 * @param string $screen_id     Current screen.
	 * @param mixed  $post_or_order Post or order.
	 */
	public static function add_box( $screen_id, $post_or_order ): void {
		if ( ! in_array( (string) $screen_id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
			return;
		}
		add_meta_box( 'upwc-pro-box', __( 'Ukrposhta shipment', 'ukrposhta-shipping-for-woocommerce' ), array( __CLASS__, 'render_box' ), $screen_id, 'side', 'high' );
	}

	/**
	 * @param mixed $post_or_order Post or order.
	 */
	public static function render_box( $post_or_order ): void {
		$order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( is_object( $post_or_order ) ? $post_or_order->ID : (int) $post_or_order );
		if ( ! $order ) {
			return;
		}
		if ( ! Shipments::is_ukrposhta_order( $order ) ) {
			echo '<p>' . esc_html__( 'No Ukrposhta post office was picked for this order.', 'ukrposhta-shipping-for-woocommerce' ) . '</p>';
			return;
		}
		if ( ! License::is_pro() ) {
			echo '<p>' . esc_html__( 'Shipment creation, labels and delivery statuses are part of Pro. Start the 7-day trial or enter a key in the Ukrposhta settings.', 'ukrposhta-shipping-for-woocommerce' ) . '</p>';
			echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=ukrposhta' ) ) . '">' . esc_html__( 'Open the settings', 'ukrposhta-shipping-for-woocommerce' ) . '</a></p>';
			return;
		}
		$id      = $order->get_id();
		$barcode = (string) $order->get_meta( '_upwc_barcode' );
		echo '<div data-order="' . esc_attr( (string) $id ) . '">';
		if ( '' === $barcode ) {
			$draft = Shipments::draft( $order );
			echo '<dl>';
			echo '<dt>' . esc_html__( 'Weight', 'ukrposhta-shipping-for-woocommerce' ) . '</dt><dd>' . esc_html( number_format_i18n( $draft['weight'] / 1000, 3 ) . ' ' . __( 'kg', 'ukrposhta-shipping-for-woocommerce' ) ) . '</dd>';
			echo '<dt>' . esc_html__( 'Declared value', 'ukrposhta-shipping-for-woocommerce' ) . '</dt><dd>' . wp_kses_post( $draft['declared'] > 0 ? wc_price( $draft['declared'], array( 'currency' => $order->get_currency() ) ) : '—' ) . '</dd>';
			echo '<dt>' . esc_html__( 'Cash on delivery', 'ukrposhta-shipping-for-woocommerce' ) . '</dt><dd>' . wp_kses_post( $draft['cod'] > 0 ? wc_price( $draft['cod'], array( 'currency' => $order->get_currency() ) ) : __( 'no', 'ukrposhta-shipping-for-woocommerce' ) ) . '</dd>';
			echo '</dl>';
			$err = (string) $order->get_meta( '_upwc_ship_error' );
			if ( '' !== $err ) {
				echo '<p class="upwc-msg is-error">' . esc_html( $err ) . '</p>';
			}
			$gaps = Shipments::setup_gaps();
			if ( $gaps ) {
				echo '<p class="upwc-gaps">' . esc_html( implode( ' ', $gaps ) ) . '</p>';
			}
			echo '<div class="upwc-actions"><button type="button" class="button button-primary" data-upwc="ship_create">' . esc_html__( 'Create shipment', 'ukrposhta-shipping-for-woocommerce' ) . '</button></div>';
		} else {
			$code = (string) $order->get_meta( '_upwc_ship_status' );
			$name = (string) $order->get_meta( '_upwc_ship_event_name' );
			echo '<dl>';
			echo '<dt>' . esc_html__( 'Barcode', 'ukrposhta-shipping-for-woocommerce' ) . '</dt><dd><a href="' . esc_url( Shipments::TRACK_URL . rawurlencode( $barcode ) ) . '" target="_blank" rel="noopener">' . esc_html( $barcode ) . '</a></dd>';
			echo '<dt>' . esc_html__( 'Status', 'ukrposhta-shipping-for-woocommerce' ) . '</dt><dd>' . esc_html( '' !== $name ? $name : Shipments::status_label( '' !== $code ? $code : 'CREATED' ) ) . '</dd>';
			$price = (string) $order->get_meta( '_upwc_delivery_price' );
			if ( '' !== $price ) {
				echo '<dt>' . esc_html__( 'Delivery by Ukrposhta', 'ukrposhta-shipping-for-woocommerce' ) . '</dt><dd>' . wp_kses_post( wc_price( (float) $price, array( 'currency' => 'UAH' ) ) ) . '</dd>';
			}
			$cod = (float) $order->get_meta( '_upwc_cod' );
			if ( $cod > 0 ) {
				echo '<dt>' . esc_html__( 'Cash on delivery', 'ukrposhta-shipping-for-woocommerce' ) . '</dt><dd>' . wp_kses_post( wc_price( $cod, array( 'currency' => $order->get_currency() ) ) ) . '</dd>';
			}
			echo '</dl><div class="upwc-actions">';
			echo '<a class="button button-primary" href="' . esc_url( self::pdf_url( 'labels', array( $id ) ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Label (PDF)', 'ukrposhta-shipping-for-woocommerce' ) . '</a>';
			echo '<button type="button" class="button" data-upwc="ship_track">' . esc_html__( 'Update status', 'ukrposhta-shipping-for-woocommerce' ) . '</button>';
			if ( '' === $code || 'CREATED' === $code ) {
				echo '<button type="button" class="button-link-delete" data-upwc="ship_delete">' . esc_html__( 'Delete shipment', 'ukrposhta-shipping-for-woocommerce' ) . '</button>';
			}
			echo '</div>';
		}
		echo '<p class="upwc-msg" role="status"></p></div>';
	}

	/**
	 * @param int[] $ids Order ids.
	 */
	public static function pdf_url( string $what, array $ids ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'upwc_pdf',
					'what'   => $what,
					'ids'    => implode( ',', array_map( 'absint', $ids ) ),
				),
				admin_url( 'admin-post.php' )
			),
			'upwc_pdf'
		);
	}

	/** Stream a label / registry PDF, or explain why there is none. */
	public static function pdf(): void {
		check_admin_referer( 'upwc_pdf' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'ukrposhta-shipping-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		$what = isset( $_GET['what'] ) ? sanitize_key( wp_unslash( $_GET['what'] ) ) : '';
		$ids  = isset( $_GET['ids'] ) ? array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['ids'] ) ) ) ) ) : array();
		$orders = array_values( array_filter( array_map( 'wc_get_order', array_slice( $ids, 0, 100 ) ) ) );
		$r = 'registry' === $what ? Shipments::registry( $orders ) : Shipments::labels( $orders );
		if ( empty( $r['ok'] ) ) {
			wp_die(
				'<p>' . esc_html( (string) $r['message'] ) . '</p><p><a href="' . esc_url( wp_get_referer() ? wp_get_referer() : admin_url() ) . '">' . esc_html__( 'Back', 'ukrposhta-shipping-for-woocommerce' ) . '</a></p>',
				esc_html__( 'Ukrposhta', 'ukrposhta-shipping-for-woocommerce' ),
				array( 'response' => 200 )
			);
		}
		$name = ( 'registry' === $what ? 'ukrposhta-form103a-' : 'ukrposhta-labels-' ) . gmdate( 'Ymd-His' ) . '.pdf';
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . $name . '"' );
		header( 'Content-Length: ' . strlen( (string) $r['pdf'] ) );
		echo $r['pdf']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary PDF from Ukrposhta.
		exit;
	}

	/**
	 * @param array<string,string> $actions Bulk actions.
	 * @return array<string,string>
	 */
	public static function bulk_actions( $actions ): array {
		$actions = is_array( $actions ) ? $actions : array();
		if ( ! License::is_pro() ) {
			return $actions;
		}
		$actions['upwc_create']   = __( 'Ukrposhta: create shipments', 'ukrposhta-shipping-for-woocommerce' );
		$actions['upwc_labels']   = __( 'Ukrposhta: print labels', 'ukrposhta-shipping-for-woocommerce' );
		$actions['upwc_registry'] = __( 'Ukrposhta: form 103a registry', 'ukrposhta-shipping-for-woocommerce' );
		$actions['upwc_track']    = __( 'Ukrposhta: update statuses', 'ukrposhta-shipping-for-woocommerce' );
		return $actions;
	}

	/**
	 * @param string $redirect Redirect URL.
	 * @param string $action   Action.
	 * @param int[]  $ids      Order ids.
	 */
	public static function handle_bulk( $redirect, $action, $ids ): string {
		if ( 0 !== strpos( (string) $action, 'upwc_' ) || ! current_user_can( 'edit_shop_orders' ) ) {
			return (string) $redirect;
		}
		$ids = array_slice( array_map( 'absint', (array) $ids ), 0, 50 );
		if ( 'upwc_labels' === $action || 'upwc_registry' === $action ) {
			return self::pdf_url( 'upwc_registry' === $action ? 'registry' : 'labels', $ids );
		}
		$ok = 0;
		$errors = array();
		if ( 'upwc_track' === $action ) {
			$orders = array_values( array_filter( array_map( 'wc_get_order', $ids ) ) );
			$ok     = Shipments::track( $orders );
		} else {
			foreach ( $ids as $id ) {
				$o = wc_get_order( $id );
				if ( ! $o ) {
					continue;
				}
				if ( ! Shipments::is_ukrposhta_order( $o ) ) {
					/* translators: %s: order number. */
					$errors[] = sprintf( __( '#%s: not a Ukrposhta order', 'ukrposhta-shipping-for-woocommerce' ), $o->get_order_number() );
					continue;
				}
				$r = Shipments::create( $o );
				if ( $r['ok'] ) {
					++$ok;
				} else {
					$errors[] = '#' . $o->get_order_number() . ': ' . $r['message'];
				}
			}
		}
		set_transient( 'upwc_bulk_' . get_current_user_id(), array( 'action' => $action, 'ok' => $ok, 'errors' => array_slice( $errors, 0, 20 ) ), 300 );
		return (string) $redirect;
	}

	public static function bulk_notice(): void {
		$key  = 'upwc_bulk_' . get_current_user_id();
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			return;
		}
		delete_transient( $key );
		$ok = (int) $data['ok'];
		if ( 'upwc_track' === $data['action'] ) {
			/* translators: %d: number of orders. */
			$text = sprintf( _n( 'Ukrposhta: status changed in %d order.', 'Ukrposhta: status changed in %d orders.', $ok, 'ukrposhta-shipping-for-woocommerce' ), $ok );
		} else {
			/* translators: %d: number of shipments. */
			$text = sprintf( _n( 'Ukrposhta: %d shipment created.', 'Ukrposhta: %d shipments created.', $ok, 'ukrposhta-shipping-for-woocommerce' ), $ok );
		}
		echo '<div class="notice notice-' . ( $data['errors'] ? 'warning' : 'success' ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p>';
		foreach ( (array) $data['errors'] as $e ) {
			echo '<p>' . esc_html( (string) $e ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * @param array<string,string> $cols Columns.
	 * @return array<string,string>
	 */
	public static function column( $cols ): array {
		$cols = is_array( $cols ) ? $cols : array();
		if ( ! License::is_pro() ) {
			return $cols;
		}
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'order_status' === $k ) {
				$out['upwc'] = __( 'Ukrposhta', 'ukrposhta-shipping-for-woocommerce' );
			}
		}
		if ( ! isset( $out['upwc'] ) ) {
			$out['upwc'] = __( 'Ukrposhta', 'ukrposhta-shipping-for-woocommerce' );
		}
		return $out;
	}

	/**
	 * @param string $col     Column.
	 * @param int    $post_id Post id.
	 */
	public static function column_legacy( $col, $post_id ): void {
		if ( 'upwc' === $col ) {
			$o = wc_get_order( $post_id );
			if ( $o ) {
				self::cell( $o );
			}
		}
	}

	/**
	 * @param string    $col   Column.
	 * @param \WC_Order $order Order.
	 */
	public static function column_hpos( $col, $order ): void {
		if ( 'upwc' === $col && $order instanceof \WC_Order ) {
			self::cell( $order );
		}
	}

	private static function cell( \WC_Order $o ): void {
		$b = (string) $o->get_meta( '_upwc_barcode' );
		if ( '' !== $b ) {
			$code = (string) $o->get_meta( '_upwc_ship_status' );
			echo '<a href="' . esc_url( Shipments::TRACK_URL . rawurlencode( $b ) ) . '" target="_blank" rel="noopener">' . esc_html( $b ) . '</a><br><small>' . esc_html( Shipments::status_label( '' !== $code ? $code : 'CREATED' ) ) . '</small>';
		} elseif ( Shipments::is_ukrposhta_order( $o ) ) {
			echo '<small>' . esc_html__( 'no shipment yet', 'ukrposhta-shipping-for-woocommerce' ) . '</small>';
		} else {
			echo '–';
		}
	}
}
