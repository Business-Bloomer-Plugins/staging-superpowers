<?php
/**
 * Settings tab at WooCommerce > Settings > Staging Superpowers.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'woocommerce_settings_tabs_array', 'sspw_add_settings_tab', 50 );
add_action( 'woocommerce_settings_tabs_sspw', 'sspw_output_settings' );
add_action( 'woocommerce_update_options_sspw', 'sspw_save_settings' );
add_filter( 'plugin_action_links_' . plugin_basename( SSPW_PLUGIN_FILE ), 'sspw_plugin_action_links' );

function sspw_default_blocked_hosts() {
	return array(
		'api.stripe.com',
		'api.paypal.com',
		'api-m.paypal.com',
		'api.braintreegateway.com',
		'connect.squareup.com',
		'api.mollie.com',
		'api.authorize.net',
		'api.mailchimp.com',
		'a.klaviyo.com',
		'api.brevo.com',
		'api.sendinblue.com',
		'connect.mailerlite.com',
		'api.omnisend.com',
		'api.kit.com',
		'api.convertkit.com',
		'api.hubapi.com',
		'ssapi.shipstation.com',
		'api.taxjar.com',
		'rest.avatax.com',
		'graph.facebook.com',
		'www.google-analytics.com',
		'region1.google-analytics.com',
		// Where automation workflows send SMS, chat messages, sheets and webhooks.
		'api.twilio.com',
		'slack.com',
		'hooks.zapier.com',
		'make.com',
		'integromat.com',
		'api-us1.com',
		'api.createsend.com',
		'api.getdrip.com',
		'api.sendgrid.com',
		'mailgun.net',
		'api.postmarkapp.com',
		'sheets.googleapis.com',
	);
}

/**
 * Same defaults for the settings screen and for runtime, so a freshly armed site
 * is fully protected before anyone opens the settings.
 */
function sspw_get( $key ) {
	$defaults = array(
		'sspw_email_mode'         => 'block',
		'sspw_email_to'           => '',
		'sspw_gateways'           => 'yes',
		'sspw_webhooks'           => 'yes',
		'sspw_http_firewall'      => 'yes',
		'sspw_blocked_hosts'      => implode( "\n", sspw_default_blocked_hosts() ),
		'sspw_freeze_actions'     => 'yes',
		'sspw_freeze_cron'        => 'yes',
		'sspw_lock_subscriptions' => 'yes',
		'sspw_look'               => 'yes',
		'sspw_noindex'            => 'yes',
		'sspw_visitors'           => 'redirect',
		'sspw_no_cache'           => 'yes',
		'sspw_live_url'           => '',
	);

	return get_option( $key, isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );
}

/**
 * What actually happens to emails. Forwarding without a valid address falls
 * back to blocking, so a half-finished setup never lets emails through.
 */
function sspw_email_status() {
	$mode = sspw_get( 'sspw_email_mode' );

	if ( 'redirect' === $mode ) {
		return is_email( sspw_get( 'sspw_email_to' ) ) ? 'redirect' : 'redirect-no-address';
	}

	return 'off' === $mode ? 'off' : 'block';
}

/**
 * The live store's address, or '' if unset, invalid, or pointing at this site.
 */
function sspw_live_url() {
	$url  = untrailingslashit( esc_url_raw( trim( (string) sspw_get( 'sspw_live_url' ) ) ) );
	$host = wp_parse_url( $url, PHP_URL_HOST );

	if ( ! $host || strtolower( $host ) === strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
		return '';
	}

	return $url;
}

/**
 * A best guess at the live store's address, from traces that survive cloning:
 * WooCommerce Subscriptions stores it scrambled so search and replace cannot
 * rewrite it, and migration tools leave post GUIDs untouched. Only ever shown as
 * a suggestion; nothing uses it until an admin saves it.
 */
function sspw_detect_live_url() {
	$here       = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$candidates = array();

	$wcs = (string) get_option( 'wc_subscriptions_siteurl', '' );
	if ( '' !== $wcs ) {
		$candidates[] = str_replace( '_[wc_subscriptions_siteurl]_', '', $wcs );
	}

	$hosts = array();
	$posts = get_posts(
		array(
			'post_type'   => array( 'post', 'page', 'product' ),
			'post_status' => 'publish',
			'numberposts' => 50,
			'orderby'     => 'ID',
			'order'       => 'DESC',
		)
	);
	foreach ( $posts as $post ) {
		// Only default GUIDs (?p=123) reliably hold the site address they were created on.
		if ( preg_match( '#^https?://([^/?]+)(/[^?]*)?\?(p|page_id|post_type)=#i', $post->guid, $m ) && strtolower( $m[1] ) !== $here ) {
			$base           = 'https://' . strtolower( $m[1] ) . ( isset( $m[2] ) ? untrailingslashit( $m[2] ) : '' );
			$hosts[ $base ] = isset( $hosts[ $base ] ) ? $hosts[ $base ] + 1 : 1;
		}
	}
	if ( $hosts ) {
		arsort( $hosts );
		$candidates[] = key( $hosts );
	}

	$candidates[] = (string) get_option( 'milo_subscriptions_production_url', '' );

	foreach ( $candidates as $url ) {
		$url  = untrailingslashit( esc_url_raw( trim( $url ) ) );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( $host && $host !== $here ) {
			return $url;
		}
	}

	return '';
}

/**
 * Who gets past the visitor page: anyone who works on the site, not shoppers.
 */
/**
 * The URL being viewed. REQUEST_URI already includes any subfolder the site
 * lives in, so only the scheme, host and port come from the site address.
 */
function sspw_current_url() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	$home = wp_parse_url( home_url() );
	$port = isset( $home['port'] ) ? ':' . $home['port'] : '';

	return $home['scheme'] . '://' . $home['host'] . $port . $uri;
}

/**
 * The path after the site address, so the same screen can be opened on another copy.
 */
function sspw_current_path() {
	$home = (string) wp_parse_url( home_url(), PHP_URL_PATH );
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

	return ( '' !== $home && 0 === strpos( $uri, $home ) ) ? substr( $uri, strlen( untrailingslashit( $home ) ) ) : $uri;
}

function sspw_can_see_store() {
	/**
	 * Whether the current visitor sees this staging copy instead of the visitor protection.
	 *
	 * @param bool $can Staff (can edit content or manage the store) always can.
	 */
	return (bool) apply_filters( 'sspw_can_see_store', current_user_can( 'edit_posts' ) || current_user_can( 'manage_woocommerce' ) );
}

function sspw_add_settings_tab( $tabs ) {
	$tabs['sspw'] = apply_filters( 'sspw_tab_label', __( 'Staging Superpowers', 'staging-superpowers-for-woocommerce' ) );

	return $tabs;
}

function sspw_status_text() {
	if ( sspw_is_armed() ) {
		/* translators: %s: site URL */
		return sprintf( __( 'Active on %s. Everything below applies to this site only; if the database is moved to another URL, the plugin pauses itself.', 'staging-superpowers-for-woocommerce' ), '<code>' . esc_html( sspw_site_fingerprint() ) . '</code>' );
	}

	return __( 'Paused. See the notice at the top of the page. These settings do nothing until the plugin is turned on for this URL.', 'staging-superpowers-for-woocommerce' );
}

/**
 * Admin screens the settings and the status bar link to.
 */
function sspw_admin_links() {
	return array(
		'settings'        => admin_url( 'admin.php?page=wc-settings&tab=sspw' ),
		'payments'        => admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
		'gateway'         => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=sspw_test' ),
		'webhooks'        => admin_url( 'admin.php?page=wc-settings&tab=advanced&section=webhooks' ),
		'actions'         => admin_url( 'admin.php?page=wc-status&tab=action-scheduler&status=pending' ),
		'emails'          => admin_url( 'admin.php?page=wc-settings&tab=email' ),
		'reading'         => admin_url( 'options-reading.php' ),
		'troubleshooting' => admin_url( 'admin.php?page=wc-settings&tab=sspw&section=troubleshooting' ),
	);
}

function sspw_link( $key, $text ) {
	$links = sspw_admin_links();

	return sprintf( '<a href="%s">%s</a>', esc_url( $links[ $key ] ), esc_html( $text ) );
}

/**
 * Pre-filled with the detected address until one is saved, so the admin only has
 * to check it and save.
 */
function sspw_live_url_field() {
	$field = array(
		'title'       => __( 'Live store address', 'staging-superpowers-for-woocommerce' ),
		'desc'        => __( 'Where your real store is. Visitors to this copy get a button to it, and admin screens get a link to open the same screen there.', 'staging-superpowers-for-woocommerce' ),
		'id'          => 'sspw_live_url',
		'type'        => 'url',
		'default'     => '',
		'placeholder' => 'https://www.example.com',
	);

	if ( false === get_option( 'sspw_live_url', false ) ) {
		$detected = sspw_detect_live_url();
		if ( $detected ) {
			$field['default'] = $detected;
			$field['desc']   .= ' <strong>' . esc_html__( 'We filled this in from your store data. Check it is right, then click Save changes to use it.', 'staging-superpowers-for-woocommerce' ) . '</strong>';
		}
	}

	return $field;
}

function sspw_visitors_description() {
	$login = wp_login_url();

	$text = sprintf(
		/* translators: %s: login page link */
		__( 'For customers and logged-out visitors, so nobody browses this copy or places orders that never reach your live store. You, and anyone who can edit the site, see this copy as normal once logged in. To log in, go to %s: that page is never redirected. Browsers you have logged in with here are remembered, so when you are logged out they go to the login page instead of the live store.', 'staging-superpowers-for-woocommerce' ),
		'<a href="' . esc_url( $login ) . '">' . esc_html( preg_replace( '#^https?://#', '', $login ) ) . '</a>'
	);

	if ( '' === sspw_live_url() ) {
		$text .= ' <strong>' . esc_html__( 'Save your live store address above to send visitors there. Until then, they see the "this is a staging site" page instead.', 'staging-superpowers-for-woocommerce' ) . '</strong>';
	}

	return $text;
}

function sspw_settings_fields() {
	return array(
		array(
			'title' => __( 'Status', 'staging-superpowers-for-woocommerce' ),
			'type'  => 'title',
			'desc'  => sspw_status_text(),
			'id'    => 'sspw_status',
		),
		array(
			'type' => 'sectionend',
			'id'   => 'sspw_status',
		),

		array(
			'title' => __( 'Your live store', 'staging-superpowers-for-woocommerce' ),
			'type'  => 'title',
			'id'    => 'sspw_live_section',
		),
		sspw_live_url_field(),
		array(
			'type' => 'sectionend',
			'id'   => 'sspw_live_section',
		),

		array(
			'title' => __( 'Emails', 'staging-superpowers-for-woocommerce' ),
			'type'  => 'title',
			'desc'  => sprintf(
				/* translators: %s: link to the WooCommerce emails settings */
				__( 'Your staging copy has real customer email addresses on every order and account. This stops order updates, password resets, newsletters and any other email from reaching them. It covers every email the site sends, including the %s. Anyone copied in (CC or BCC) is removed too.', 'staging-superpowers-for-woocommerce' ),
				sspw_link( 'emails', __( 'WooCommerce emails', 'staging-superpowers-for-woocommerce' ) )
			),
			'id'    => 'sspw_emails',
		),
		array(
			'title'   => __( 'Outgoing emails', 'staging-superpowers-for-woocommerce' ),
			'id'      => 'sspw_email_mode',
			'type'    => 'select',
			'default' => 'block',
			'options' => array(
				'block'    => __( 'Block all (recommended)', 'staging-superpowers-for-woocommerce' ),
				'redirect' => __( 'Forward all to one address', 'staging-superpowers-for-woocommerce' ),
				'off'      => __( 'Send normally (not safe)', 'staging-superpowers-for-woocommerce' ),
			),
		),
		array(
			'title'       => __( 'Forward to', 'staging-superpowers-for-woocommerce' ),
			'desc'        => __( 'Every email goes to this address instead, with the original recipient added to the subject line. Until you enter an address, emails stay blocked.', 'staging-superpowers-for-woocommerce' ),
			'id'          => 'sspw_email_to',
			'type'        => 'email',
			'default'     => '',
			'placeholder' => 'you@example.com',
		),
		array(
			'type' => 'sectionend',
			'id'   => 'sspw_emails',
		),

		array(
			'title' => __( 'Payments and connected services', 'staging-superpowers-for-woocommerce' ),
			'type'  => 'title',
			'id'    => 'sspw_integrations',
		),
		array(
			'title'    => __( 'Payment methods', 'staging-superpowers-for-woocommerce' ),
			'desc'     => __( 'Hide every payment method at checkout and show the Staging Test Gateway instead', 'staging-superpowers-for-woocommerce' ),
			'desc_tip' => sprintf(
				/* translators: 1: link to payment methods, 2: link to the test gateway settings */
				__( 'Your staging copy still has your live Stripe, PayPal or WooPayments keys, so a test order could charge a real card. With this on, only the Staging Test Gateway shows at checkout: it pretends to take the payment and never touches real money. Your %1$s settings are not changed. You can choose whether test payments succeed, wait or fail in the %2$s.', 'staging-superpowers-for-woocommerce' ),
				sspw_link( 'payments', __( 'payment methods', 'staging-superpowers-for-woocommerce' ) ),
				sspw_link( 'gateway', __( 'Staging Test Gateway settings', 'staging-superpowers-for-woocommerce' ) )
			),
			'id'       => 'sspw_gateways',
			'type'     => 'checkbox',
			'default'  => 'yes',
		),
		array(
			'title'    => __( 'Subscriptions', 'staging-superpowers-for-woocommerce' ),
			'desc'     => __( 'Lock subscriptions copied from the live store', 'staging-superpowers-for-woocommerce' ),
			'desc_tip' => __( 'Deleting a subscription or a customer on staging can make your payment plugin tell Stripe (or PayPal, Square...) to remove the customer\'s saved card. The live store uses that same card for renewals, so they would start failing. With this on, subscriptions and their orders copied from the live store cannot be deleted, trashed or changed here, and neither can the customers who own them. Subscriptions you create on this staging site for testing are not locked.', 'staging-superpowers-for-woocommerce' ),
			'id'       => 'sspw_lock_subscriptions',
			'type'     => 'checkbox',
			'default'  => 'yes',
		),
		array(
			'title'    => __( 'Webhooks', 'staging-superpowers-for-woocommerce' ),
			'desc'     => __( 'Pause all webhooks', 'staging-superpowers-for-woocommerce' ),
			'desc_tip' => sprintf(
				/* translators: %s: link to the webhooks list */
				__( 'A webhook is an automatic message your store sends to another app when something happens, for example "new order received" to your fulfillment, accounting or CRM software. The staging copy has the same webhooks as your live store, so test orders would show up in those apps as if they were real. This stops the messages without changing or deleting your %s.', 'staging-superpowers-for-woocommerce' ),
				sspw_link( 'webhooks', __( 'webhooks', 'staging-superpowers-for-woocommerce' ) )
			),
			'id'       => 'sspw_webhooks',
			'type'     => 'checkbox',
			'default'  => 'yes',
		),
		array(
			'title'    => __( 'Connected services', 'staging-superpowers-for-woocommerce' ),
			'desc'     => __( 'Block the site from contacting the services listed below', 'staging-superpowers-for-woocommerce' ),
			'desc_tip' => __( 'Many plugins talk to outside services in the background: payment processors, email marketing, shipping, tax and tracking tools. On a staging copy they still use your live accounts, so a test could refund a real payment from the order screen, add a test customer to your mailing list, or send a fake order to your shipping software. This firewall stops the site from connecting to those services. Everything else keeps working.', 'staging-superpowers-for-woocommerce' ),
			'id'       => 'sspw_http_firewall',
			'type'     => 'checkbox',
			'default'  => 'yes',
		),
		array(
			'title'             => __( 'Blocked services', 'staging-superpowers-for-woocommerce' ),
			'desc'              => __( 'One web address per line. The list already covers common payment, email marketing, shipping, tax and tracking services. Add any other service your store is connected to. Entering api.mailchimp.com also blocks addresses ending in it, such as us1.api.mailchimp.com.', 'staging-superpowers-for-woocommerce' ),
			'id'                => 'sspw_blocked_hosts',
			'type'              => 'textarea',
			'default'           => implode( "\n", sspw_default_blocked_hosts() ),
			'css'               => 'min-width:400px;height:220px;font-family:monospace;',
			'custom_attributes' => array( 'spellcheck' => 'false' ),
		),
		array(
			'title'    => __( 'Scheduled actions', 'staging-superpowers-for-woocommerce' ),
			'desc'     => __( 'Freeze scheduled actions', 'staging-superpowers-for-woocommerce' ),
			'desc_tip' => sprintf(
				/* translators: %s: link to pending scheduled actions */
				__( 'WooCommerce and many plugins keep a list of jobs to run later, like subscription renewals, follow-up emails, automation workflows (AutomateWoo, for example) and syncs with other apps. The staging copy has the same list as your live store, so it would renew subscriptions and repeat those jobs a second time. This holds every job on the list. You can still run a single one by hand from the %s.', 'staging-superpowers-for-woocommerce' ),
				sspw_link( 'actions', __( 'pending scheduled actions', 'staging-superpowers-for-woocommerce' ) )
			),
			'id'       => 'sspw_freeze_actions',
			'type'     => 'checkbox',
			'default'  => 'yes',
		),
		array(
			'title'    => __( 'WP-Cron', 'staging-superpowers-for-woocommerce' ),
			'desc'     => __( 'Freeze WP-Cron tasks', 'staging-superpowers-for-woocommerce' ),
			'desc_tip' => __( 'Some plugins, including older automation and follow-up tools, run their jobs with the WordPress scheduler (WP-Cron) instead. This holds those too, so nothing runs by itself on this copy. Nothing is deleted: the tasks run again as soon as you turn this off. Developers can still run a single task with WP-CLI (wp cron event run).', 'staging-superpowers-for-woocommerce' ),
			'id'       => 'sspw_freeze_cron',
			'type'     => 'checkbox',
			'default'  => 'yes',
		),
		array(
			'type' => 'sectionend',
			'id'   => 'sspw_integrations',
		),

		array(
			'title' => __( 'Look and feel', 'staging-superpowers-for-woocommerce' ),
			'type'  => 'title',
			'id'    => 'sspw_look_section',
		),
		array(
			'title'   => __( 'Visitors', 'staging-superpowers-for-woocommerce' ),
			'desc'    => sspw_visitors_description(),
			'id'      => 'sspw_visitors',
			'type'    => 'select',
			'css'     => 'min-width:440px;',
			'default' => 'redirect',
			'options' => array(
				'redirect' => __( 'Send them to the same page on the live store (recommended)', 'staging-superpowers-for-woocommerce' ),
				'lock'     => __( 'Show a "this is a staging site" page', 'staging-superpowers-for-woocommerce' ),
				'bar'      => __( 'Show the site, with a STAGING bar on every page', 'staging-superpowers-for-woocommerce' ),
				'off'      => __( 'Show the site as normal (not safe)', 'staging-superpowers-for-woocommerce' ),
			),
		),
		array(
			'title'    => __( 'Staging look', 'staging-superpowers-for-woocommerce' ),
			'desc'     => __( 'Make it obvious this is the staging site', 'staging-superpowers-for-woocommerce' ),
			'desc_tip' => __( 'Turns the admin bar orange with a STAGING badge, adds a status bar under it showing which protections are on, puts [STAGING] in front of admin page titles so browser tabs are easy to tell apart, and shows a reminder when you edit products, pages, coupons, menus or WooCommerce settings, so changes meant for the live store are not made here by mistake.', 'staging-superpowers-for-woocommerce' ),
			'id'       => 'sspw_look',
			'type'     => 'checkbox',
			'default'  => 'yes',
		),
		array(
			'title'    => __( 'Search engines', 'staging-superpowers-for-woocommerce' ),
			'desc'     => __( 'Hide this site from search engines', 'staging-superpowers-for-woocommerce' ),
			'desc_tip' => sprintf(
				/* translators: %s: link to Settings > Reading */
				__( 'Tells Google and other search engines not to list any page of this copy, so it never competes with your live store. This works on its own, whatever is set in %s.', 'staging-superpowers-for-woocommerce' ),
				sspw_link( 'reading', __( 'Settings > Reading', 'staging-superpowers-for-woocommerce' ) )
			),
			'id'       => 'sspw_noindex',
			'type'     => 'checkbox',
			'default'  => 'yes',
		),
		array(
			'title'    => __( 'Page caching', 'staging-superpowers-for-woocommerce' ),
			'desc'     => __( 'Turn off page caching on this site', 'staging-superpowers-for-woocommerce' ),
			'desc_tip' => __( 'Cache plugins save copies of your pages and show those instead of the real page. On staging that means you do not see your changes, and visitors could see a saved page instead of being sent to the live store. This tells cache plugins (WP Rocket, W3 Total Cache, LiteSpeed Cache, WP Super Cache and others) and your host not to save pages, and empties their saved pages once. Their own settings are not changed.', 'staging-superpowers-for-woocommerce' ),
			'id'       => 'sspw_no_cache',
			'type'     => 'checkbox',
			'default'  => 'yes',
		),
		array(
			'type' => 'sectionend',
			'id'   => 'sspw_look_section',
		),
	);
}

/**
 * The forwarding address only matters in "Forward all" mode, so it is hidden otherwise.
 */
function sspw_output_settings() {
	global $current_section;

	if ( 'troubleshooting' === $current_section ) {
		sspw_output_troubleshooting();
		return;
	}

	if ( 'changelog' === $current_section ) {
		sspw_output_changelog();
		return;
	}

	if ( '' !== (string) $current_section ) {
		$GLOBALS['hide_save_button'] = true; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce's own flag for hiding the Save button.

		/**
		 * Output for a sub-page added through sspw_settings_sections.
		 */
		do_action( 'sspw_output_section_' . sanitize_key( $current_section ) );
		return;
	}

	woocommerce_admin_fields( sspw_settings_fields() );

	sspw_inline_script(
		"var sspwMode = jQuery( '#sspw_email_mode' ), sspwTo = jQuery( '#sspw_email_to' ).closest( 'tr' );
		function sspwToggleTo() { sspwTo.toggle( 'redirect' === sspwMode.val() ); }
		sspwMode.on( 'change', sspwToggleTo );
		sspwToggleTo();"
	);
}

function sspw_save_settings() {
	global $current_section;

	if ( '' === $current_section ) {
		woocommerce_update_options( sspw_settings_fields() );
	}
}

function sspw_plugin_action_links( $links ) {
	array_unshift(
		$links,
		sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wc-settings&tab=sspw' ) ),
			esc_html__( 'Settings', 'staging-superpowers-for-woocommerce' )
		)
	);

	return $links;
}

/**
 * Inline script printed in the admin footer (wc_enqueue_js() is deprecated
 * since WooCommerce 10.4). Safe to call while the page body renders.
 */
function sspw_inline_script( $js ) {
	if ( ! wp_script_is( 'sspw-inline', 'registered' ) ) {
		wp_register_script( 'sspw-inline', false, array( 'jquery' ), SSPW_VERSION, true );
	}

	wp_enqueue_script( 'sspw-inline' );
	wp_add_inline_script( 'sspw-inline', 'jQuery( function () { ' . $js . ' } );' );
}
