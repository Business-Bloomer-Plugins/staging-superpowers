<?php
/**
 * Everything that stops a staging copy from talking to the outside world,
 * plus the visual reminder. Only loaded when the site is armed.
 *
 * Every feature is a runtime filter: nothing in the database (webhook status,
 * gateway settings, scheduled actions) is changed, so disarming restores the
 * store exactly as it was.
 */

defined( 'ABSPATH' ) || exit;

// Emails.
if ( 'block' === sspw_get( 'sspw_email_mode' ) ) {
	add_filter( 'pre_wp_mail', 'sspw_block_email', PHP_INT_MAX );
} elseif ( 'redirect' === sspw_get( 'sspw_email_mode' ) ) {
	add_filter( 'wp_mail', 'sspw_redirect_email', PHP_INT_MAX );
}

// Webhooks.
if ( 'yes' === sspw_get( 'sspw_webhooks' ) ) {
	add_filter( 'woocommerce_webhook_should_deliver', '__return_false', PHP_INT_MAX );
}

// HTTP firewall.
if ( 'yes' === sspw_get( 'sspw_http_firewall' ) ) {
	add_filter( 'pre_http_request', 'sspw_http_firewall', PHP_INT_MAX, 3 );
}

// Action Scheduler: zero allowed batches means every queue run (WP-Cron, async
// loopback, WP-CLI runner) exits before claiming anything. Running a single
// action from Tools > Scheduled Actions bypasses the queue, so it still works.
if ( 'yes' === sspw_get( 'sspw_freeze_actions' ) ) {
	add_filter( 'action_scheduler_queue_runner_concurrent_batches', '__return_zero', PHP_INT_MAX );
	add_filter( 'action_scheduler_allow_async_request_runner', '__return_false', PHP_INT_MAX );
}

// Look and feel.
if ( 'yes' === sspw_get( 'sspw_look' ) ) {
	add_action( 'admin_bar_menu', 'sspw_admin_bar_badge', 0 );
	add_action( 'wp_enqueue_scripts', 'sspw_admin_bar_style' );
	add_action( 'admin_enqueue_scripts', 'sspw_admin_bar_style' );
	add_filter( 'admin_title', 'sspw_admin_title' );
}

if ( 'yes' === sspw_get( 'sspw_noindex' ) ) {
	add_filter( 'wp_robots', 'sspw_no_robots', PHP_INT_MAX );
}

/**
 * Core's wp_robots_no_robots() keeps "follow" on public sites; staging should
 * not pass link equity anywhere either.
 */
function sspw_no_robots( $robots ) {
	unset( $robots['follow'] );
	$robots['noindex']  = true;
	$robots['nofollow'] = true;

	return $robots;
}

function sspw_email_recipient() {
	$to = sanitize_email( sspw_get( 'sspw_email_to' ) );

	return $to ? $to : get_option( 'admin_email' );
}

/**
 * Returning true tells wp_mail() the email was sent, so WooCommerce and other
 * plugins carry on as normal instead of logging failures.
 */
function sspw_block_email() {
	return true;
}

function sspw_redirect_email( $atts ) {
	$original = is_array( $atts['to'] ) ? implode( ', ', $atts['to'] ) : (string) $atts['to'];

	$atts['to']      = sspw_email_recipient();
	$atts['subject'] = sprintf( '[STAGING to %s] %s', $original, $atts['subject'] );
	$atts['headers'] = sspw_strip_cc_bcc( $atts['headers'] );

	return $atts;
}

function sspw_strip_cc_bcc( $headers ) {
	if ( empty( $headers ) ) {
		return $headers;
	}

	$is_string = ! is_array( $headers );
	$lines     = $is_string ? explode( "\n", str_replace( "\r\n", "\n", $headers ) ) : $headers;

	$lines = array_filter(
		$lines,
		function ( $line ) {
			return ! preg_match( '/^\s*b?cc\s*:/i', (string) $line );
		}
	);

	return $is_string ? implode( "\r\n", $lines ) : array_values( $lines );
}

function sspw_blocked_hosts() {
	$hosts = preg_split( '/\s+/', strtolower( (string) sspw_get( 'sspw_blocked_hosts' ) ) );

	return array_filter( array_map( 'trim', $hosts ) );
}

function sspw_http_firewall( $pre, $args, $url ) {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

	if ( '' === $host ) {
		return $pre;
	}

	foreach ( sspw_blocked_hosts() as $blocked ) {
		if ( $host === $blocked || str_ends_with( $host, '.' . $blocked ) ) {
			/* translators: %s: blocked host name */
			return new WP_Error( 'sspw_blocked', sprintf( __( 'Request to %s blocked by Staging Superpowers.', 'staging-superpowers-for-woocommerce' ), $host ) );
		}
	}

	return $pre;
}

function sspw_admin_bar_badge( $wp_admin_bar ) {
	$wp_admin_bar->add_node(
		array(
			'id'    => 'sspw-staging',
			'title' => esc_html__( 'STAGING', 'staging-superpowers-for-woocommerce' ),
			'href'  => current_user_can( 'manage_woocommerce' ) ? admin_url( 'admin.php?page=wc-settings&tab=sspw' ) : false,
		)
	);
}

function sspw_admin_bar_style() {
	if ( ! is_admin_bar_showing() ) {
		return;
	}

	wp_add_inline_style(
		'admin-bar',
		'html #wpadminbar{background:#c2410c}' .
		'html #wpadminbar #wp-admin-bar-sspw-staging>.ab-item{background:#7c2d12;color:#fff;font-weight:700;letter-spacing:.08em}'
	);
}

function sspw_admin_title( $title ) {
	return '[STAGING] ' . $title;
}
