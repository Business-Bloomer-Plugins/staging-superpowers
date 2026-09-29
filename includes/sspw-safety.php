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
if ( 'redirect' === sspw_email_status() ) {
	add_filter( 'wp_mail', 'sspw_redirect_email', PHP_INT_MAX );
} elseif ( 'off' !== sspw_email_status() ) {
	add_filter( 'pre_wp_mail', 'sspw_block_email', PHP_INT_MAX );
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

	// Actions piling up is the point while frozen, not a fault worth warning about.
	add_filter( 'action_scheduler_check_pastdue_actions', '__return_false', PHP_INT_MAX );
}

// Look and feel.
if ( 'yes' === sspw_get( 'sspw_look' ) ) {
	add_action( 'admin_bar_menu', 'sspw_admin_bar_badge', 0 );
	add_action( 'wp_after_admin_bar_render', 'sspw_status_bar' );
	add_action( 'wp_enqueue_scripts', 'sspw_admin_bar_style' );
	add_action( 'admin_enqueue_scripts', 'sspw_admin_bar_style' );
	add_filter( 'admin_title', 'sspw_admin_title' );
	add_action( 'admin_notices', 'sspw_editing_staging_notice' );
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

/**
 * Returning true tells wp_mail() the email was sent, so WooCommerce and other
 * plugins carry on as normal instead of logging failures.
 */
function sspw_block_email() {
	return true;
}

function sspw_redirect_email( $atts ) {
	$original = is_array( $atts['to'] ) ? implode( ', ', $atts['to'] ) : (string) $atts['to'];

	$atts['to']      = sanitize_email( sspw_get( 'sspw_email_to' ) );
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
			'href'  => current_user_can( 'manage_woocommerce' ) ? sspw_admin_links()['settings'] : false,
		)
	);
}

function sspw_can_see_status_bar() {
	return is_admin_bar_showing() && current_user_can( 'manage_woocommerce' );
}

/**
 * One entry per protection: is it on, what to call it, where to manage it.
 */
function sspw_status_items() {
	$links = sspw_admin_links();
	$email = sspw_email_status();

	$email_labels = array(
		'block'               => __( 'Emails blocked', 'staging-superpowers-for-woocommerce' ),
		'redirect'            => __( 'Emails forwarded', 'staging-superpowers-for-woocommerce' ),
		'redirect-no-address' => __( 'Email forwarding', 'staging-superpowers-for-woocommerce' ),
		'off'                 => __( 'Emails going to real people', 'staging-superpowers-for-woocommerce' ),
	);

	$email_tips = array(
		'block'               => __( 'No email leaves this site.', 'staging-superpowers-for-woocommerce' ),
		/* translators: %s: forwarding email address */
		'redirect'            => sprintf( __( 'Every email goes to %s.', 'staging-superpowers-for-woocommerce' ), sspw_get( 'sspw_email_to' ) ),
		'redirect-no-address' => __( 'No forwarding address set yet, so emails are blocked for now.', 'staging-superpowers-for-woocommerce' ),
		'off'                 => __( 'Emails are sent to real recipients.', 'staging-superpowers-for-woocommerce' ),
	);

	$items = array(
		array(
			'on'    => in_array( $email, array( 'block', 'redirect' ), true ),
			'label' => $email_labels[ $email ],
			'tip'   => $email_tips[ $email ],
			'url'   => $links['settings'],
		),
		array(
			'on'    => 'yes' === sspw_get( 'sspw_gateways' ),
			'label' => __( 'Payments hidden', 'staging-superpowers-for-woocommerce' ),
			'tip'   => __( 'Only the Staging Test Gateway shows at checkout.', 'staging-superpowers-for-woocommerce' ),
			'url'   => $links['payments'],
		),
		array(
			'on'    => 'yes' === sspw_get( 'sspw_webhooks' ),
			'label' => __( 'Webhooks paused', 'staging-superpowers-for-woocommerce' ),
			'tip'   => __( 'Other apps are not told about orders on this site.', 'staging-superpowers-for-woocommerce' ),
			'url'   => $links['webhooks'],
		),
		array(
			'on'    => 'yes' === sspw_get( 'sspw_http_firewall' ),
			'label' => __( 'Services blocked', 'staging-superpowers-for-woocommerce' ),
			'tip'   => __( 'Payment, marketing, shipping and tax services cannot be contacted.', 'staging-superpowers-for-woocommerce' ),
			'url'   => $links['settings'],
		),
		array(
			'on'    => 'yes' === sspw_get( 'sspw_freeze_actions' ),
			'label' => sspw_frozen_actions_label(),
			'tip'   => __( 'Renewals, follow-ups and syncs do not run by themselves.', 'staging-superpowers-for-woocommerce' ),
			'url'   => $links['actions'],
		),
		array(
			'on'    => 'yes' === sspw_get( 'sspw_no_cache' ),
			'label' => __( 'Page cache off', 'staging-superpowers-for-woocommerce' ),
			'tip'   => __( 'Pages are never served from a cache, so you always see your latest changes.', 'staging-superpowers-for-woocommerce' ),
			'url'   => $links['settings'],
		),
		array(
			'on'    => 'yes' === sspw_get( 'sspw_noindex' ),
			'label' => __( 'Hidden from Google', 'staging-superpowers-for-woocommerce' ),
			'tip'   => __( 'Search engines are asked not to list this site.', 'staging-superpowers-for-woocommerce' ),
			'url'   => $links['settings'],
		),
	);

	// Troubleshooting leftovers are easy to forget, so they stay visible until undone.
	$disabled = count( sspw_disabled_plugins() );
	if ( $disabled ) {
		$items[] = array(
			'warn'  => true,
			/* translators: %d: number of plugins */
			'label' => sprintf( _n( '%d plugin switched off', '%d plugins switched off', $disabled, 'staging-superpowers-for-woocommerce' ), $disabled ),
			'tip'   => __( 'Switched off for troubleshooting. Click to switch them back on.', 'staging-superpowers-for-woocommerce' ),
			'url'   => $links['troubleshooting'],
		);
	}

	if ( sspw_previous_theme() && sspw_previous_theme() !== get_stylesheet() ) {
		$items[] = array(
			'warn'  => true,
			'label' => __( 'Theme switched', 'staging-superpowers-for-woocommerce' ),
			'tip'   => __( 'Switched for troubleshooting. Click to switch back.', 'staging-superpowers-for-woocommerce' ),
			'url'   => $links['troubleshooting'],
		);
	}

	return $items;
}

function sspw_status_bar() {
	if ( ! sspw_can_see_status_bar() ) {
		return;
	}

	echo '<div id="sspw-status-bar" role="status">';

	foreach ( sspw_status_items() as $item ) {
		if ( ! empty( $item['warn'] ) ) {
			printf(
				'<a class="sspw-warn" href="%1$s" title="%2$s"><span class="sspw-mark" aria-hidden="true">&#9888;</span> %3$s</a>',
				esc_url( $item['url'] ),
				esc_attr( $item['tip'] ),
				esc_html( $item['label'] )
			);
			continue;
		}

		printf(
			'<a class="%1$s" href="%2$s" title="%3$s"><span class="sspw-mark" aria-hidden="true">%4$s</span> %5$s<span class="screen-reader-text"> (%6$s)</span></a>',
			$item['on'] ? 'sspw-on' : 'sspw-off',
			esc_url( $item['url'] ),
			esc_attr( $item['tip'] ),
			$item['on'] ? '&#10003;' : '&#10007;',
			esc_html( $item['label'] ),
			$item['on'] ? esc_html__( 'on', 'staging-superpowers-for-woocommerce' ) : esc_html__( 'off', 'staging-superpowers-for-woocommerce' )
		);
	}

	printf(
		'<a class="sspw-gear" href="%1$s" title="%2$s"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><span class="screen-reader-text">%2$s</span></a>',
		esc_url( sspw_admin_links()['settings'] ),
		esc_attr__( 'All settings', 'staging-superpowers-for-woocommerce' )
	);

	echo '</div>';

	// On phones, core lets the admin bar scroll away but some screens (WooCommerce's
	// own pages) keep it fixed, so the status bar copies whatever the admin bar does.
	wp_print_inline_script_tag(
		"( function () {
			var adminBar = document.getElementById( 'wpadminbar' ), statusBar = document.getElementById( 'sspw-status-bar' );
			function sspwFollowAdminBar() { statusBar.style.position = 'fixed' === getComputedStyle( adminBar ).position ? 'fixed' : 'absolute'; }
			sspwFollowAdminBar();
			window.addEventListener( 'resize', sspwFollowAdminBar );
		} )();"
	);
}

/**
 * The status bar sits fixed under the admin bar, so the page is pushed down by
 * its height too: html padding in the dashboard, html margin on the front end
 * (both mirror how core makes room for the admin bar itself).
 */
function sspw_admin_bar_style() {
	if ( ! is_admin_bar_showing() ) {
		return;
	}

	$css = 'html #wpadminbar{background:#c2410c}' .
		'html #wpadminbar #wp-admin-bar-sspw-staging>.ab-item{background:#7c2d12;color:#fff;font-weight:700;letter-spacing:.08em}' .
		'@media screen and (max-width:782px){html #wpadminbar li#wp-admin-bar-sspw-staging{display:block}html #wpadminbar #wp-admin-bar-sspw-staging>.ab-item{font-size:14px;padding:0 10px}}';

	if ( sspw_can_see_status_bar() ) {
		$css .= '#sspw-status-bar{position:fixed;top:32px;left:0;right:0;z-index:99998;height:28px;display:flex;align-items:center;gap:2px;padding:0 8px;box-sizing:border-box;background:#7c2d12;font:13px/28px -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;overflow-x:auto;white-space:nowrap}' .
			'#sspw-status-bar a{color:#fff;text-decoration:none;padding:0 8px;border-radius:3px;box-shadow:none}' .
			'#sspw-status-bar a:hover,#sspw-status-bar a:focus{background:rgba(255,255,255,.15);color:#fff}' .
			'#sspw-status-bar .sspw-mark{font-weight:700}' .
			'#sspw-status-bar .sspw-on .sspw-mark{color:#86efac}' .
			'#sspw-status-bar .sspw-off{color:#fecaca}' .
			'#sspw-status-bar .sspw-off .sspw-mark{color:#fca5a5}' .
			'#sspw-status-bar .sspw-warn{background:#fde68a;color:#7c2d12;font-weight:600;margin-left:6px}' .
			'#sspw-status-bar .sspw-warn:hover,#sspw-status-bar .sspw-warn:focus{background:#fcd34d;color:#7c2d12}' .
			'#sspw-status-bar .sspw-gear{margin-left:auto}' .
			'#sspw-status-bar .dashicons{font-size:18px;width:18px;height:18px;line-height:28px;vertical-align:top}';

		if ( is_admin() ) {
			$css .= 'html.wp-toolbar{padding-top:60px}' .
				'html.wp-toolbar .woocommerce-layout__header{top:60px}' .
				'@media screen and (max-width:782px){#sspw-status-bar{top:46px}html.wp-toolbar{padding-top:74px}html.wp-toolbar .woocommerce-layout__header{top:74px}}' .
				'@media screen and (max-width:600px){#sspw-status-bar{position:absolute}html.wp-toolbar{padding-top:0}#wpbody{padding-top:74px}}';
		} else {
			$css .= 'html:root{margin-top:60px !important}' .
				'@media screen and (max-width:782px){#sspw-status-bar{top:46px}html:root{margin-top:74px !important}}' .
				'@media screen and (max-width:600px){#sspw-status-bar{position:absolute}}';
		}
	}

	wp_add_inline_style( 'admin-bar', $css );
}

function sspw_admin_title( $title ) {
	return '[STAGING] ' . $title;
}

/**
 * Counting pending actions is one indexed query, cached briefly because the
 * status bar shows on every admin page.
 */
function sspw_frozen_actions_label() {
	if ( 'yes' !== sspw_get( 'sspw_freeze_actions' ) || ! class_exists( 'ActionScheduler' ) ) {
		return __( 'Scheduled actions frozen', 'staging-superpowers-for-woocommerce' );
	}

	$waiting = get_transient( 'sspw_pending_actions' );
	if ( false === $waiting ) {
		$waiting = (int) ActionScheduler::store()->query_actions( array( 'status' => ActionScheduler_Store::STATUS_PENDING ), 'count' );
		set_transient( 'sspw_pending_actions', $waiting, MINUTE_IN_SECONDS );
	}

	if ( ! $waiting ) {
		return __( 'Scheduled actions frozen', 'staging-superpowers-for-woocommerce' );
	}

	/* translators: %s: number of pending scheduled actions */
	return sprintf( __( 'Scheduled actions frozen (%s waiting)', 'staging-superpowers-for-woocommerce' ), number_format_i18n( $waiting ) );
}

/**
 * Screens where people change what customers see. On staging those changes are
 * easy to make by mistake and then lose, so each one gets a reminder.
 */
function sspw_is_merchandising_screen( $screen ) {
	if ( ! $screen ) {
		return false;
	}

	if ( in_array( $screen->base, array( 'post', 'edit', 'term', 'edit-tags' ), true ) && in_array( $screen->post_type, array( 'product', 'page', 'post', 'shop_coupon' ), true ) ) {
		return true;
	}

	if ( in_array( $screen->id, array( 'nav-menus', 'widgets', 'customize', 'site-editor', 'appearance_page_gutenberg-edit-site' ), true ) ) {
		return true;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of which settings tab is open.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

	return 'woocommerce_page_wc-settings' === $screen->id && 'sspw' !== $tab;
}

function sspw_editing_staging_notice() {
	if ( ! sspw_is_merchandising_screen( get_current_screen() ) ) {
		return;
	}

	$live = sspw_live_url();

	echo '<div class="notice notice-warning sspw-editing-notice"><p><strong>' . esc_html__( 'You are editing the STAGING site.', 'staging-superpowers-for-woocommerce' ) . '</strong> ';
	esc_html_e( 'Changes made here do not reach your live store.', 'staging-superpowers-for-woocommerce' );

	if ( $live ) {
		printf( ' <a href="%1$s">%2$s</a>', esc_url( $live . sspw_current_path() ), esc_html__( 'Open this screen on the live store', 'staging-superpowers-for-woocommerce' ) );
	}

	echo '</p></div>';
}
