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
		'sspw_email_mode'     => 'redirect',
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
			'desc'  => __( 'Applies to every email sent through wp_mail(), WooCommerce and other plugins included. CC and BCC headers are removed.', 'staging-superpowers-for-woocommerce' ),
			'id'    => 'sspw_emails',
		),
		array(
			'title'   => __( 'Outgoing emails', 'staging-superpowers-for-woocommerce' ),
			'id'      => 'sspw_email_mode',
			'type'    => 'select',
			'default' => 'redirect',
			'options' => array(
				'redirect' => __( 'Send all to one address', 'staging-superpowers-for-woocommerce' ),
				'block'    => __( 'Block all', 'staging-superpowers-for-woocommerce' ),
				'off'      => __( 'Send normally (not recommended)', 'staging-superpowers-for-woocommerce' ),
			),
		),
		array(
			'title'       => __( 'Send to', 'staging-superpowers-for-woocommerce' ),
			'id'          => 'sspw_email_to',
			'type'        => 'email',
			'default'     => '',
			'placeholder' => get_option( 'admin_email' ),
			'desc_tip'    => __( 'Leave empty to use the site admin email.', 'staging-superpowers-for-woocommerce' ),
		),
		array(
			'type' => 'sectionend',
			'id'   => 'sspw_emails',
		),

		array(
			'title' => __( 'Payments and integrations', 'staging-superpowers-for-woocommerce' ),
			'type'  => 'title',
			'id'    => 'sspw_integrations',
		),
		array(
			'title'   => __( 'Payment gateways', 'staging-superpowers-for-woocommerce' ),
			'desc'    => __( 'Hide every payment gateway at checkout and show the Staging Test Gateway instead', 'staging-superpowers-for-woocommerce' ),
			'id'      => 'sspw_gateways',
			'type'    => 'checkbox',
			'default' => 'yes',
		),
		array(
			'title'   => __( 'Webhooks', 'staging-superpowers-for-woocommerce' ),
			'desc'    => __( 'Stop all WooCommerce webhooks from being delivered', 'staging-superpowers-for-woocommerce' ),
			'id'      => 'sspw_webhooks',
			'type'    => 'checkbox',
			'default' => 'yes',
		),
		array(
			'title'   => __( 'HTTP firewall', 'staging-superpowers-for-woocommerce' ),
			'desc'    => __( 'Block outgoing requests to the hosts below', 'staging-superpowers-for-woocommerce' ),
			'id'      => 'sspw_http_firewall',
			'type'    => 'checkbox',
			'default' => 'yes',
		),
		array(
			'title'             => __( 'Blocked hosts', 'staging-superpowers-for-woocommerce' ),
			'desc'              => __( 'One host per line. Subdomains are blocked too, so api.mailchimp.com also blocks us1.api.mailchimp.com. Blocking payment APIs also stops refunds from the order screen reaching the real payment account.', 'staging-superpowers-for-woocommerce' ),
			'id'                => 'sspw_blocked_hosts',
			'type'              => 'textarea',
			'default'           => implode( "\n", sspw_default_blocked_hosts() ),
			'css'               => 'min-width:400px;height:220px;font-family:monospace;',
			'custom_attributes' => array( 'spellcheck' => 'false' ),
		),
		array(
			'title'   => __( 'Scheduled actions', 'staging-superpowers-for-woocommerce' ),
			'desc'    => __( 'Freeze the Action Scheduler queue (subscription renewals, follow-up emails, syncs). You can still run a single action by hand from Tools > Scheduled Actions.', 'staging-superpowers-for-woocommerce' ),
			'id'      => 'sspw_freeze_actions',
			'type'    => 'checkbox',
			'default' => 'yes',
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
			'title'   => __( 'Staging look', 'staging-superpowers-for-woocommerce' ),
			'desc'    => __( 'Orange admin bar with a STAGING badge, and a [STAGING] prefix on admin page titles', 'staging-superpowers-for-woocommerce' ),
			'id'      => 'sspw_look',
			'type'    => 'checkbox',
			'default' => 'yes',
		),
		array(
			'title'   => __( 'Search engines', 'staging-superpowers-for-woocommerce' ),
			'desc'    => __( 'Add noindex, nofollow to every page', 'staging-superpowers-for-woocommerce' ),
			'id'      => 'sspw_noindex',
			'type'    => 'checkbox',
			'default' => 'yes',
		),
		array(
			'type' => 'sectionend',
			'id'   => 'sspw_look_section',
		),
	);
}

function sspw_output_settings() {
	woocommerce_admin_fields( sspw_settings_fields() );
}

function sspw_save_settings() {
	woocommerce_update_options( sspw_settings_fields() );
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
