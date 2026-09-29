<?php
/**
 * Settings tab at WooCommerce > Settings > Staging.
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
	);
}

/**
 * Same defaults for the settings screen and for runtime, so a freshly armed site
 * is fully protected before anyone opens the settings.
 */
function sspw_get( $key ) {
	$defaults = array(
		'sspw_email_mode'     => 'block',
		'sspw_email_to'       => '',
		'sspw_gateways'       => 'yes',
		'sspw_webhooks'       => 'yes',
		'sspw_http_firewall'  => 'yes',
		'sspw_blocked_hosts'  => implode( "\n", sspw_default_blocked_hosts() ),
		'sspw_freeze_actions' => 'yes',
		'sspw_look'           => 'yes',
		'sspw_noindex'        => 'yes',
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

function sspw_add_settings_tab( $tabs ) {
	$tabs['sspw'] = __( 'Staging', 'staging-superpowers-for-woocommerce' );

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

function sspw_settings_fields() {
	return array(
		array(
			'title' => __( 'Staging Superpowers', 'staging-superpowers-for-woocommerce' ),
			'type'  => 'title',
			'desc'  => sspw_status_text(),
			'id'    => 'sspw_status',
		),
		array(
			'type' => 'sectionend',
			'id'   => 'sspw_status',
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
				__( 'WooCommerce and many plugins keep a list of jobs to run later, like subscription renewals, follow-up emails and syncs with other apps. The staging copy has the same list as your live store, so it would renew subscriptions and repeat those jobs a second time. This holds every job on the list. You can still run a single one by hand from the %s.', 'staging-superpowers-for-woocommerce' ),
				sspw_link( 'actions', __( 'pending scheduled actions', 'staging-superpowers-for-woocommerce' ) )
			),
			'id'       => 'sspw_freeze_actions',
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
			'title'    => __( 'Staging look', 'staging-superpowers-for-woocommerce' ),
			'desc'     => __( 'Make it obvious this is the staging site', 'staging-superpowers-for-woocommerce' ),
			'desc_tip' => __( 'Turns the admin bar orange with a STAGING badge, adds a status bar under it showing which protections are on, and puts [STAGING] in front of admin page titles so browser tabs are easy to tell apart.', 'staging-superpowers-for-woocommerce' ),
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

	woocommerce_admin_fields( sspw_settings_fields() );

	wc_enqueue_js(
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
