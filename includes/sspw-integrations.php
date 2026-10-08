<?php
/**
 * Popular plugins that talk to live services by other routes than email or a
 * blocked web address: publishing pings, Jetpack, scheduled backups, payment
 * test modes and analytics. Each one uses that plugin's own switch, so nothing
 * is changed in the database. Only loaded when the site is armed.
 */

defined( 'ABSPATH' ) || exit;

if ( 'yes' === sspw_get( 'sspw_http_firewall' ) ) {
	// Pingbacks, trackbacks and update-service pings (Ping-O-Matic by default)
	// announce new posts to other sites. Core does the same outside production
	// since WordPress 7.1; this covers older versions and sites set to production.
	add_filter( 'wp_should_disable_pings_for_environment', '__return_true' );
	add_action( 'do_all_pings', 'sspw_stop_pings', 1, 0 );

	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- each plugin's own hook.

	// Jetpack's safe mode is meant for copies of a site: it stops syncing to
	// WordPress.com, which is also how Jetpack Social shares new posts.
	add_filter( 'jetpack_is_in_safe_mode', '__return_true' );

	// UpdraftPlus: scheduled backups would upload to, and prune, the live
	// site's remote storage. Backups started by hand still run.
	add_filter( 'updraftplus_boot_backup', 'sspw_no_scheduled_updraftplus', 10, 3 );

	// WP Fusion's staging mode: no contact updates, tags or tracking reach the CRM.
	add_filter( 'wpf_get_setting_staging_mode', '__return_true', PHP_INT_MAX );

	// Payment test modes.
	add_filter( 'edd_is_test_mode', '__return_true' );
	add_filter( 'give_is_test_mode', '__return_true' );
	add_filter( 'pre_option_pmpro_gateway_environment', 'sspw_pmpro_sandbox' );
	add_filter( 'rcp_is_sandbox', '__return_true' );
	add_filter( 'option_fluent_cart_store_settings', 'sspw_fluentcart_test_mode' );

	// Product, inventory and order syncs would change the live catalog and orders.
	add_filter( 'wc_facebook_is_product_sync_enabled', '__return_false' );
	add_filter( 'woocommerce_gla_ready_for_syncing', '__return_false' );
	add_filter( 'wc_square_inventory_sync_enabled', '__return_false' );
	add_filter( 'wc_square_order_fulfillment_sync_enabled', '__return_false' );

	// phpcs:enable
}

if ( 'yes' === sspw_get( 'sspw_gateways' ) ) {
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- each plugin's own hook.

	// WooPayments and Mollie: test mode, so refunds and captures done here never touch real money.
	add_filter( 'wcpay_test_mode', '__return_true' );
	add_filter( 'pre_option_mollie-payments-for-woocommerce_test_mode_enabled', 'sspw_yes' );

	// phpcs:enable
}

if ( 'off' !== sspw_email_status() ) {
	// MailPoet newsletters can go out through its own sending service, not the
	// WordPress email function. An empty batch means nobody gets them.
	add_filter( 'mailpoet_sending_queue_subscribers_to_process', '__return_empty_array' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- MailPoet's hook.
}

if ( 'yes' === sspw_get( 'sspw_no_analytics' ) ) {
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- each plugin's own hook.

	// Site Kit by Google.
	foreach ( array( 'analytics-4', 'tagmanager', 'ads', 'adsense' ) as $sspw_module ) {
		add_filter( 'googlesitekit_' . $sspw_module . '_tag_blocked', '__return_true' );
		add_filter( 'googlesitekit_' . $sspw_module . '_tag_amp_blocked', '__return_true' );
	}

	// MonsterInsights.
	add_filter( 'monsterinsights_skip_tracking', '__return_true' );

	// GTM4WP: its own switch for staging copies.
	add_filter( 'gtm4wp_output_container', '__return_false' );

	// Meta pixel for WordPress: its Conversions API sends events with its own
	// code, so the blocked services list cannot stop it.
	add_filter( 'before_conversions_api_event_sent', '__return_empty_array' );

	// Facebook for WooCommerce, Google for WooCommerce, Pinterest for WooCommerce.
	add_filter( 'facebook_for_woocommerce_integration_pixel_enabled', '__return_false' );
	add_filter( 'woocommerce_gla_disable_gtag_tracking', '__return_true' );
	add_filter( 'woocommerce_pinterest_disable_tracking', '__return_true' );

	// Klaviyo: its onsite script and the Started Checkout event.
	add_filter( 'wck_should_add_started_checkout', '__return_false' );
	add_action( 'wp_enqueue_scripts', 'sspw_no_klaviyo_js', PHP_INT_MAX );

	// phpcs:enable
}

function sspw_stop_pings() {
	remove_action( 'do_all_pings', 'do_all_pingbacks' );
	remove_action( 'do_all_pings', 'do_all_trackbacks' );
	remove_action( 'do_all_pings', 'generic_ping' );
}

/**
 * Scheduled backups pass true/false for files and database; "Backup Now"
 * passes numbers, so it still works.
 */
function sspw_no_scheduled_updraftplus( $start, $files, $database ) {
	return ( is_bool( $files ) || is_bool( $database ) ) ? false : $start;
}

function sspw_pmpro_sandbox() {
	return 'sandbox';
}

function sspw_yes() {
	return 'yes';
}

function sspw_fluentcart_test_mode( $settings ) {
	if ( is_array( $settings ) ) {
		$settings['order_mode'] = 'test';
	}

	return $settings;
}

/**
 * Klaviyo's other scripts depend on klaviyojs, which would bring it back, so
 * they are dropped too.
 */
function sspw_no_klaviyo_js() {
	$scripts = wp_scripts();
	$drop    = array( 'klaviyojs' );

	foreach ( $scripts->queue as $handle ) {
		if ( isset( $scripts->registered[ $handle ] ) && array_intersect( $drop, (array) $scripts->registered[ $handle ]->deps ) ) {
			$drop[] = $handle;
		}
	}

	foreach ( $drop as $handle ) {
		wp_dequeue_script( $handle );
	}
}
