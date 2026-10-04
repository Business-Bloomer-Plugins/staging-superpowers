<?php
/**
 * WooCommerce parts that are not about payments or subscriptions: webhooks,
 * the Store API for visitors, and the WooCommerce settings and checkout
 * changes in the changelog. Only loaded when the site is armed and
 * WooCommerce is active.
 */

defined( 'ABSPATH' ) || exit;

if ( 'yes' === sspw_get( 'sspw_webhooks' ) ) {
	add_filter( 'woocommerce_webhook_should_deliver', '__return_false', PHP_INT_MAX );
}

if ( 'lock' === sspw_get( 'sspw_visitors' ) ) {
	add_filter( 'rest_pre_dispatch', 'sspw_visitor_store_api', 10, 3 );
}

add_action( 'check_admin_referer', 'sspw_log_settings_start', 10, 2 );
add_action( 'woocommerce_update_options', 'sspw_log_settings_end', PHP_INT_MAX );
add_action( 'post_updated', 'sspw_log_checkout_switch', 10, 3 );

/**
 * The cart and checkout blocks talk to the Store API directly, so the visitor
 * page alone would not stop a determined shopper (or bot) from ordering.
 */
function sspw_visitor_store_api( $result, $server, $request ) {
	if ( 0 === strpos( $request->get_route(), '/wc/store' ) && ! sspw_can_see_store() ) {
		return new WP_Error( 'sspw_staging_site', __( 'This is a staging site. Orders are not accepted.', 'staging-superpowers' ), array( 'status' => 503 ) );
	}

	return $result;
}

/*
 * WooCommerce settings. WooCommerce checks its settings nonce right before saving,
 * so every option written between that check and the end of the save is a
 * setting the user just changed.
 */

function sspw_log_settings_start( $action, $result ) {
	global $sspw_settings_changes;

	if ( 'woocommerce-settings' !== $action || ! $result || null !== $sspw_settings_changes ) {
		return;
	}

	$sspw_settings_changes = array();
	add_action( 'updated_option', 'sspw_collect_setting', 10, 3 );
	add_action( 'added_option', 'sspw_collect_new_setting', 10, 2 );
}

function sspw_collect_setting( $option, $old, $value ) {
	global $sspw_settings_changes;

	if ( $old !== $value && is_array( $sspw_settings_changes ) ) {
		$sspw_settings_changes[ $option ] = array( $old, $value );
	}
}

function sspw_collect_new_setting( $option, $value ) {
	sspw_collect_setting( $option, null, $value );
}

function sspw_log_settings_end() {
	global $sspw_settings_changes, $current_tab, $current_section;

	remove_action( 'updated_option', 'sspw_collect_setting', 10 );
	remove_action( 'added_option', 'sspw_collect_new_setting', 10 );

	if ( empty( $sspw_settings_changes ) ) {
		$sspw_settings_changes = null;
		return;
	}

	$fields = sspw_settings_fields_for( $current_tab, $current_section );
	$tabs   = apply_filters( 'woocommerce_settings_tabs_array', array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter.
	$where  = isset( $tabs[ $current_tab ] ) ? $tabs[ $current_tab ] : $current_tab;

	sspw_log_changes( $where, $sspw_settings_changes, $fields );

	$sspw_settings_changes = null;
}

/**
 * The fields shown on a WooCommerce settings screen, keyed by option name.
 */
function sspw_settings_fields_for( $tab, $section ) {
	$list = array();

	if ( class_exists( 'WC_Admin_Settings' ) ) {
		foreach ( WC_Admin_Settings::get_settings_pages() as $page ) {
			if ( $page instanceof WC_Settings_Page && $page->get_id() === $tab ) {
				$list = $page->get_settings_for_section( (string) $section );
				break;
			}
		}
	}

	$fields = array();
	foreach ( (array) $list as $field ) {
		if ( ! empty( $field['id'] ) && isset( $field['type'] ) && ! in_array( $field['type'], array( 'title', 'sectionend' ), true ) ) {
			$fields[ preg_replace( '/\[.*$/', '', $field['id'] ) ] = $field;
		}
	}

	return $fields;
}

/**
 * Cart and checkout can use blocks or the classic shortcode, and switching is a
 * change that is easy to forget on the live store.
 */
function sspw_log_checkout_switch( $post_id, $after, $before ) {
	$pages = array(
		wc_get_page_id( 'checkout' ) => array( 'woocommerce/checkout', __( 'Checkout', 'staging-superpowers' ) ),
		wc_get_page_id( 'cart' )     => array( 'woocommerce/cart', __( 'Cart', 'staging-superpowers' ) ),
	);

	if ( ! isset( $pages[ $post_id ] ) ) {
		return;
	}

	list( $block, $name ) = $pages[ $post_id ];
	$was_block            = has_block( $block, $before->post_content );
	$is_block             = has_block( $block, $after->post_content );

	if ( $was_block !== $is_block ) {
		sspw_log(
			$is_block
				/* translators: %s: Cart or Checkout */
				? sprintf( __( '%s page switched from classic to blocks', 'staging-superpowers' ), $name )
				/* translators: %s: Cart or Checkout */
				: sprintf( __( '%s page switched from blocks to classic', 'staging-superpowers' ), $name )
		);
	}
}
