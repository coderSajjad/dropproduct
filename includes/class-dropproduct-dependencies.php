<?php
/**
 * WooCommerce dependency gate.
 *
 * DropProduct is built entirely on WooCommerce APIs — products, orders,
 * gateways — so it cannot do anything useful without it. Rather than fail with
 * a bare red error, this class works out exactly which of the four states the
 * site is in and shows a friendly notice with the one button that resolves it:
 *
 *   ok       — WooCommerce active and new enough; the plugin boots normally.
 *   missing  — not installed at all      → "Install WooCommerce"
 *   inactive — installed but deactivated → "Activate WooCommerce"
 *   outdated — active but below minimum  → "Update WooCommerce"
 *
 * @package DropProduct
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DropProduct_Dependencies
 *
 * @since 1.2.0
 */
class DropProduct_Dependencies {

	/**
	 * Minimum supported WooCommerce version.
	 *
	 * Keep in sync with the "WC requires at least" plugin header.
	 *
	 * @var string
	 */
	const MIN_WC_VERSION = '6.0';

	/**
	 * Canonical WooCommerce plugin file, as installed from wordpress.org.
	 *
	 * @var string
	 */
	const WC_PLUGIN_FILE = 'woocommerce/woocommerce.php';

	/**
	 * Option storing the timestamp until which the notice stays hidden.
	 *
	 * @var string
	 */
	const SNOOZE_OPTION = 'dropproduct_wc_notice_snoozed_until';

	/**
	 * Query arg used by the dismiss link.
	 *
	 * @var string
	 */
	const DISMISS_ARG = 'dropproduct_dismiss_wc_notice';

	/**
	 * Transient set on activation so the first notice can be worded differently.
	 *
	 * @var string
	 */
	const JUST_ACTIVATED = 'dropproduct_just_activated';

	/**
	 * Memoised status so repeated calls in one request stay cheap.
	 *
	 * @var string|null
	 */
	private static $status = null;

	// ──────────────────────────────────────────
	//  Bootstrap
	// ──────────────────────────────────────────

	/**
	 * Register the notice and its dismiss handler.
	 *
	 * Only called when the dependency is not satisfied.
	 */
	public static function register_notices() {
		add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_dismiss' ) );
		add_filter( 'plugin_action_links_' . DROPPRODUCT_PLUGIN_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Runs on plugin activation.
	 *
	 * Activation deliberately still succeeds when WooCommerce is absent —
	 * bailing out here would just produce WordPress's opaque "Plugin could not
	 * be activated" screen. The plugin sits inert instead and explains itself
	 * through the admin notice, which can offer a one-click fix.
	 */
	public static function on_activate() {
		set_transient( self::JUST_ACTIVATED, 1, MINUTE_IN_SECONDS * 5 );

		// A fresh activation should always surface the notice, even if the user
		// snoozed it during a previous install.
		delete_option( self::SNOOZE_OPTION );
	}

	// ──────────────────────────────────────────
	//  Status
	// ──────────────────────────────────────────

	/**
	 * Whether WooCommerce is present, active, and new enough.
	 *
	 * @return bool
	 */
	public static function is_satisfied() {
		return 'ok' === self::get_status();
	}

	/**
	 * Determine the current WooCommerce state.
	 *
	 * @return string One of: ok, missing, inactive, outdated.
	 */
	public static function get_status() {
		if ( null !== self::$status ) {
			return self::$status;
		}

		if ( self::woocommerce_is_loaded() ) {
			$version = defined( 'WC_VERSION' ) ? WC_VERSION : null;

			// An unknown version is treated as acceptable: better to run than to
			// lock someone out over a constant a fork might not define.
			self::$status = ( $version && version_compare( $version, self::MIN_WC_VERSION, '<' ) )
				? 'outdated'
				: 'ok';

			return self::$status;
		}

		self::$status = self::get_wc_plugin_file() ? 'inactive' : 'missing';

		return self::$status;
	}

	/**
	 * Whether WooCommerce is actually loaded and running.
	 *
	 * Deliberately tests for the WC() function rather than the WooCommerce
	 * class. PHP never autoloads functions, whereas class_exists() invokes every
	 * registered autoloader — and on a site where another plugin's autoloader
	 * can resolve a "WooCommerce" class, class_exists() returns true even though
	 * WooCommerce is deactivated. That false positive would let DropProduct boot
	 * and then fatal on the first wc_* call.
	 *
	 * WC() is defined in WooCommerce's main plugin file and invoked at include
	 * time, so it is reliably available well before `plugins_loaded` fires.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	private static function woocommerce_is_loaded() {
		return function_exists( 'WC' );
	}

	/**
	 * Locate the installed WooCommerce plugin file.
	 *
	 * Checks the canonical path first, then scans installed plugins so a
	 * WooCommerce installed into a non-standard directory is still found.
	 *
	 * @return string|false Plugin file relative to the plugins directory, or false.
	 */
	public static function get_wc_plugin_file() {
		if ( file_exists( WP_PLUGIN_DIR . '/' . self::WC_PLUGIN_FILE ) ) {
			return self::WC_PLUGIN_FILE;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			if ( ! is_admin() ) {
				return false;
			}
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( get_plugins() as $file => $data ) {
			if ( isset( $data['TextDomain'] ) && 'woocommerce' === $data['TextDomain'] ) {
				return $file;
			}
			if ( isset( $data['Name'] ) && 'WooCommerce' === $data['Name'] ) {
				return $file;
			}
		}

		return false;
	}

	// ──────────────────────────────────────────
	//  Notice
	// ──────────────────────────────────────────

	/**
	 * Whether the notice should be displayed on the current screen.
	 *
	 * @return bool
	 */
	private static function should_render() {
		// Only people who could actually resolve it need to see it.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return false;
		}

		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id = $screen ? $screen->id : '';

		// The Plugins screen is where someone goes to fix this, so the notice is
		// never suppressed there regardless of snoozing.
		if ( 'plugins' === $screen_id || 'plugins-network' === $screen_id ) {
			return true;
		}

		$snoozed_until = (int) get_option( self::SNOOZE_OPTION, 0 );

		return $snoozed_until <= time();
	}

	/**
	 * Render the dependency notice.
	 */
	public static function render_notice() {
		if ( ! self::should_render() ) {
			return;
		}

		$status = self::get_status();

		if ( 'ok' === $status ) {
			return;
		}

		$just_activated = (bool) get_transient( self::JUST_ACTIVATED );
		delete_transient( self::JUST_ACTIVATED );

		$copy = self::get_notice_copy( $status, $just_activated );

		if ( empty( $copy ) ) {
			return;
		}

		$action = self::get_primary_action( $status );
		?>
		<div class="notice notice-warning dropproduct-dependency-notice">
			<p style="font-size:14px;margin-bottom:6px;">
				<strong><?php echo esc_html( $copy['title'] ); ?></strong>
			</p>
			<p style="margin-top:0;">
				<?php echo esc_html( $copy['body'] ); ?>
			</p>
			<?php if ( $action ) : ?>
				<p>
					<a href="<?php echo esc_url( $action['url'] ); ?>" class="button button-primary">
						<?php echo esc_html( $action['label'] ); ?>
					</a>
					<a href="<?php echo esc_url( self::get_dismiss_url() ); ?>"
					   class="button button-link"
					   style="margin-left:8px;color:#646970;text-decoration:none;">
						<?php esc_html_e( 'Remind me later', 'dropproduct' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Notice wording for a given state.
	 *
	 * @param string $status         One of: missing, inactive, outdated.
	 * @param bool   $just_activated Whether DropProduct was activated moments ago.
	 * @return array{title: string, body: string}|array Empty array when nothing to say.
	 */
	private static function get_notice_copy( $status, $just_activated ) {
		$title = $just_activated
			? __( 'DropProduct is installed — one more step to go.', 'dropproduct' )
			: __( 'DropProduct is paused.', 'dropproduct' );

		switch ( $status ) {
			case 'missing':
				return array(
					'title' => $title,
					'body'  => __( 'DropProduct builds WooCommerce products, so it needs WooCommerce installed to do anything. Install it and DropProduct will start up on its own — your settings and any products you have already created are kept safe in the meantime.', 'dropproduct' ),
				);

			case 'inactive':
				return array(
					'title' => $title,
					'body'  => __( 'WooCommerce is installed but not active. DropProduct will start up automatically as soon as you activate it — nothing else to configure.', 'dropproduct' ),
				);

			case 'outdated':
				$installed = defined( 'WC_VERSION' ) ? WC_VERSION : __( 'unknown', 'dropproduct' );

				return array(
					'title' => __( 'DropProduct needs a newer WooCommerce.', 'dropproduct' ),
					'body'  => sprintf(
						/* translators: 1: minimum required WooCommerce version, 2: currently installed version */
						__( 'DropProduct requires WooCommerce %1$s or newer, and this site is running %2$s. Updating WooCommerce will bring DropProduct back online.', 'dropproduct' ),
						self::MIN_WC_VERSION,
						$installed
					),
				);
		}

		return array();
	}

	/**
	 * Build the primary call-to-action for a given state.
	 *
	 * Each action is capability-gated, and falls back to the WooCommerce plugin
	 * listing when the user cannot perform the operation directly.
	 *
	 * @param string $status One of: missing, inactive, outdated.
	 * @return array{url: string, label: string}|false
	 */
	private static function get_primary_action( $status ) {
		$wc_file = self::get_wc_plugin_file();

		switch ( $status ) {
			case 'missing':
				if ( current_user_can( 'install_plugins' ) ) {
					return array(
						'url'   => wp_nonce_url(
							self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ),
							'install-plugin_woocommerce'
						),
						'label' => __( 'Install WooCommerce', 'dropproduct' ),
					);
				}

				return array(
					'url'   => self_admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ),
					'label' => __( 'Find WooCommerce', 'dropproduct' ),
				);

			case 'inactive':
				if ( $wc_file && current_user_can( 'activate_plugins' ) ) {
					return array(
						'url'   => wp_nonce_url(
							self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $wc_file ) ),
							'activate-plugin_' . $wc_file
						),
						'label' => __( 'Activate WooCommerce', 'dropproduct' ),
					);
				}

				return array(
					'url'   => self_admin_url( 'plugins.php' ),
					'label' => __( 'Go to Plugins', 'dropproduct' ),
				);

			case 'outdated':
				if ( $wc_file && current_user_can( 'update_plugins' ) ) {
					return array(
						'url'   => wp_nonce_url(
							self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $wc_file ) ),
							'upgrade-plugin_' . $wc_file
						),
						'label' => __( 'Update WooCommerce', 'dropproduct' ),
					);
				}

				return array(
					'url'   => self_admin_url( 'plugins.php' ),
					'label' => __( 'Go to Plugins', 'dropproduct' ),
				);
		}

		return false;
	}

	// ──────────────────────────────────────────
	//  Dismissal
	// ──────────────────────────────────────────

	/**
	 * URL that snoozes the notice.
	 *
	 * @return string
	 */
	private static function get_dismiss_url() {
		return wp_nonce_url(
			add_query_arg( self::DISMISS_ARG, 1 ),
			self::DISMISS_ARG
		);
	}

	/**
	 * Handle the snooze link.
	 *
	 * Snoozes rather than dismisses permanently: the plugin genuinely cannot
	 * work until this is resolved, so going quiet forever would leave the user
	 * wondering why nothing happens.
	 */
	public static function handle_dismiss() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified immediately below.
		if ( empty( $_GET[ self::DISMISS_ARG ] ) ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::DISMISS_ARG ) ) {
			return;
		}

		/**
		 * Filter how long the WooCommerce dependency notice stays hidden.
		 *
		 * @since 1.2.0
		 * @param int $seconds Snooze duration. Default one week.
		 */
		$period = (int) apply_filters( 'dropproduct_wc_notice_snooze_period', WEEK_IN_SECONDS );

		update_option( self::SNOOZE_OPTION, time() + $period, false );

		wp_safe_redirect(
			remove_query_arg( array( self::DISMISS_ARG, '_wpnonce' ) )
		);
		exit;
	}

	// ──────────────────────────────────────────
	//  Plugins screen
	// ──────────────────────────────────────────

	/**
	 * Add a corrective action link to DropProduct's row on the Plugins screen.
	 *
	 * This is where someone lands after seeing "Plugin activated" with nothing
	 * appearing in the menu, so the fix should be reachable right there.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$action = self::get_primary_action( self::get_status() );

		if ( ! $action ) {
			return $links;
		}

		$link = sprintf(
			'<a href="%s" style="color:#b32d2e;font-weight:600;">%s</a>',
			esc_url( $action['url'] ),
			esc_html( $action['label'] )
		);

		array_unshift( $links, $link );

		return $links;
	}
}
