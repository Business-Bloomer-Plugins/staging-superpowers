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
	require_once SSPW_PLUGIN_DIR . 'includes/sspw-plugin-check.php';
	require_once SSPW_PLUGIN_DIR . 'includes/sspw-troubleshoot.php';
	require_once SSPW_PLUGIN_DIR . 'includes/sspw-changelog-page.php';
	require_once SSPW_PLUGIN_DIR . 'includes/sspw-link-check.php';

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
