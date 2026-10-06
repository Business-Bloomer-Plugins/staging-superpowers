<?php
/**
 * Bootstrap shared by this plugin and Business Bloomer WooCommerce Staging Toolkit.
 */

defined( 'ABSPATH' ) || exit;

function sspw_declare_compatibility() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SSPW_PLUGIN_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', SSPW_PLUGIN_FILE, true );
	}
}

/**
 * During a troubleshooting check only, adds the file a fatal error came from to
 * WordPress's error page, so the check can name the plugin or theme.
 */
function sspw_mark_fatal_error( $message, $error ) {
	$key = (string) get_transient( 'sspw_check_key' );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- compared with a one-time key.
	$given = isset( $_GET['sspw_check'] ) ? sanitize_text_field( wp_unslash( $_GET['sspw_check'] ) ) : '';

	if ( '' !== $key && hash_equals( $key, $given ) && ! empty( $error['file'] ) ) {
		$message .= '<!-- sspw-fatal-file: ' . esc_html( $error['file'] ) . ' -->';
	}

	return $message;
}

function sspw_has_woocommerce() {
	return class_exists( 'WooCommerce' );
}

/**
 * Action Scheduler ships with WooCommerce and with many other plugins
 * (email marketing, backups, SEO), so it is checked on its own.
 */
function sspw_has_action_scheduler() {
	return class_exists( 'ActionScheduler' );
}

/**
 * The guard is loaded by the main file so the deploy notice always shows.
 * WooCommerce parts only load when WooCommerce is active.
 */
function sspw_init() {
	require_once SSPW_PLUGIN_DIR . 'includes/sspw-settings.php';
	require_once SSPW_PLUGIN_DIR . 'includes/sspw-email-check.php';
	require_once SSPW_PLUGIN_DIR . 'includes/sspw-troubleshoot.php';
	require_once SSPW_PLUGIN_DIR . 'includes/sspw-changelog-page.php';
	require_once SSPW_PLUGIN_DIR . 'includes/sspw-requests.php';
	require_once SSPW_PLUGIN_DIR . 'includes/sspw-emails-log.php';

	if ( sspw_is_armed() ) {
		require_once SSPW_PLUGIN_DIR . 'includes/sspw-safety.php';
		require_once SSPW_PLUGIN_DIR . 'includes/sspw-visitors.php';
		require_once SSPW_PLUGIN_DIR . 'includes/sspw-changelog.php';
		require_once SSPW_PLUGIN_DIR . 'includes/sspw-cache.php';
		require_once SSPW_PLUGIN_DIR . 'includes/sspw-integrations.php';

		if ( sspw_has_woocommerce() ) {
			require_once SSPW_PLUGIN_DIR . 'includes/sspw-woocommerce.php';
			require_once SSPW_PLUGIN_DIR . 'includes/sspw-gateways.php';
			require_once SSPW_PLUGIN_DIR . 'includes/sspw-lock.php';
		}
	}

	/**
	 * Fires once the core is loaded, so add-ons can build on it.
	 *
	 * @param bool $armed Whether the plugin is on for this site.
	 */
	do_action( 'sspw_loaded', sspw_is_armed() );
}
