<?php
/**
 * Staging Test Gateway, and hiding every other gateway at checkout.
 * Only loaded when the site is armed, so the test gateway can never
 * accept free orders on a live store.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'woocommerce_payment_gateways', 'sspw_register_test_gateway' );
add_action( 'woocommerce_blocks_payment_method_type_registration', 'sspw_register_test_gateway_blocks' );

if ( 'yes' === sspw_get( 'sspw_gateways' ) ) {
	add_filter( 'woocommerce_available_payment_gateways', 'sspw_only_test_gateway', PHP_INT_MAX );

	// WooCommerce Subscriptions' own staging mode: renewals are created but never
	// charged automatically, even if someone unfreezes scheduled actions. Hiding
	// gateways at checkout alone would not stop a renewal charging the saved card.
	add_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_true', PHP_INT_MAX ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce Subscriptions' own hook.
}

function sspw_register_test_gateway( $gateways ) {
	require_once SSPW_PLUGIN_DIR . 'includes/class-sspw-test-gateway.php';
	$gateways[] = 'SSPW_Test_Gateway';

	return $gateways;
}

function sspw_register_test_gateway_blocks( $registry ) {
	require_once SSPW_PLUGIN_DIR . 'includes/class-sspw-test-gateway-blocks.php';
	$registry->register( new SSPW_Test_Gateway_Blocks() );
}

/**
 * The block checkout reads this same list (Store API cart "payment_methods"),
 * so one filter covers classic and block checkout.
 */
function sspw_only_test_gateway( $gateways ) {
	return array_intersect_key( $gateways, array( 'sspw_test' => true ) );
}
