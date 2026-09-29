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
 * The guard runs even without WooCommerce so the deploy notice always shows;
 * everything else needs WooCommerce.
 */
function sspw_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once SSPW_PLUGIN_DIR . 'includes/sspw-settings.php';
	require_once SSPW_PLUGIN_DIR . 'includes/sspw-troubleshoot.php';

	if ( sspw_is_armed() ) {
		require_once SSPW_PLUGIN_DIR . 'includes/sspw-safety.php';
		require_once SSPW_PLUGIN_DIR . 'includes/sspw-gateways.php';
		require_once SSPW_PLUGIN_DIR . 'includes/sspw-visitors.php';
		require_once SSPW_PLUGIN_DIR . 'includes/sspw-changelog.php';
		require_once SSPW_PLUGIN_DIR . 'includes/sspw-cache.php';
	}

	/**
	 * Fires once the core is loaded, so add-ons can build on it.
	 *
	 * @param bool $armed Whether the plugin is on for this site.
	 */
	do_action( 'sspw_loaded', sspw_is_armed() );
}
