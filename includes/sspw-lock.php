<?php
/**
 * Locks subscriptions copied from the live store, and their orders.
 *
 * Deleting a subscription or a customer on staging can make the payment
 * plugin tell the payment provider to remove the saved card, which the live
 * store still needs for renewals. Anything that existed when this plugin was
 * turned on (so came from the live store) cannot be deleted, trashed or saved
 * from its edit screen. Subscriptions created on staging for testing are not
 * locked. Only loaded when the site is armed.
 */

defined( 'ABSPATH' ) || exit;

if ( 'yes' === sspw_get( 'sspw_lock_subscriptions' ) ) {
	add_filter( 'woocommerce_pre_delete_order', 'sspw_lock_block_delete', 10, 2 );
	add_filter( 'woocommerce_pre_delete_subscription', 'sspw_lock_block_delete', 10, 2 );
	add_filter( 'pre_trash_post', 'sspw_lock_block_post', 10, 2 );
	add_filter( 'pre_delete_post', 'sspw_lock_block_post', 10, 2 );
	add_filter( 'map_meta_cap', 'sspw_lock_block_user_delete', 10, 4 );
	add_action( 'admin_init', 'sspw_lock_block_save', 1 );
	add_action( 'admin_notices', 'sspw_lock_notices' );
}

/**
 * Locked: a subscription, or an order belonging to one, that existed when the
 * plugin was turned on.
 */
function sspw_is_locked_order( $order ) {
	if ( ! $order instanceof WC_Abstract_Order || $order instanceof WC_Order_Refund ) {
		return false;
	}

	$armed_at = (int) get_option( 'sspw_armed_at' );
	$created  = $order->get_date_created();
	if ( $armed_at && $created && $created->getTimestamp() > $armed_at ) {
		return false;
	}

	if ( 'shop_subscription' === $order->get_type() ) {
		return true;
	}

	return function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order, 'any' );
}

function sspw_lock_remember_block() {
	set_transient( 'sspw_lock_blocked_' . get_current_user_id(), 1, MINUTE_IN_SECONDS );
}

function sspw_lock_block_delete( $check, $order ) {
	if ( sspw_is_locked_order( $order ) ) {
		sspw_lock_remember_block();
		return false;
	}

	return $check;
}

/**
 * With orders stored as posts, the orders list trashes and deletes the posts directly.
 */
function sspw_lock_block_post( $check, $post ) {
	if ( $post && in_array( $post->post_type, array( 'shop_order', 'shop_subscription' ), true ) && sspw_is_locked_order( wc_get_order( $post->ID ) ) ) {
		sspw_lock_remember_block();
		return false;
	}

	return $check;
}

/**
 * Deleting a customer also deletes their saved cards, which has the same effect
 * on the payment provider, so customers with a locked subscription stay.
 */
function sspw_lock_block_user_delete( $caps, $cap, $user_id, $args ) {
	if ( ! in_array( $cap, array( 'delete_user', 'remove_user' ), true ) || empty( $args[0] ) || ! wc_get_order_type( 'shop_subscription' ) ) {
		return $caps;
	}

	$query = array(
		'type'        => 'shop_subscription',
		'customer_id' => (int) $args[0],
		'status'      => 'any',
		'limit'       => 1,
		'return'      => 'ids',
	);

	$armed_at = (int) get_option( 'sspw_armed_at' );
	if ( $armed_at ) {
		$query['date_created'] = '<=' . $armed_at;
	}

	return wc_get_orders( $query ) ? array( 'do_not_allow' ) : $caps;
}

/**
 * The order being viewed or saved in the admin, whichever storage is in use.
 */
function sspw_lock_current_order() {
	// phpcs:disable WordPress.Security.NonceVerification -- only identifies the screen; nothing is saved here.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( in_array( $page, array( 'wc-orders', 'wc-orders--shop_subscription' ), true ) && isset( $_GET['id'] ) ) {
		$id = absint( $_GET['id'] );
	} elseif ( isset( $_REQUEST['post_ID'] ) ) {
		$id = absint( $_REQUEST['post_ID'] );
	} elseif ( isset( $_GET['post'] ) ) {
		$id = absint( $_GET['post'] );
	} elseif ( isset( $_POST['order_id'] ) ) {
		$id = absint( $_POST['order_id'] );
	} else {
		$id = 0;
	}
	// phpcs:enable

	return $id ? wc_get_order( $id ) : false;
}

/**
 * Stops the edit screen's save and the order item AJAX edits before WooCommerce
 * handles them. Viewing still works.
 */
function sspw_lock_block_save() {
	$order = sspw_lock_current_order();
	if ( ! $order || ! sspw_is_locked_order( $order ) ) {
		return;
	}

	$ajax_edits = array( 'woocommerce_add_order_item', 'woocommerce_save_order_items', 'woocommerce_remove_order_item', 'woocommerce_calc_line_taxes', 'woocommerce_add_coupon_discount', 'woocommerce_remove_order_coupon', 'woocommerce_add_order_fee', 'woocommerce_add_order_shipping', 'woocommerce_add_order_tax', 'woocommerce_remove_order_tax', 'woocommerce_refund_line_items' );
	$action     = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- refusing a request, nothing is saved.

	if ( wp_doing_ajax() ) {
		if ( in_array( $action, $ajax_edits, true ) ) {
			wp_send_json_error( array( 'error' => sspw_lock_message() ) );
		}
		return;
	}

	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && in_array( $action, array( 'editpost', 'edit_order', 'edit', '' ), true ) ) {
		sspw_lock_remember_block();
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}

function sspw_lock_message() {
	return __( 'This subscription (or its order) was copied from your live store, so Staging Superpowers locks it: deleting or changing it here could make your payment provider remove the customer\'s saved card and stop renewals on the live store. Subscriptions you create on this staging site are not locked.', 'staging-superpowers' );
}

function sspw_lock_notices() {
	$blocked = get_transient( 'sspw_lock_blocked_' . get_current_user_id() );
	if ( $blocked ) {
		delete_transient( 'sspw_lock_blocked_' . get_current_user_id() );
		echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'Nothing was changed.', 'staging-superpowers' ) . '</strong> ' . esc_html( sspw_lock_message() ) . '</p></div>';
		return;
	}

	$screen = get_current_screen();
	$order  = $screen && in_array( $screen->base, array( 'post', 'woocommerce_page_wc-orders', 'woocommerce_page_wc-orders--shop_subscription' ), true ) ? sspw_lock_current_order() : false;

	if ( $order && sspw_is_locked_order( $order ) ) {
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Locked:', 'staging-superpowers' ) . '</strong> ' . esc_html( sspw_lock_message() ) . ' ' . esc_html__( 'You can look, but changes here are not saved.', 'staging-superpowers' ) . '</p></div>';
	}
}
