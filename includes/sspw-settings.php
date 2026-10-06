<?php
/**
 * Settings page at Tools > Staging Superpowers, with sub-pages for
 * troubleshooting, the changelog and anything an add-on adds.
 */

defined( 'ABSPATH' ) || exit;

define( 'SSPW_SETTINGS_PAGE', 'staging-superpowers' );

add_action( 'admin_menu', 'sspw_add_settings_page' );
add_action( 'admin_init', 'sspw_save_settings' );
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
		// Covers www. and region1. (GA4 Measurement Protocol) and the older ssl. address.
		'google-analytics.com',
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
		// Email sending services that plugins may call directly.
		'api.sparkpost.com',
		'mandrillapp.com',
		'bridge.mailpoet.com',
		'api.mailjet.com',
		'api.resend.com',
		'api.mailersend.com',
		'api.smtp2go.com',
		'api.elasticemail.com',
		// Social sharing and push notifications.
		'api.twitter.com',
		'api.linkedin.com',
		'developer.blog2social.com',
		'blog2social-wordpress-api.adenion.de',
		'onesignal.com',
		'api.pushengage.com',
		'rpc.pingomatic.com',
		// Shared live services and automation platforms.
		'api.cloudflare.com',
		'api.automatorplugin.com',
		// Image optimization credits.
		'api.shortpixel.com',
		'smushpro.wpmudev.com',
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
		'sspw_block_bots'         => 'yes',
		'sspw_no_analytics'       => 'yes',
		'sspw_visitors'           => 'open',
		'sspw_message_title'      => '',
		'sspw_message_text'       => '',
		'sspw_no_cache'           => 'yes',
	);

	$value = get_option( $key, isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );

	// Free has two choices. An unknown value (older "send to live", or an add-on's
	// choice while that add-on is off) shows the message, so visitors stay out.
	if ( 'sspw_visitors' === $key && ! array_key_exists( $value, sspw_visitor_modes() ) ) {
		$value = in_array( $value, array( 'lock', 'redirect' ), true ) ? 'lock' : 'open';
	}

	// An empty message field falls back to the default text, translated only when needed.
	if ( in_array( $key, array( 'sspw_message_title', 'sspw_message_text' ), true ) && '' === trim( (string) $value ) ) {
		$value = 'sspw_message_title' === $key ? __( 'We\'ll be right back', 'staging-superpowers' ) : __( 'This site is under maintenance. Please check back soon.', 'staging-superpowers' );
	}

	return $value;
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
 * Who gets past the visitor protection: anyone who works on the site, not
 * customers, members or subscribers.
 */
function sspw_can_see_store() {
	$staff = current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' ) || ( sspw_has_woocommerce() && current_user_can( 'manage_woocommerce' ) );

	/**
	 * Whether the current visitor sees this staging copy instead of the visitor protection.
	 *
	 * @param bool $can Staff (can edit content or manage the site or store) always can.
	 */
	return (bool) apply_filters( 'sspw_can_see_store', $staff );
}

function sspw_settings_label() {
	/**
	 * Name of the settings page, in the Settings menu and as its heading.
	 *
	 * @param string $label Default "Staging Superpowers".
	 */
	return apply_filters( 'sspw_tab_label', __( 'Staging Superpowers', 'staging-superpowers' ) );
}

function sspw_settings_url( $section = '' ) {
	return admin_url( 'tools.php?page=' . SSPW_SETTINGS_PAGE . ( '' !== $section ? '&section=' . $section : '' ) );
}

/**
 * The sub-page being viewed: '' for the main settings.
 */
function sspw_current_section() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of which sub-page is open.
	return isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
}

/**
 * Whether the settings page is open, optionally on one sub-page. Works before
 * the admin screen is set up, so add-ons can use it to enqueue their assets.
 */
function sspw_is_settings_page( $section = null ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of which admin page is open.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	return is_admin() && SSPW_SETTINGS_PAGE === $page && ( null === $section || sspw_current_section() === $section );
}

function sspw_status_text() {
	if ( sspw_is_armed() ) {
		/* translators: %s: site URL */
		return sprintf( __( 'Protecting this staging site, %s. If this site is ever copied to another address, for example pushed back to your live site, the protection switches itself off there, so it never gets in the way on live.', 'staging-superpowers' ), '<code>' . esc_html( sspw_site_fingerprint() ) . '</code>' );
	}

	return __( 'Not protecting this site yet. See the notice at the top of the page: once you confirm this is a staging site, everything below starts working.', 'staging-superpowers' );
}

/**
 * Admin screens the settings and the status bar link to. The WooCommerce ones
 * are only there when WooCommerce is active.
 */
function sspw_admin_links() {
	$links = array(
		'settings'        => sspw_settings_url(),
		'troubleshooting' => sspw_settings_url( 'troubleshooting' ),
		'changelog'       => sspw_settings_url( 'changelog' ),
		'reading'         => admin_url( 'options-reading.php' ),
		'actions'         => sspw_settings_url(),
	);

	if ( sspw_has_woocommerce() ) {
		$links['payments'] = admin_url( 'admin.php?page=wc-settings&tab=checkout' );
		$links['gateway']  = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=sspw_test' );
		$links['webhooks'] = admin_url( 'admin.php?page=wc-settings&tab=advanced&section=webhooks' );
		$links['emails']   = admin_url( 'admin.php?page=wc-settings&tab=email' );
		$links['actions']  = admin_url( 'admin.php?page=wc-status&tab=action-scheduler&status=pending' );
	} elseif ( sspw_has_action_scheduler() ) {
		$links['actions'] = admin_url( 'tools.php?page=action-scheduler&status=pending' );
	}

	return $links;
}

function sspw_link( $key, $text ) {
	$links = sspw_admin_links();

	return sprintf( '<a href="%s">%s</a>', esc_url( $links[ $key ] ), esc_html( $text ) );
}

function sspw_visitors_description() {
	$login = wp_login_url();

	return sprintf(
		/* translators: %s: login page link */
		__( 'Who this applies to: anyone logged out, and accounts that cannot edit the site (customers, members, subscribers). Your team always sees the site once logged in at %s. Search engines are kept away either way.', 'staging-superpowers' ),
		'<a href="' . esc_url( $login ) . '">' . esc_html( preg_replace( '#^https?://#', '', $login ) ) . '</a>'
	);
}

function sspw_woocommerce_settings_fields() {
	return array(
		array(
			'title' => __( 'Protect your WooCommerce store', 'staging-superpowers' ),
			'type'  => 'title',
			'id'    => 'sspw_woocommerce',
		),
		array(
			'title'    => __( 'Payment methods', 'staging-superpowers' ),
			'desc'     => __( 'Hide every payment method at checkout and show the Staging Test Gateway instead', 'staging-superpowers' ),
			'desc_tip' => sprintf(
				/* translators: 1: link to payment methods, 2: link to the test gateway settings */
				__( 'Your staging copy still has your live Stripe, PayPal or WooPayments keys, so a test order could charge a real card. With this on, only the Staging Test Gateway shows at checkout: it pretends to take the payment and never touches real money. Your %1$s settings are not changed. You can choose whether test payments succeed, wait or fail in the %2$s.', 'staging-superpowers' ),
				sspw_link( 'payments', __( 'payment methods', 'staging-superpowers' ) ),
				sspw_link( 'gateway', __( 'Staging Test Gateway settings', 'staging-superpowers' ) )
			),
			'id'       => 'sspw_gateways',
			'type'     => 'checkbox',
			'default'  => 'yes',
		),
		array(
			'title'    => __( 'Subscriptions', 'staging-superpowers' ),
			'desc'     => __( 'Lock subscriptions copied from the live store', 'staging-superpowers' ),
			'desc_tip' => __( 'Deleting a subscription or a customer on staging can make your payment plugin tell Stripe (or PayPal, Square...) to remove the customer\'s saved card. The live store uses that same card for renewals, so they would start failing. With this on, subscriptions and their orders copied from the live store cannot be deleted, trashed or changed here, and neither can the customers who own them. Subscriptions you create on this staging site for testing are not locked.', 'staging-superpowers' ),
			'id'       => 'sspw_lock_subscriptions',
			'type'     => 'checkbox',
			'default'  => 'yes',
		),
		array(
			'title'    => __( 'Webhooks', 'staging-superpowers' ),
			'desc'     => __( 'Pause all webhooks', 'staging-superpowers' ),
			'desc_tip' => sprintf(
				/* translators: %s: link to the webhooks list */
				__( 'A webhook is an automatic message your store sends to another app when something happens, for example "new order received" to your fulfillment, accounting or CRM software. The staging copy has the same webhooks as your live store, so test orders would show up in those apps as if they were real. This stops the messages without changing or deleting your %s.', 'staging-superpowers' ),
				sspw_link( 'webhooks', __( 'webhooks', 'staging-superpowers' ) )
			),
			'id'       => 'sspw_webhooks',
			'type'     => 'checkbox',
			'default'  => 'yes',
		),
		array(
			'type' => 'sectionend',
			'id'   => 'sspw_woocommerce',
		),
	);
}

/**
 * Choices for what logged-out visitors see. "open" and "lock" are handled here;
 * an add-on that adds a choice handles it itself.
 */
function sspw_visitor_modes() {
	/**
	 * Visitor choices, value => label.
	 *
	 * @param array $modes Default "open" and "lock".
	 */
	return (array) apply_filters(
		'sspw_visitor_modes',
		array(
			'open' => __( 'Let everyone see the site', 'staging-superpowers' ),
			'lock' => __( 'Show a message instead', 'staging-superpowers' ),
		)
	);
}

function sspw_settings_fields() {
	$emails = __( 'Your staging copy has the real email addresses of your customers, members and users. This stops password resets, notifications, order updates, newsletters and any other email from reaching them. It covers every email the site sends. Anyone copied in (CC or BCC) is removed too.', 'staging-superpowers' );
	if ( sspw_has_woocommerce() ) {
		$emails .= ' ' . sprintf(
			/* translators: %s: link to the WooCommerce emails settings */
			__( 'That includes the %s.', 'staging-superpowers' ),
			sspw_link( 'emails', __( 'WooCommerce emails', 'staging-superpowers' ) )
		);
	}

	$fields = array(
		array(
			'title' => __( 'Protection status', 'staging-superpowers' ),
			'type'  => 'title',
			'desc'  => sspw_status_text(),
			'id'    => 'sspw_status',
		),
		array(
			'type' => 'sectionend',
			'id'   => 'sspw_status',
		),

		array(
			'title' => __( 'Staging look, search engines and page caching', 'staging-superpowers' ),
			'type'  => 'title',
			'id'    => 'sspw_look_section',
		),
		array(
			'title'    => __( 'Staging look', 'staging-superpowers' ),
			'desc'     => __( 'Make it obvious this is the staging site', 'staging-superpowers' ),
			'desc_tip' => __( 'Adds a red STAGING badge to the admin bar and a red status bar under it showing which protections are on, puts [STAGING] in front of admin page titles so browser tabs are easy to tell apart, and shows a reminder when you edit posts, pages, products, menus or store settings, so changes meant for the live site are not made here by mistake.', 'staging-superpowers' ),
			'id'       => 'sspw_look',
			'type'     => 'checkbox',
			'default'  => 'yes',
		),
		array(
			'title'    => __( 'Search engines', 'staging-superpowers' ),
			'desc'     => __( 'Hide this site from search engines', 'staging-superpowers' ),
			'desc_tip' => sprintf(
				/* translators: %s: link to Settings > Reading */
				__( 'Tells Google and other search engines not to list any page of this copy, so it never competes with your live site. This works on its own, whatever is set in %s.', 'staging-superpowers' ),
				sspw_link( 'reading', __( 'Settings > Reading', 'staging-superpowers' ) )
			),
			'id'       => 'sspw_noindex',
			'type'     => 'checkbox',
			'default'  => 'yes',
		),
		array(
			'title'    => __( 'Crawlers and AI bots', 'staging-superpowers' ),
			'desc'     => __( 'Block crawlers and AI bots', 'staging-superpowers' ),
			'desc_tip' => __( 'Asks every crawler to stay away in robots.txt, turns off the WordPress sitemaps, and refuses pages to known search and AI bots (Googlebot, Bingbot, GPTBot, ClaudeBot, PerplexityBot, CCBot and others) that come anyway. Your team, logged-in users and the site\'s own background tasks are never blocked.', 'staging-superpowers' ),
			'id'       => 'sspw_block_bots',
			'type'     => 'checkbox',
			'default'  => 'yes',
		),
		array(
			'title'    => __( 'Page caching', 'staging-superpowers' ),
			'desc'     => __( 'Turn off page caching on this site', 'staging-superpowers' ),
			'desc_tip' => __( 'Cache plugins save copies of your pages and show those instead of the real page. On staging that means you do not see your changes, and visitors could see a saved page instead of your message. This tells cache plugins (WP Rocket, W3 Total Cache, LiteSpeed Cache, WP Super Cache and others) and your host not to save pages, and empties their saved pages once. Their own settings are not changed.', 'staging-superpowers' ),
			'id'       => 'sspw_no_cache',
			'type'     => 'checkbox',
			'default'  => 'yes',
		),
		array(
			'type' => 'sectionend',
			'id'   => 'sspw_look_section',
		),

		array(
			'title' => __( 'What visitors see', 'staging-superpowers' ),
			'type'  => 'title',
			'desc'  => sspw_visitors_description(),
			'id'    => 'sspw_visitors_section',
		),
		array(
			'title'   => __( 'Visitors', 'staging-superpowers' ),
			'id'      => 'sspw_visitors',
			'type'    => 'select',
			'css'     => 'min-width:440px;',
			'default' => 'open',
			'options' => sspw_visitor_modes(),
		),
		array(
			'title' => __( 'Message heading', 'staging-superpowers' ),
			'id'    => 'sspw_message_title',
			'type'  => 'text',
		),
		array(
			'title' => __( 'Message', 'staging-superpowers' ),
			'desc'  => __( 'Shown on a plain page with your site name and a small Log in link. It never mentions staging, so customers are not confused.', 'staging-superpowers' ),
			'id'    => 'sspw_message_text',
			'type'  => 'textarea',
			'css'   => 'min-width:400px;height:80px;',
		),
		array(
			'type' => 'sectionend',
			'id'   => 'sspw_visitors_section',
		),

		array(
			'title' => __( 'Stop emails to real people', 'staging-superpowers' ),
			'type'  => 'title',
			'desc'  => $emails,
			'id'    => 'sspw_emails',
		),
		array(
			'title'   => __( 'Outgoing emails', 'staging-superpowers' ),
			'id'      => 'sspw_email_mode',
			'type'    => 'select',
			'default' => 'block',
			'options' => array(
				'block'    => __( 'Block all (recommended)', 'staging-superpowers' ),
				'redirect' => __( 'Forward all to one address', 'staging-superpowers' ),
				'off'      => __( 'Send normally (not safe)', 'staging-superpowers' ),
			),
		),
		array(
			'title'       => __( 'Forward to', 'staging-superpowers' ),
			'desc'        => __( 'Every email goes to this address instead, with the original recipient added to the subject line. Until you enter an address, emails stay blocked.', 'staging-superpowers' ),
			'id'          => 'sspw_email_to',
			'type'        => 'email',
			'default'     => '',
			'placeholder' => 'you@example.com',
		),
		array(
			'title' => __( 'Email check', 'staging-superpowers' ),
			'type'  => 'info',
			'text'  => sspw_email_check_html(),
			'id'    => 'sspw_email_check',
		),
		array(
			'type' => 'sectionend',
			'id'   => 'sspw_emails',
		),
	);

	if ( sspw_has_woocommerce() ) {
		$fields = array_merge( $fields, sspw_woocommerce_settings_fields() );
	}

	$fields[] = array(
		'title' => __( 'Freeze automations and block outside services', 'staging-superpowers' ),
		'type'  => 'title',
		'id'    => 'sspw_integrations',
	);

	if ( sspw_has_action_scheduler() ) {
		$fields[] = array(
			'title'    => __( 'Scheduled actions', 'staging-superpowers' ),
			'desc'     => __( 'Freeze scheduled actions', 'staging-superpowers' ),
			'desc_tip' => sprintf(
				/* translators: %s: link to pending scheduled actions */
				__( 'WooCommerce and many other plugins keep a list of jobs to run later, like subscription renewals, follow-up emails, automation workflows and syncs with other apps. The staging copy has the same list as your live site, so it would run those jobs a second time. This holds every job on the list. You can still run a single one by hand from the %s.', 'staging-superpowers' ),
				sspw_link( 'actions', __( 'pending scheduled actions', 'staging-superpowers' ) )
			),
			'id'       => 'sspw_freeze_actions',
			'type'     => 'checkbox',
			'default'  => 'yes',
		);
	}

	/**
	 * Fields on the Protection page, in order. Add-ons can add or move fields;
	 * the page saves every field listed here.
	 *
	 * @param array $fields Field definitions.
	 */
	return apply_filters(
		'sspw_settings_fields',
		array_merge(
			$fields,
			array(
				array(
					'title'    => __( 'WP-Cron', 'staging-superpowers' ),
					'desc'     => __( 'Freeze WP-Cron tasks', 'staging-superpowers' ),
					'desc_tip' => __( 'WordPress and many plugins run background jobs with the WordPress scheduler (WP-Cron): digests and follow-up emails, syncs with other apps, backups to the cloud, imports and clean-ups. The staging copy would run them a second time. This holds them, so nothing runs by itself on this copy. Nothing is deleted: the tasks run again as soon as you turn this off. Developers can still run a single task with WP-CLI (wp cron event run).', 'staging-superpowers' ),
					'id'       => 'sspw_freeze_cron',
					'type'     => 'checkbox',
					'default'  => 'yes',
				),
				array(
					'title'    => __( 'Connected services', 'staging-superpowers' ),
					'desc'     => __( 'Block the site from contacting the services listed below', 'staging-superpowers' ),
					'desc_tip' => __( 'Many plugins talk to outside services in the background: payment processors, email marketing, CRM, social sharing, push notifications, shipping, tax and tracking tools. On a staging copy they still use your live accounts, so a test could refund a real payment, add a test contact to your mailing list, or share a test post on your social accounts. This stops the site from connecting to those services, stops pings to other sites when you publish, puts Jetpack in safe mode, skips scheduled UpdraftPlus backups, and turns on test mode in Easy Digital Downloads, GiveWP and Paid Memberships Pro. Everything else keeps working.', 'staging-superpowers' ),
					'id'       => 'sspw_http_firewall',
					'type'     => 'checkbox',
					'default'  => 'yes',
				),
				array(
					'title'             => __( 'Blocked services', 'staging-superpowers' ),
					'desc'              => __( 'One web address per line. The list already covers common payment, email, marketing, shipping, tax and tracking services. Add any other service your site is connected to. Entering api.mailchimp.com also blocks addresses ending in it, such as us1.api.mailchimp.com.', 'staging-superpowers' ),
					'id'                => 'sspw_blocked_hosts',
					'type'              => 'textarea',
					'default'           => implode( "\n", sspw_default_blocked_hosts() ),
					'css'               => 'min-width:400px;height:220px;font-family:monospace;',
					'custom_attributes' => array( 'spellcheck' => 'false' ),
				),
				array(
					'title'    => __( 'Analytics', 'staging-superpowers' ),
					'desc'     => __( 'Stop analytics on staging', 'staging-superpowers' ),
					'desc_tip' => __( 'Your staging copy has the same tracking codes as your live site, so every visit and test order here would show up in your reports. This switches off the tracking of Site Kit by Google, MonsterInsights, GTM4WP and the Meta pixel\'s Conversions API, and the blocked services list stops Google Analytics and Meta server events from other plugins.', 'staging-superpowers' ),
					'id'       => 'sspw_no_analytics',
					'type'     => 'checkbox',
					'default'  => 'yes',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'sspw_integrations',
				),
			)
		)
	);
}

function sspw_add_settings_page() {
	add_management_page( sspw_settings_label(), sspw_settings_label(), 'manage_options', SSPW_SETTINGS_PAGE, 'sspw_output_settings_page' );

	// The page lived under Settings until 1.1.0: send old links and bookmarks to Tools.
	global $pagenow;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect, no data changes.
	if ( 'options-general.php' === $pagenow && isset( $_GET['page'] ) && SSPW_SETTINGS_PAGE === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect, no data changes.
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		wp_safe_redirect( sspw_settings_url( $section ) );
		exit;
	}
}

/**
 * Sub-pages of the settings page.
 */
function sspw_settings_sections() {
	/**
	 * Sub-pages of the settings page.
	 *
	 * @param array $sections Section id => label.
	 */
	return apply_filters(
		'sspw_settings_sections',
		array(
			''                => __( 'Protection', 'staging-superpowers' ),
			'troubleshooting' => __( 'Troubleshooting', 'staging-superpowers' ),
			'emails'          => __( 'Emails', 'staging-superpowers' ),
			'requests'        => __( 'Requests', 'staging-superpowers' ),
			'changelog'       => __( 'Changelog', 'staging-superpowers' ),
		)
	);
}

function sspw_output_sections() {
	$sections = sspw_settings_sections();
	$current  = sspw_current_section();

	echo '<ul class="subsubsub">';
	$last = array_key_last( $sections );
	foreach ( $sections as $id => $label ) {
		printf(
			'<li><a href="%1$s" class="%2$s">%3$s</a>%4$s</li>',
			esc_url( sspw_settings_url( (string) $id ) ),
			$current === (string) $id ? 'current' : '',
			esc_html( $label ),
			$last === $id ? '' : ' | '
		);
	}
	echo '</ul><br class="clear" />';
}

/**
 * Every sub-page sits inside one form, so tools can post with formaction
 * buttons. Only the main settings have a Save button.
 */
function sspw_output_settings_page() {
	$section = sspw_current_section();

	/* translators: %s: plugin name */
	echo '<div class="wrap"><h1>' . esc_html( sprintf( __( '%s: Live stays safe. Every time.', 'staging-superpowers' ), sspw_settings_label() ) ) . '</h1>';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only shows the "saved" message after the redirect.
	if ( isset( $_GET['sspw-saved'] ) && '' === $section ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'staging-superpowers' ) . '</p></div>';
	}

	sspw_output_sections();

	echo '<form method="post" id="mainform" action="" enctype="multipart/form-data">';

	if ( 'troubleshooting' === $section ) {
		sspw_output_troubleshooting();
	} elseif ( 'emails' === $section ) {
		sspw_output_emails();
	} elseif ( 'requests' === $section ) {
		sspw_output_requests();
	} elseif ( 'changelog' === $section ) {
		sspw_output_changelog();
	} elseif ( '' !== $section ) {
		/**
		 * Output for a sub-page added through sspw_settings_sections.
		 */
		do_action( 'sspw_output_section_' . $section );
	} else {
		wp_nonce_field( 'sspw_settings', 'sspw_settings_nonce' );
		sspw_render_fields( sspw_settings_fields() );
		sspw_pro_features();
		submit_button( null, 'primary', 'sspw_save' );

		// The forwarding address only matters in "Forward all" mode, so it is hidden otherwise.
		sspw_inline_script(
			"var sspwMode = jQuery( '#sspw_email_mode' ), sspwTo = jQuery( '#sspw_email_to' ).closest( 'tr' );
			function sspwToggleTo() { sspwTo.toggle( 'redirect' === sspwMode.val() ); }
			sspwMode.on( 'change', sspwToggleTo );
			sspwToggleTo();
			var sspwVisitors = jQuery( '#sspw_visitors' ), sspwMessage = jQuery( '#sspw_message_title, #sspw_message_text' ).closest( 'tr' );
			function sspwToggleMessage() { sspwMessage.toggle( 'lock' === sspwVisitors.val() ); }
			sspwVisitors.on( 'change', sspwToggleMessage );
			sspwToggleMessage();"
		);
	}

	echo '</form></div>';
}

/**
 * Renders the field arrays above as a standard WordPress settings screen.
 */
function sspw_render_fields( $fields ) {
	foreach ( $fields as $field ) {
		$type = $field['type'];

		if ( 'title' === $type ) {
			echo '<h2>' . esc_html( $field['title'] ) . '</h2>';
			if ( ! empty( $field['desc'] ) ) {
				echo '<p>' . wp_kses_post( $field['desc'] ) . '</p>';
			}
			echo '<table class="form-table" role="presentation">';
			continue;
		}

		if ( 'sectionend' === $type ) {
			echo '</table>';
			continue;
		}

		$id    = $field['id'];
		$value = 'info' === $type ? '' : sspw_get( $id );
		$value = false === get_option( $id, false ) && isset( $field['default'] ) ? $field['default'] : $value;

		echo '<tr><th scope="row">';
		if ( in_array( $type, array( 'info', 'checkbox' ), true ) ) {
			echo esc_html( $field['title'] );
		} else {
			printf( '<label for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $field['title'] ) );
		}
		echo '</th><td>';

		switch ( $type ) {
			case 'info':
				echo wp_kses_post( $field['text'] );
				break;

			case 'checkbox':
				printf(
					'<label for="%1$s"><input type="checkbox" name="%1$s" id="%1$s" value="1" %2$s /> %3$s</label>',
					esc_attr( $id ),
					checked( 'yes', $value, false ),
					esc_html( $field['desc'] )
				);
				if ( ! empty( $field['desc_tip'] ) ) {
					echo '<p class="description">' . wp_kses_post( $field['desc_tip'] ) . '</p>';
				}
				break;

			case 'select':
				printf( '<select name="%1$s" id="%1$s" style="%2$s">', esc_attr( $id ), esc_attr( isset( $field['css'] ) ? $field['css'] : '' ) );
				foreach ( $field['options'] as $key => $label ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $key, $value, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'textarea':
				printf(
					'<textarea name="%1$s" id="%1$s" style="%2$s" spellcheck="false">%3$s</textarea>',
					esc_attr( $id ),
					esc_attr( isset( $field['css'] ) ? $field['css'] : '' ),
					esc_textarea( $value )
				);
				break;

			default:
				printf(
					'<input type="%1$s" name="%2$s" id="%2$s" value="%3$s" placeholder="%4$s" class="regular-text" />',
					esc_attr( $type ),
					esc_attr( $id ),
					esc_attr( $value ),
					esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' )
				);
		}

		if ( ! in_array( $type, array( 'checkbox', 'info' ), true ) && ! empty( $field['desc'] ) ) {
			echo '<p class="description">' . wp_kses_post( $field['desc'] ) . '</p>';
		}

		echo '</td></tr>';
	}
}

function sspw_sanitize_field( $field, $raw ) {
	switch ( $field['type'] ) {
		case 'checkbox':
			return null === $raw ? 'no' : 'yes';
		case 'select':
			return isset( $field['options'][ (string) $raw ] ) ? (string) $raw : $field['default'];
		case 'textarea':
			// Browsers send Windows line endings; store plain ones so an unchanged list never looks changed.
			return str_replace( "\r\n", "\n", sanitize_textarea_field( (string) $raw ) );
		case 'email':
			return sanitize_email( (string) $raw );
		case 'url':
			return esc_url_raw( trim( (string) $raw ) );
		default:
			return sanitize_text_field( (string) $raw );
	}
}

/**
 * Saves the main settings, records what changed in the changelog, and
 * reloads the page so every protection runs with the new values.
 */
function sspw_save_settings() {
	if ( ! isset( $_POST['sspw_save'] ) || ! sspw_is_settings_page( '' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'staging-superpowers' ) );
	}

	check_admin_referer( 'sspw_settings', 'sspw_settings_nonce' );

	$fields  = array();
	$changes = array();

	foreach ( sspw_settings_fields() as $field ) {
		if ( empty( $field['id'] ) || in_array( $field['type'], array( 'title', 'sectionend', 'info' ), true ) ) {
			continue;
		}

		$id  = $field['id'];
		$raw = isset( $_POST[ $id ] ) ? wp_unslash( $_POST[ $id ] ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by type in sspw_sanitize_field().
		$old = sspw_get( $id );
		$new = sspw_sanitize_field( $field, $raw );

		update_option( $id, $new );

		$fields[ $id ] = $field;
		// Lists saved before 1.1.2 may still have Windows line endings.
		if ( is_string( $old ) ) {
			$old = str_replace( "\r\n", "\n", $old );
		}
		if ( $old !== $new ) {
			$changes[ $id ] = array( $old, $new );
		}
	}

	if ( function_exists( 'sspw_log_changes' ) ) {
		sspw_log_changes( sspw_settings_label(), $changes, $fields );
	}

	wp_safe_redirect( add_query_arg( 'sspw-saved', '1', sspw_settings_url() ) );
	exit;
}

function sspw_plugin_action_links( $links ) {
	array_unshift(
		$links,
		sprintf(
			'<a href="%s">%s</a>',
			esc_url( sspw_settings_url() ),
			esc_html__( 'Settings', 'staging-superpowers' )
		)
	);

	return $links;
}

/**
 * Inline script printed in the admin footer. Safe to call while the page body renders.
 */
function sspw_inline_script( $js ) {
	if ( ! wp_script_is( 'sspw-inline', 'registered' ) ) {
		wp_register_script( 'sspw-inline', false, array( 'jquery' ), SSPW_VERSION, true );
	}

	wp_enqueue_script( 'sspw-inline' );
	wp_add_inline_script( 'sspw-inline', 'jQuery( function () { ' . $js . ' } );' );
}

/**
 * What Pro adds, shown above the Save button while Pro is not active.
 */
function sspw_pro_features() {
	if ( class_exists( 'Business_Bloomer_Staging_Superpowers_Pro' ) ) {
		return;
	}

	$features = array(
		__( 'Anonymize users, customers, form entries and staff, and remove secret keys, before you hand the site to a developer or agency', 'staging-superpowers' ),
		__( 'Send logged-out visitors to the same page on your live site', 'staging-superpowers' ),
		__( 'Compare any page with your live site, side by side', 'staging-superpowers' ),
		__( 'Find staging links, images and files still used on your live site', 'staging-superpowers' ),
		__( 'See and block every outgoing request', 'staging-superpowers' ),
	);

	if ( sspw_has_woocommerce() ) {
		$features[] = __( 'Generate and delete test orders, products and customers', 'staging-superpowers' );
		$features[] = __( 'Scramble revenue and keep only some of your orders', 'staging-superpowers' );
		$features[] = __( 'Log in as a customer, switch WooCommerce versions and HPOS', 'staging-superpowers' );
	} else {
		$features[] = __( 'Delete your own account when you log out, so whoever takes over never sees your details', 'staging-superpowers' );
		$features[] = __( 'Every data tool checks this is not your live site before it changes anything', 'staging-superpowers' );
		$features[] = __( 'For WooCommerce stores: test orders and products, revenue scrambling, logging in as a customer, version and HPOS switches', 'staging-superpowers' );
	}

	$features[] = __( 'And more...', 'staging-superpowers' );

	echo '<h2>' . esc_html__( 'Get more with Staging Superpowers Pro', 'staging-superpowers' ) . '</h2><ul style="list-style:disc;margin-left:20px">';
	foreach ( $features as $feature ) {
		echo '<li>' . esc_html( $feature ) . '</li>';
	}
	echo '</ul><p><a href="' . esc_url( 'https://www.businessbloomer.com/plugins/staging-superpowers-pro/' ) . '" target="_blank" rel="noopener">' . esc_html__( 'See everything Pro does', 'staging-superpowers' ) . '</a></p>';
}
