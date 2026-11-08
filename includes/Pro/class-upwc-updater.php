<?php
/**
 * Updates from catcode.com.ua for a CatCode Pro plugin.
 *
 * Pro builds carry `Update URI: https://catcode.com.ua/...`. WordPress then
 * never asks wordpress.org about the plugin and instead calls the filter
 * `update_plugins_catcode.com.ua` (wp-includes/update.php) — this class answers
 * it, and the "View details" popup (`plugins_api`), from
 * POST https://catcode.com.ua/wp-json/catcode/v1/update.
 *
 *  - The server always tells the latest version; the zip (`package`) comes only
 *    for a licence that covers updates and is activated on this site. Without
 *    one WordPress shows the new version with "Automatic update is unavailable",
 *    and the server's explanation is printed under it.
 *  - The answer is cached in a site transient (3 h; 30 min after a failure).
 *    "Check again" on Dashboard → Updates bypasses the cache.
 *  - An unreachable or broken server never breaks the site: the filter hands
 *    WordPress back what it got (no update), the last good answer is kept.
 *  - The package link is short-lived; if it expired before "Update" was
 *    pressed, a fresh one is fetched right before the download.
 *
 * Template: _factory/updater/client/ of the CatCode workspace. Do not edit the
 * copy inside a plugin — `python _factory/updater/install_updater.py` copies it
 * with the plugin's own prefix in place of the template one (class, transient),
 * because two plugins declaring the same global class would fatal.
 *
 * Usage (main plugin file, Pro build only — inside @pro-begin/@pro-end):
 *
 *   require_once __DIR__ . '/includes/Pro/class-upwc-updater.php';
 *   UPWC_Updater::init( array(
 *       'file' => __FILE__,
 *       'slug' => 'module-slug-on-catcode',          // = License::EXPECTED_SLUG
 *       'key'  => static function () { return License::key(); },
 *   ) );
 *
 * @version 1.0.0 (09.10.2026)
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'UPWC_Updater', false ) ) :

	final class UPWC_Updater {

		const API      = 'https://catcode.com.ua/wp-json/catcode/v1/update';
		const HOST     = 'catcode.com.ua';
		const TTL_OK   = 10800;  // 3 h — how long an answer counts as fresh.
		const TTL_FAIL = 1800;   // 30 min — next try after a failure.
		const KEEP     = 604800; // 7 days — the last good answer survives an outage.
		const TIMEOUT  = 6;
		// Shared by every CatCode plugin on the site (no prefix on purpose): when
		// catcode.com.ua does not answer, the other plugins skip their requests too,
		// so an outage costs one timeout, not one per plugin.
		const DOWN_FLAG = 'catcode_updater_down';
		const DOWN_TTL  = 900;

		/** @var string Main plugin file. */
		private $file;
		/** @var string plugin_basename(). */
		private $basename;
		/** @var string Plugin folder = slug WordPress uses for "View details". */
		private $dir_slug;
		/** @var string Module slug on catcode.com.ua. */
		private $module_slug;
		/** @var callable|null Returns the stored licence key. */
		private $key_cb;
		/** @var string */
		private $endpoint;
		/** @var string Site transient name. */
		private $cache;
		/** @var bool Re-entrancy guard for the transient read filter. */
		private $patching = false;

		/**
		 * @param array{file:string,slug:string,key?:callable,endpoint?:string} $args
		 */
		public static function init( array $args ) {
			if ( empty( $args['file'] ) || empty( $args['slug'] ) ) {
				return null;
			}
			$self              = new self();
			$self->file        = (string) $args['file'];
			$self->basename    = plugin_basename( $self->file );
			$self->dir_slug    = dirname( $self->basename );
			$self->module_slug = (string) $args['slug'];
			$self->key_cb      = isset( $args['key'] ) && is_callable( $args['key'] ) ? $args['key'] : null;
			if ( ! empty( $args['endpoint'] ) ) {
				$self->endpoint = (string) $args['endpoint'];
			} elseif ( defined( 'CATCODE_UPDATE_API' ) ) {
				$self->endpoint = (string) constant( 'CATCODE_UPDATE_API' );
			} else {
				$self->endpoint = self::API;
			}
			$self->cache = 'upwc_upd_' . substr( md5( $self->basename ), 0, 12 );

			add_filter( 'update_plugins_' . self::HOST, array( $self, 'filter_update' ), 10, 4 );
			add_filter( 'plugins_api', array( $self, 'filter_plugins_api' ), 20, 3 );
			add_action( 'in_plugin_update_message-' . $self->basename, array( $self, 'print_update_message' ), 10, 2 );
			add_filter( 'upgrader_pre_download', array( $self, 'refresh_expired_package' ), 10, 4 );
			add_action( 'upgrader_process_complete', array( $self, 'flush' ), 10, 0 );
			add_filter( 'site_transient_update_plugins', array( $self, 'follow_key_change' ), 20 );

			return $self;
		}

		/** Drop the cached answer (after an upgrade, or on demand). */
		public function flush() {
			delete_site_transient( $this->cache );
		}

		/**
		 * update_plugins_{$hostname}.
		 *
		 * @param array|false $update      What other callbacks returned (default false).
		 * @param array       $plugin_data Headers of the plugin being checked.
		 * @param string      $plugin_file Its basename.
		 * @param string[]    $locales     Installed locales.
		 * @return array|false
		 */
		public function filter_update( $update, $plugin_data, $plugin_file, $locales = array() ) {
			if ( $plugin_file !== $this->basename || ! is_file( $this->file ) ) {
				return $update;
			}
			$force = is_admin() && ! empty( $_GET['force-check'] ) && current_user_can( 'update_plugins' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$info  = $this->info( $force );
			if ( ! $info ) {
				return $update;
			}

			return $this->entry( $info, isset( $plugin_data['UpdateURI'] ) ? (string) $plugin_data['UpdateURI'] : '' );
		}

		/**
		 * site_transient_update_plugins (read): the stored entry was built for the
		 * key that was set at the last check. When the owner has entered, changed
		 * or released the key since, WordPress would keep showing the old verdict
		 * ("Automatic update is unavailable") for up to 12 hours — rebuild just this
		 * plugin's entry for the current key instead. One request per key change;
		 * after that the answer comes from the cache.
		 *
		 * @param mixed $value The update_plugins site transient.
		 * @return mixed
		 */
		public function follow_key_change( $value ) {
			// The plugin files may already be gone while this request still runs:
			// uninstall/delete (wp-admin and `wp plugin uninstall`) reads this transient
			// right after deleting the folder, and the key went with the settings —
			// asking the server then would read the header of a missing file.
			if ( $this->patching || ! is_object( $value ) || ! is_file( $this->file ) ) {
				return $value;
			}
			$entry = null;
			if ( isset( $value->response[ $this->basename ] ) ) {
				$entry = $value->response[ $this->basename ];
			} elseif ( isset( $value->no_update[ $this->basename ] ) ) {
				$entry = $value->no_update[ $this->basename ];
			}
			if ( ! is_object( $entry ) || ! isset( $entry->catcode_key ) || $entry->catcode_key === $this->key_hash() ) {
				return $value;
			}

			$this->patching = true;
			$info           = $this->info();
			$this->patching = false;
			if ( ! $info ) {
				return $value;
			}

			$fresh = (object) $this->entry( $info, isset( $entry->id ) ? (string) $entry->id : '' );
			unset( $value->response[ $this->basename ], $value->no_update[ $this->basename ] );
			if ( version_compare( $fresh->new_version, $this->installed_version(), '>' ) ) {
				$value->response[ $this->basename ] = $fresh;
			} else {
				$value->no_update[ $this->basename ] = $fresh;
			}
			return $value;
		}

		/**
		 * The entry WordPress keeps in update_plugins for this plugin.
		 *
		 * @param array<string,mixed> $info Normalised server answer.
		 * @param string              $id   Update URI.
		 * @return array<string,mixed>
		 */
		private function entry( array $info, $id ) {
			return array(
				'id'                => $id,
				'slug'              => $this->dir_slug,
				'plugin'            => $this->basename,
				'version'           => $info['version'],
				'new_version'       => $info['version'],
				'url'               => $info['homepage'],
				'package'           => $info['package'],
				'requires'          => $info['requires'],
				'tested'            => $info['tested'],
				'requires_php'      => $info['requires_php'],
				'icons'             => $info['icons'],
				'catcode_message'   => $info['message'],
				'catcode_renew_url' => $info['renew_url'],
				'catcode_key'       => $this->key_hash(),
			);
		}

		/**
		 * plugins_api: the "View details" popup.
		 *
		 * @param false|object|array $result Result so far.
		 * @param string             $action Requested action.
		 * @param object             $args   Arguments (slug, …).
		 * @return false|object|array
		 */
		public function filter_plugins_api( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || ! is_object( $args ) || empty( $args->slug ) || $args->slug !== $this->dir_slug ) {
				return $result;
			}
			$info = $this->info();
			if ( ! $info ) {
				return $result;
			}

			$sections = $info['sections'];
			if ( '' !== $info['message'] ) {
				$note                  = '<p><strong>' . esc_html( $info['message'] ) . '</strong></p>';
				$sections['changelog'] = $note . ( isset( $sections['changelog'] ) ? $sections['changelog'] : '' );
			}
			$sections = array_filter( $sections, 'strlen' );

			return (object) array(
				'name'          => $info['name'],
				'slug'          => $this->dir_slug,
				'version'       => $info['version'],
				'author'        => '<a href="' . esc_url( $info['author_url'] ) . '">' . esc_html( $info['author'] ) . '</a>',
				'homepage'      => $info['homepage'],
				'requires'      => $info['requires'],
				'tested'        => $info['tested'],
				'requires_php'  => $info['requires_php'],
				'last_updated'  => $info['last_updated'],
				'sections'      => $sections,
				'download_link' => $info['package'],
				'icons'         => $info['icons'],
				'banners'       => array(),
				'external'      => true,
			);
		}

		/**
		 * Under "There is a new version … Automatic update is unavailable": why.
		 *
		 * @param array  $plugin_data Plugin headers.
		 * @param object $response    The update entry from the transient.
		 */
		public function print_update_message( $plugin_data, $response ) {
			if ( ! is_object( $response ) || ! empty( $response->package ) || empty( $response->catcode_message ) ) {
				return;
			}
			echo '<br><span class="catcode-update-note">' . esc_html( (string) $response->catcode_message );
			if ( ! empty( $response->catcode_renew_url ) ) {
				echo ' <a href="' . esc_url( (string) $response->catcode_renew_url ) . '" target="_blank" rel="noopener">catcode.com.ua</a>';
			}
			echo '</span>';
		}

		/**
		 * upgrader_pre_download: the signed link lives two days; an update pressed
		 * later gets a fresh link instead of a 410.
		 *
		 * @param false|string|WP_Error $reply      Short-circuit value.
		 * @param string                $package    Package URL.
		 * @param WP_Upgrader           $upgrader   Upgrader.
		 * @param array                 $hook_extra Context.
		 * @return false|string|WP_Error
		 */
		public function refresh_expired_package( $reply, $package, $upgrader = null, $hook_extra = array() ) {
			if ( false !== $reply || ! is_string( $package ) || ! $this->is_our_package( $package ) ) {
				return $reply;
			}
			if ( ! empty( $hook_extra['plugin'] ) && $hook_extra['plugin'] !== $this->basename ) {
				return $reply;
			}
			$query = array();
			wp_parse_str( (string) wp_parse_url( $package, PHP_URL_QUERY ), $query );
			$exp = isset( $query['exp'] ) ? (int) $query['exp'] : 0;
			if ( $exp > time() + 60 ) {
				return $reply;
			}
			$info = $this->info( true );
			if ( ! $info || '' === $info['package'] ) {
				return new WP_Error( 'catcode_no_package', $info && '' !== $info['message'] ? $info['message'] : 'catcode.com.ua: no update package.' );
			}
			if ( ! function_exists( 'download_url' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			return download_url( $info['package'] );
		}

		/**
		 * Latest-version answer, normalised, or null when nothing usable is known.
		 *
		 * @param bool $force Skip the cache.
		 * @return array<string,mixed>|null
		 */
		private function info( $force = false ) {
			$key    = $this->key();
			$locale = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
			$hash   = md5( $key . '|' . $this->installed_version() . '|' . $this->endpoint . '|' . $locale );
			$cached = get_site_transient( $this->cache );
			$fresh  = is_array( $cached ) && isset( $cached['h'], $cached['until'] ) && $cached['h'] === $hash && (int) $cached['until'] > time();

			if ( $fresh && ! $force ) {
				return isset( $cached['data'] ) && is_array( $cached['data'] ) ? $cached['data'] : null;
			}

			$data = ( $force || ! get_site_transient( self::DOWN_FLAG ) ) ? $this->request( $key, $locale ) : null;
			if ( null === $data ) {
				// Keep serving the last good answer for this key while the server is away.
				$last = is_array( $cached ) && isset( $cached['data'] ) && is_array( $cached['data'] ) && isset( $cached['k'] ) && $cached['k'] === md5( $key ) ? $cached['data'] : null;
				set_site_transient(
					$this->cache,
					array( 'h' => $hash, 'k' => md5( $key ), 'until' => time() + self::TTL_FAIL, 'data' => $last ),
					self::KEEP
				);
				return $last;
			}

			set_site_transient(
				$this->cache,
				array( 'h' => $hash, 'k' => md5( $key ), 'until' => time() + self::TTL_OK, 'data' => $data ),
				self::KEEP
			);
			return $data;
		}

		/**
		 * One call to the update endpoint. null on any failure.
		 *
		 * @param string $key    Licence key ('' = none).
		 * @param string $locale Admin locale, for the server's messages.
		 * @return array<string,mixed>|null
		 */
		private function request( $key, $locale ) {
			$response = wp_remote_post(
				$this->endpoint,
				array(
					'timeout' => self::TIMEOUT,
					'headers' => array(
						'Content-Type' => 'application/json',
						'Accept'       => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'slug'     => $this->module_slug,
							'key'      => $key,
							'site_url' => $this->site_url(),
							'version'  => $this->installed_version(),
							'locale'   => $locale,
						)
					),
				)
			);
			$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			if ( 0 === $code || 429 === $code || $code >= 500 ) {
				// Unreachable, throttled or broken — not "no such module" (404).
				set_site_transient( self::DOWN_FLAG, 1, self::DOWN_TTL );
				return null;
			}
			if ( 200 !== $code ) {
				return null;
			}
			$raw = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $raw ) || empty( $raw['ok'] ) || empty( $raw['version'] ) ) {
				return null;
			}
			$version = (string) $raw['version'];
			if ( ! preg_match( '/^\d+(\.\d+){0,3}([.\-+][0-9A-Za-z.\-]+)?$/', $version ) ) {
				return null;
			}

			$str = static function ( $v ) {
				return is_scalar( $v ) ? trim( (string) $v ) : '';
			};

			// The zip may only come from the same host as the endpoint.
			$package = $str( isset( $raw['package'] ) ? $raw['package'] : '' );
			if ( '' !== $package && ! $this->is_our_package( $package ) ) {
				$package = '';
			}

			$sections = array();
			if ( isset( $raw['sections'] ) && is_array( $raw['sections'] ) ) {
				foreach ( array( 'description', 'changelog' ) as $s ) {
					if ( isset( $raw['sections'][ $s ] ) && is_string( $raw['sections'][ $s ] ) ) {
						$sections[ $s ] = wp_kses_post( $raw['sections'][ $s ] );
					}
				}
			}
			$icons = array();
			if ( isset( $raw['icons'] ) && is_array( $raw['icons'] ) ) {
				foreach ( $raw['icons'] as $size => $url ) {
					if ( is_string( $url ) && '' !== $url ) {
						$icons[ (string) $size ] = esc_url_raw( $url );
					}
				}
			}

			return array(
				'version'      => $version,
				'name'         => $str( isset( $raw['name'] ) ? $raw['name'] : '' ),
				'homepage'     => esc_url_raw( $str( isset( $raw['homepage'] ) ? $raw['homepage'] : '' ) ),
				'author'       => $str( isset( $raw['author'] ) ? $raw['author'] : 'CatCode' ),
				'author_url'   => esc_url_raw( $str( isset( $raw['author_url'] ) ? $raw['author_url'] : 'https://catcode.com.ua/' ) ),
				'requires'     => $str( isset( $raw['requires'] ) ? $raw['requires'] : '' ),
				'tested'       => $str( isset( $raw['tested'] ) ? $raw['tested'] : '' ),
				'requires_php' => $str( isset( $raw['requires_php'] ) ? $raw['requires_php'] : '' ),
				'last_updated' => $str( isset( $raw['last_updated'] ) ? $raw['last_updated'] : '' ),
				'package'      => $package,
				'message'      => $str( isset( $raw['message'] ) ? $raw['message'] : '' ),
				'renew_url'    => esc_url_raw( $str( isset( $raw['renew_url'] ) ? $raw['renew_url'] : '' ) ),
				'sections'     => $sections,
				'icons'        => $icons,
			);
		}

		/** @param string $url */
		private function is_our_package( $url ) {
			$host = wp_parse_url( $this->endpoint, PHP_URL_HOST );
			return is_string( $host ) && '' !== $host && wp_parse_url( $url, PHP_URL_HOST ) === $host
				&& false !== strpos( (string) wp_parse_url( $url, PHP_URL_PATH ), '/catcode/v1/update-package' );
		}

		private function key() {
			if ( ! $this->key_cb ) {
				return '';
			}
			try {
				return trim( (string) call_user_func( $this->key_cb ) );
			} catch ( \Throwable $e ) {
				return '';
			}
		}

		/** Short fingerprint of the stored key ('' = none) — never the key itself. */
		private function key_hash() {
			$key = $this->key();
			return '' === $key ? '' : substr( md5( 'upwc_key|' . $key ), 0, 12 );
		}

		private function installed_version() {
			static $v = array();
			if ( ! isset( $v[ $this->file ] ) ) {
				if ( ! is_readable( $this->file ) ) {
					return '';
				}
				$data              = get_file_data( $this->file, array( 'Version' => 'Version' ) );
				$v[ $this->file ] = isset( $data['Version'] ) ? (string) $data['Version'] : '';
			}
			return $v[ $this->file ];
		}

		/** scheme://host — the licence server compares activations in this form. */
		private function site_url() {
			$parts = wp_parse_url( home_url() );
			if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
				return '';
			}
			$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : 'https';
			return strtolower( $scheme ) . '://' . strtolower( $parts['host'] );
		}
	}

endif;
