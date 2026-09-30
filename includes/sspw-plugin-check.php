<?php
/**
 * Plugin check: popular plugins that talk to live services, and whether this
 * plugin covers them on staging or what to do yourself. Shown on the settings
 * page. The protections themselves are in sspw-integrations.php.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Known plugins by folder: array( status, text, setting ).
 *
 * Status "covered" means this plugin handles it, "check" means there is
 * something to do yourself. When a covered plugin relies on a setting that is
 * off, it is shown as "check" instead.
 */
function sspw_known_plugins() {
	$wp_mail     = __( 'It sends through WordPress, where the block stops every email first.', 'staging-superpowers' );
	$email_api   = __( 'Its sending service is on the blocked services list, and the email check above warns you if it replaces the WordPress email function.', 'staging-superpowers' );
	$ses         = __( 'It sends through Amazon SES directly. Add your SES address (for example email.eu-west-1.amazonaws.com) to the blocked services.', 'staging-superpowers' );
	$test_mode   = __( 'Test mode is forced on, so payments use your test account.', 'staging-superpowers' );
	$analytics   = 'sspw_no_analytics';
	$firewall    = 'sspw_http_firewall';
	$cron        = 'sspw_freeze_cron';
	$offload     = __( 'It uploads with Amazon\'s own code, not through WordPress, so it cannot be blocked. Media uploaded here goes to your live bucket, so switch it off on staging.', 'staging-superpowers' );
	$email_block = 'sspw_email_mode';

	return array(
		// Email.
		'wp-mail-smtp'                          => array( 'covered', $wp_mail, $email_block ),
		'fluent-smtp'                           => array( 'covered', $wp_mail, $email_block ),
		'post-smtp'                             => array( 'covered', $wp_mail, $email_block ),
		'easy-wp-smtp'                          => array( 'covered', $wp_mail, $email_block ),
		'suremails'                             => array( 'covered', $wp_mail, $email_block ),
		'gosmtp'                                => array( 'covered', $wp_mail, $email_block ),
		'mailgun'                               => array( 'covered', $email_api, $firewall ),
		'sendgrid-email-delivery-simplified'    => array( 'covered', $email_api, $firewall ),
		'sparkpost'                             => array( 'covered', $email_api, $firewall ),
		'wpmandrill'                            => array( 'covered', $email_api, $firewall ),
		'mailpoet'                              => array( 'check', __( 'MailPoet sends newsletters from its own queue. That queue stays frozen while scheduled actions and WP-Cron are frozen, and the MailPoet Sending Service is blocked. If MailPoet sends through your own SMTP server, keep both freezes on.', 'staging-superpowers' ), '' ),
		'wp-ses'                                => array( 'check', $ses, '' ),
		'wp-offload-ses'                        => array( 'check', $ses, '' ),
		'wp-offload-ses-lite'                   => array( 'check', $ses, '' ),

		// Publishing and social sharing.
		'jetpack'                               => array( 'covered', __( 'Jetpack is put in safe mode, so it stops syncing this copy to WordPress.com. That also stops Jetpack Social sharing new posts.', 'staging-superpowers' ), $firewall ),
		'jetpack-social'                        => array( 'covered', __( 'Jetpack is put in safe mode, so it stops syncing this copy to WordPress.com and new posts are not shared.', 'staging-superpowers' ), $firewall ),
		'blog2social'                           => array( 'covered', __( 'Posts are shared through the Blog2Social service, which is blocked.', 'staging-superpowers' ), $firewall ),
		'tweet-old-post'                        => array( 'covered', __( 'It shares from WP-Cron, which is frozen. Its X (Twitter) and Facebook connections do not go through WordPress, so if you turn the WP-Cron freeze off, click Stop Sharing on the Revive Social dashboard first.', 'staging-superpowers' ), $cron ),
		'onesignal-free-web-push-notifications' => array( 'covered', __( 'Push notifications are sent through OneSignal, which is blocked.', 'staging-superpowers' ), $firewall ),
		'pushengage'                            => array( 'covered', __( 'Push notifications are sent through the PushEngage API, which is blocked.', 'staging-superpowers' ), $firewall ),

		// Shared live services.
		'cloudflare'                            => array( 'covered', __( 'The Cloudflare API is blocked, so this copy cannot purge the cache or change the settings of your live site.', 'staging-superpowers' ), $firewall ),
		'elasticpress'                          => array( 'check', __( 'This copy writes to its own search index, named after this address, so your live search is not touched. That index lives in your ElasticPress account, so delete it when you are done.', 'staging-superpowers' ), '' ),
		'wp-search-with-algolia'                => array( 'check', __( 'It talks to Algolia with its own code, so it cannot be blocked, and this copy uses the same index names as your live site. Change the index name prefix in its settings before indexing, or switch it off on staging.', 'staging-superpowers' ), '' ),
		'updraftplus'                           => array( 'covered', __( 'Scheduled backups do not run. A backup you start by hand still goes to the same remote storage as your live backups.', 'staging-superpowers' ), $firewall ),
		'backwpup'                              => array( 'covered', __( 'Its scheduled jobs start from WP-Cron, which is frozen. If you turn the WP-Cron freeze off, set its jobs to start manually first.', 'staging-superpowers' ), $cron ),
		'amazon-s3-and-cloudfront'              => array( 'check', $offload, '' ),
		'amazon-s3-and-cloudfront-pro'          => array( 'check', $offload, '' ),

		// Payments.
		'easy-digital-downloads'                => array( 'covered', $test_mode, $firewall ),
		'give'                                  => array( 'covered', $test_mode, $firewall ),
		'paid-memberships-pro'                  => array( 'covered', __( 'The payment gateway is switched to its testing (sandbox) environment.', 'staging-superpowers' ), $firewall ),

		// Automations.
		'uncanny-automator'                     => array( 'covered', __( 'Its app integrations go through the Automator API, which is blocked. Webhooks it sends to Zapier or Make are blocked too.', 'staging-superpowers' ), $firewall ),

		// Analytics.
		'google-site-kit'                       => array( 'covered', __( 'The Analytics, Tag Manager, Ads and AdSense tags are not added to pages.', 'staging-superpowers' ), $analytics ),
		'google-analytics-for-wordpress'        => array( 'covered', __( 'MonsterInsights tracking is switched off.', 'staging-superpowers' ), $analytics ),
		'duracelltomi-google-tag-manager'       => array( 'covered', __( 'The Google Tag Manager container is not added to pages.', 'staging-superpowers' ), $analytics ),
		'official-facebook-pixel'               => array( 'covered', __( 'Server events (Conversions API) are not sent to Meta.', 'staging-superpowers' ), $analytics ),

		// Image optimization.
		'shortpixel-image-optimiser'            => array( 'covered', __( 'The ShortPixel API is blocked, so images uploaded here do not use your credits.', 'staging-superpowers' ), $firewall ),
		'wp-smushit'                            => array( 'covered', __( 'The Smush API is blocked, so images uploaded here are not sent for optimizing.', 'staging-superpowers' ), $firewall ),
		'imagify'                               => array( 'check', __( 'Images are sent to Imagify with its own code, so this cannot be blocked and images uploaded here use your credits. Switch it off on staging if that matters.', 'staging-superpowers' ), '' ),
	);
}

/**
 * Whether the setting a protection relies on is on.
 */
function sspw_plugin_check_setting_on( $option ) {
	if ( '' === $option ) {
		return true;
	}

	if ( 'sspw_email_mode' === $option ) {
		return 'off' !== sspw_email_status();
	}

	return 'yes' === sspw_get( $option );
}

/**
 * Active plugins from the list above: folder => array( name, status, text ).
 */
function sspw_active_known_plugins() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$known  = sspw_known_plugins();
	$active = array();

	foreach ( get_plugins() as $file => $data ) {
		$folder = dirname( $file );
		if ( ! isset( $known[ $folder ] ) || ! is_plugin_active( $file ) ) {
			continue;
		}

		list( $status, $text, $setting ) = $known[ $folder ];

		if ( 'covered' === $status && ! sspw_plugin_check_setting_on( $setting ) ) {
			$status = 'check';
			$text   = __( 'This relies on a protection that is switched off above. Turn it back on to cover this plugin.', 'staging-superpowers' );
		}

		$active[ $folder ] = array( $data['Name'], $status, $text );
	}

	return $active;
}

/**
 * The report shown in the Plugin check section.
 */
function sspw_plugin_check_html() {
	$active = sspw_active_known_plugins();

	if ( ! $active ) {
		return esc_html__( 'None of the plugins we know about are active.', 'staging-superpowers' );
	}

	$html = '<ul style="list-style:disc;margin:0 0 0 20px">';
	foreach ( $active as $plugin ) {
		$label = 'covered' === $plugin[1] ? __( 'Covered:', 'staging-superpowers' ) : __( 'Check:', 'staging-superpowers' );
		$color = 'covered' === $plugin[1] ? '#007017' : '#b32d2e';

		$html .= '<li><strong>' . esc_html( $plugin[0] ) . '</strong>: <span style="color:' . $color . ';font-weight:600">' . esc_html( $label ) . '</span> ' . esc_html( $plugin[2] ) . '</li>';
	}
	$html .= '</ul>';

	return $html;
}
