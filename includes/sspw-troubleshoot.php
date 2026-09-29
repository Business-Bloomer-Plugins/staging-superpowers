<?php
/**
 * Troubleshooting sub-page: switch plugins and the theme off and back on to find
 * conflicts. Only works while the site is armed, so a live store can never be
 * bulk-disabled from here.
 *
 * Plugins are switched silently (no activation/deactivation routines), because
 * this is a temporary test and those routines can reset schedules or settings.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'woocommerce_sections_sspw', 'sspw_output_sections' );
add_action( 'admin_post_sspw_troubleshoot', 'sspw_handle_troubleshoot' );

function sspw_output_sections() {
	global $current_section;

	$sections = array(
		''                => __( 'Protection', 'staging-superpowers-for-woocommerce' ),
		'troubleshooting' => __( 'Troubleshooting', 'staging-superpowers-for-woocommerce' ),
		'changelog'       => __( 'Changelog', 'staging-superpowers-for-woocommerce' ),
	);

	echo '<ul class="subsubsub">';
	$last = array_key_last( $sections );
	foreach ( $sections as $id => $label ) {
		printf(
			'<li><a href="%1$s" class="%2$s">%3$s</a>%4$s</li>',
			esc_url( admin_url( 'admin.php?page=wc-settings&tab=sspw' . ( $id ? '&section=' . $id : '' ) ) ),
			$current_section === $id ? 'current' : '',
			esc_html( $label ),
			$last === $id ? '' : ' | '
		);
	}
	echo '</ul><br class="clear" />';
}

/**
 * WooCommerce itself and this plugin are never switched off.
 */
function sspw_always_kept_plugins() {
	return array( plugin_basename( WC_PLUGIN_FILE ), plugin_basename( SSPW_PLUGIN_FILE ) );
}

/**
 * Plugins the kept ones rely on ("Requires Plugins" header) must stay on too,
 * otherwise the kept plugin breaks and the test proves nothing.
 */
function sspw_with_dependencies( $keep ) {
	if ( ! class_exists( 'WP_Plugin_Dependencies' ) ) {
		return $keep;
	}

	WP_Plugin_Dependencies::initialize();

	$by_slug = array();
	foreach ( (array) get_option( 'active_plugins', array() ) as $file ) {
		$by_slug[ dirname( $file ) ] = $file;
	}

	foreach ( $keep as $file ) {
		foreach ( WP_Plugin_Dependencies::get_dependencies( $file ) as $slug ) {
			if ( isset( $by_slug[ $slug ] ) && ! in_array( $by_slug[ $slug ], $keep, true ) ) {
				$keep[] = $by_slug[ $slug ];
			}
		}
	}

	return $keep;
}

function sspw_disabled_plugins() {
	return (array) get_option( 'sspw_disabled_plugins', array() );
}

function sspw_previous_theme() {
	return (string) get_option( 'sspw_previous_theme', '' );
}

/**
 * Newest bundled Twenty-something theme, falling back to Storefront.
 */
function sspw_default_theme() {
	$core = WP_Theme::get_core_default_theme();
	if ( $core ) {
		return $core;
	}

	$storefront = wp_get_theme( 'storefront' );

	return $storefront->exists() ? $storefront : false;
}

function sspw_troubleshoot_url() {
	return admin_url( 'admin.php?page=wc-settings&tab=sspw&section=troubleshooting' );
}

function sspw_output_troubleshooting() {
	$GLOBALS['hide_save_button'] = true; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce's own flag for hiding the Save button.

	$notice = get_transient( 'sspw_notice_' . get_current_user_id() );
	if ( $notice ) {
		delete_transient( 'sspw_notice_' . get_current_user_id() );
		echo '<div class="notice notice-success inline"><p>' . esc_html( $notice ) . '</p></div>';
	}

	echo '<h2>' . esc_html__( 'Troubleshooting', 'staging-superpowers-for-woocommerce' ) . '</h2>';

	if ( ! sspw_is_armed() ) {
		echo '<p>' . esc_html__( 'Troubleshooting is only available while Staging Superpowers is turned on for this site, so a live store can never be switched off by accident.', 'staging-superpowers-for-woocommerce' ) . '</p>';
		return;
	}

	echo '<p>' . esc_html__( 'When something is broken, the cause is often another plugin or the theme. Switch them off here, check if the problem is gone, then switch them back on. Changes apply to the whole site, which is fine on a staging copy.', 'staging-superpowers-for-woocommerce' ) . '</p>';

	$action = admin_url( 'admin-post.php?action=sspw_troubleshoot' );
	wp_nonce_field( 'sspw_troubleshoot', 'sspw_nonce' );

	if ( current_user_can( 'activate_plugins' ) ) {
		sspw_output_plugin_tools( $action );
	}

	if ( current_user_can( 'switch_themes' ) ) {
		sspw_output_theme_tools( $action );
	}

	sspw_output_more_tools();

	// WooCommerce warns about unsaved changes when a checkbox is ticked; these buttons submit on purpose.
	wc_enqueue_js( "jQuery( '.sspw-do' ).on( 'click', function () { window.onbeforeunload = null; jQuery( window ).off( 'beforeunload' ); } );" );
}

function sspw_button( $action, $task, $label, $confirm = '', $primary = false ) {
	printf(
		'<button type="submit" class="button %1$s sspw-do" formaction="%2$s" formmethod="post" name="sspw_do" value="%3$s"%4$s>%5$s</button> ',
		$primary ? 'button-primary' : '',
		esc_url( $action ),
		esc_attr( $task ),
		$confirm ? ' onclick="return confirm( ' . esc_attr( wp_json_encode( $confirm ) ) . ' );"' : '',
		esc_html( $label )
	);
}

function sspw_output_plugin_tools( $action ) {
	$all      = get_plugins();
	$active   = (array) get_option( 'active_plugins', array() );
	$kept     = sspw_always_kept_plugins();
	$disabled = array_intersect( sspw_disabled_plugins(), array_keys( $all ) );

	echo '<h3>' . esc_html__( 'Plugins', 'staging-superpowers-for-woocommerce' ) . '</h3>';

	if ( $disabled ) {
		echo '<p><strong>' . esc_html(
			sprintf(
				/* translators: %d: number of plugins */
				_n( '%d plugin is switched off for troubleshooting:', '%d plugins are switched off for troubleshooting:', count( $disabled ), 'staging-superpowers-for-woocommerce' ),
				count( $disabled )
			)
		) . '</strong> ' . esc_html( implode( ', ', array_map( fn( $f ) => $all[ $f ]['Name'], $disabled ) ) ) . '</p><p>';
		sspw_button( $action, 'restore_plugins', __( 'Switch these back on', 'staging-superpowers-for-woocommerce' ), '', true );
		echo '</p>';
	}

	$others = array_diff( $active, $kept );

	if ( $others ) {
		echo '<p>' . esc_html__( 'Tick any plugin you want to keep on. WooCommerce, Staging Superpowers, and plugins that a kept plugin needs always stay on.', 'staging-superpowers-for-woocommerce' ) . '</p>';
		echo '<fieldset style="columns:3 280px;margin:0 0 12px">';
		foreach ( $others as $file ) {
			if ( ! isset( $all[ $file ] ) ) {
				continue;
			}
			printf(
				'<label style="display:block;margin:0 0 6px;break-inside:avoid"><input type="checkbox" name="sspw_keep[]" value="%1$s" /> %2$s</label>',
				esc_attr( $file ),
				esc_html( $all[ $file ]['Name'] )
			);
		}
		echo '</fieldset><p>';
		sspw_button(
			$action,
			'disable_plugins',
			__( 'Switch off all other plugins', 'staging-superpowers-for-woocommerce' ),
			__( 'Switch off every active plugin except WooCommerce, Staging Superpowers and the ones you ticked? You can switch them back on from this page.', 'staging-superpowers-for-woocommerce' )
		);
		echo '</p>';
	} else {
		echo '<p>' . esc_html__( 'Only WooCommerce and Staging Superpowers are active.', 'staging-superpowers-for-woocommerce' ) . '</p>';
	}

	if ( array_diff( array_keys( $all ), $active ) ) {
		echo '<p>';
		sspw_button(
			$action,
			'enable_all',
			__( 'Switch on all installed plugins', 'staging-superpowers-for-woocommerce' ),
			__( 'Switch on every installed plugin, including ones that were already off before you started troubleshooting?', 'staging-superpowers-for-woocommerce' )
		);
		echo '</p>';
	}
}

function sspw_output_theme_tools( $action ) {
	$theme    = wp_get_theme();
	$parent   = $theme->parent();
	$default  = sspw_default_theme();
	$previous = sspw_previous_theme() ? wp_get_theme( sspw_previous_theme() ) : false;

	echo '<h3>' . esc_html__( 'Theme', 'staging-superpowers-for-woocommerce' ) . '</h3><p>';

	if ( $parent ) {
		/* translators: 1: active theme name, 2: parent theme name */
		echo esc_html( sprintf( __( 'Active theme: %1$s (a child theme of %2$s).', 'staging-superpowers-for-woocommerce' ), $theme->get( 'Name' ), $parent->get( 'Name' ) ) );
	} else {
		/* translators: %s: active theme name */
		echo esc_html( sprintf( __( 'Active theme: %s.', 'staging-superpowers-for-woocommerce' ), $theme->get( 'Name' ) ) );
	}

	echo ' ' . esc_html__( 'Menus, widgets and customizer settings are kept for each theme, so switching back restores them.', 'staging-superpowers-for-woocommerce' ) . '</p><p>';

	if ( $previous && $previous->exists() && $previous->get_stylesheet() !== $theme->get_stylesheet() ) {
		/* translators: %s: theme name */
		sspw_button( $action, 'theme_restore', sprintf( __( 'Switch back to %s', 'staging-superpowers-for-woocommerce' ), $previous->get( 'Name' ) ), '', true );
	}

	if ( $parent ) {
		/* translators: %s: parent theme name */
		sspw_button( $action, 'theme_parent', sprintf( __( 'Switch to the parent theme (%s)', 'staging-superpowers-for-woocommerce' ), $parent->get( 'Name' ) ) );
	}

	if ( $default && $default->get_stylesheet() !== $theme->get_stylesheet() ) {
		/* translators: %s: theme name */
		sspw_button( $action, 'theme_default', sprintf( __( 'Switch to a default theme (%s)', 'staging-superpowers-for-woocommerce' ), $default->get( 'Name' ) ) );
	} elseif ( ! $default ) {
		esc_html_e( 'To test with a default theme, install Twenty Twenty-Five or Storefront first.', 'staging-superpowers-for-woocommerce' );
	}

	echo '</p>';
}

function sspw_output_more_tools() {
	$links = array(
		admin_url( 'admin.php?page=wc-status&tab=tools' ) => __( 'WooCommerce tools (clear transients, regenerate lookup tables, and more)', 'staging-superpowers-for-woocommerce' ),
		admin_url( 'admin.php?page=wc-status&tab=logs' )  => __( 'WooCommerce logs', 'staging-superpowers-for-woocommerce' ),
		admin_url( 'admin.php?page=wc-status&tab=action-scheduler&status=pending' ) => __( 'Pending scheduled actions', 'staging-superpowers-for-woocommerce' ),
		admin_url( 'site-health.php' )                    => __( 'Site Health', 'staging-superpowers-for-woocommerce' ),
	);

	echo '<h3>' . esc_html__( 'More tools', 'staging-superpowers-for-woocommerce' ) . '</h3><ul style="list-style:disc;margin-left:20px">';
	foreach ( $links as $url => $label ) {
		printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $url ), esc_html( $label ) );
	}
	echo '</ul>';
}

/**
 * Switch plugins on in rounds: a plugin whose "Requires Plugins" dependency is not
 * active yet is refused by WordPress, so it is retried after the others.
 * Plugins listed in $silent come back without running their activation routine.
 *
 * @return array Plugin files that could not be switched on.
 */
function sspw_activate_plugins( $files, $silent = array() ) {
	$pending = array_values( $files );

	while ( $pending ) {
		$retry = array();

		foreach ( $pending as $file ) {
			$result = activate_plugin( $file, '', false, in_array( $file, $silent, true ) );
			if ( is_wp_error( $result ) ) {
				$retry[] = $file;
			}
		}

		if ( count( $retry ) === count( $pending ) ) {
			return $retry;
		}

		$pending = $retry;
	}

	return array();
}

function sspw_activation_message( $total, $failed ) {
	$all     = get_plugins();
	$message = sprintf(
		/* translators: %d: number of plugins */
		_n( '%d plugin switched on.', '%d plugins switched on.', $total - count( $failed ), 'staging-superpowers-for-woocommerce' ),
		$total - count( $failed )
	);

	if ( $failed ) {
		$names = array_map(
			function ( $file ) use ( $all ) {
				return isset( $all[ $file ] ) ? $all[ $file ]['Name'] : $file;
			},
			$failed
		);
		/* translators: %s: list of plugin names */
		$message .= ' ' . sprintf( __( 'Could not switch on: %s', 'staging-superpowers-for-woocommerce' ), implode( ', ', $names ) );
	}

	return $message;
}

function sspw_troubleshoot_done( $message ) {
	set_transient( 'sspw_notice_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );
	wp_safe_redirect( sspw_troubleshoot_url() );
	exit;
}

function sspw_handle_troubleshoot() {
	check_admin_referer( 'sspw_troubleshoot', 'sspw_nonce' );

	if ( ! sspw_is_armed() ) {
		wp_die( esc_html__( 'Troubleshooting is only available while Staging Superpowers is turned on for this site.', 'staging-superpowers-for-woocommerce' ) );
	}

	$do = isset( $_POST['sspw_do'] ) ? sanitize_key( wp_unslash( $_POST['sspw_do'] ) ) : '';

	if ( in_array( $do, array( 'disable_plugins', 'restore_plugins', 'enable_all' ), true ) && ! current_user_can( 'activate_plugins' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'staging-superpowers-for-woocommerce' ) );
	}

	if ( 0 === strpos( $do, 'theme_' ) && ! current_user_can( 'switch_themes' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'staging-superpowers-for-woocommerce' ) );
	}

	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	switch ( $do ) {
		case 'disable_plugins':
			$ticked = isset( $_POST['sspw_keep'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['sspw_keep'] ) ) : array();
			$keep   = sspw_with_dependencies( array_merge( sspw_always_kept_plugins(), $ticked ) );
			$off    = array_values( array_diff( (array) get_option( 'active_plugins', array() ), $keep ) );

			deactivate_plugins( $off, true );

			if ( function_exists( 'sspw_log' ) && $off ) {
				/* translators: %s: list of plugin names */
				sspw_log( sprintf( __( 'Troubleshooting: plugins switched off: %s', 'staging-superpowers-for-woocommerce' ), implode( ', ', array_map( 'sspw_plugin_name', $off ) ) ) );
			}

			// Several rounds add up, so "switch these back on" restores everything.
			update_option( 'sspw_disabled_plugins', array_values( array_unique( array_merge( sspw_disabled_plugins(), $off ) ) ), false );

			/* translators: %d: number of plugins */
			sspw_troubleshoot_done( sprintf( _n( '%d plugin switched off.', '%d plugins switched off.', count( $off ), 'staging-superpowers-for-woocommerce' ), count( $off ) ) );
			break;

		case 'restore_plugins':
			$back   = array_values( array_intersect( sspw_disabled_plugins(), array_keys( get_plugins() ) ) );
			$failed = sspw_activate_plugins( $back, $back );

			if ( function_exists( 'sspw_log' ) && $back ) {
				/* translators: %s: list of plugin names */
				sspw_log( sprintf( __( 'Troubleshooting: plugins switched back on: %s', 'staging-superpowers-for-woocommerce' ), implode( ', ', array_map( 'sspw_plugin_name', array_diff( $back, $failed ) ) ) ) );
			}

			// Anything that could not come back stays listed, so it is not forgotten.
			if ( $failed ) {
				update_option( 'sspw_disabled_plugins', $failed, false );
			} else {
				delete_option( 'sspw_disabled_plugins' );
			}

			sspw_troubleshoot_done( sspw_activation_message( count( $back ), $failed ) );
			break;

		case 'enable_all':
			// Plugins turned off by troubleshooting come back silently; plugins that were never on
			// get a normal activation, so they can create their tables and defaults.
			$inactive = array_values( array_diff( array_keys( get_plugins() ), (array) get_option( 'active_plugins', array() ) ) );
			$failed   = sspw_activate_plugins( $inactive, sspw_disabled_plugins() );

			if ( function_exists( 'sspw_log' ) && $inactive ) {
				/* translators: %s: list of plugin names */
				sspw_log( sprintf( __( 'Troubleshooting: all installed plugins switched on: %s', 'staging-superpowers-for-woocommerce' ), implode( ', ', array_map( 'sspw_plugin_name', array_diff( $inactive, $failed ) ) ) ) );
			}

			delete_option( 'sspw_disabled_plugins' );

			sspw_troubleshoot_done( sspw_activation_message( count( $inactive ), $failed ) );
			break;

		case 'theme_parent':
		case 'theme_default':
		case 'theme_restore':
			$current = get_stylesheet();

			if ( 'theme_restore' === $do ) {
				$target = sspw_previous_theme();
			} elseif ( 'theme_parent' === $do ) {
				$target = get_template();
			} else {
				$default = sspw_default_theme();
				$target  = $default ? $default->get_stylesheet() : '';
			}

			$theme = $target ? wp_get_theme( $target ) : false;
			if ( ! $theme || ! $theme->exists() || $target === $current ) {
				sspw_troubleshoot_done( __( 'That theme is not available.', 'staging-superpowers-for-woocommerce' ) );
			}

			// Remember the theme from before troubleshooting started, not each step in between.
			if ( 'theme_restore' === $do ) {
				delete_option( 'sspw_previous_theme' );
			} elseif ( '' === sspw_previous_theme() ) {
				update_option( 'sspw_previous_theme', $current, false );
			}

			switch_theme( $target );

			/* translators: %s: theme name */
			sspw_troubleshoot_done( sprintf( __( 'Switched to %s.', 'staging-superpowers-for-woocommerce' ), $theme->get( 'Name' ) ) );
			break;
	}

	wp_safe_redirect( sspw_troubleshoot_url() );
	exit;
}

function sspw_output_changelog() {
	$GLOBALS['hide_save_button'] = true; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce's own flag for hiding the Save button.

	echo '<h2>' . esc_html__( 'Changelog', 'staging-superpowers-for-woocommerce' ) . '</h2>';
	echo '<p>' . esc_html__( 'Everything changed on this staging copy is written to the WooCommerce logs, so you have a list of what to redo on your live store instead of copying this database over it (which would wipe every order placed on the live store since the copy was made).', 'staging-superpowers-for-woocommerce' ) . '</p>';
	echo '<p>' . esc_html__( 'Recorded: WooCommerce settings (with the old and new value), theme and plugin changes and updates, the cart or checkout switching between blocks and classic, and products, pages, posts, coupons, categories, tags and menus created, edited or deleted.', 'staging-superpowers-for-woocommerce' ) . '</p>';

	if ( ! sspw_is_armed() ) {
		echo '<p>' . esc_html__( 'Nothing is recorded while Staging Superpowers is paused.', 'staging-superpowers-for-woocommerce' ) . '</p>';
	} elseif ( 'no' === get_option( 'woocommerce_logs_logging_enabled', 'yes' ) ) {
		echo '<div class="notice notice-warning inline"><p>' . wp_kses_post(
			sprintf(
				/* translators: %s: link to the WooCommerce logs settings */
				__( 'WooCommerce logging is turned off, so nothing can be recorded. Turn it on in %s.', 'staging-superpowers-for-woocommerce' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=logs' ) ) . '">' . esc_html__( 'WooCommerce > Settings > Advanced > Logs', 'staging-superpowers-for-woocommerce' ) . '</a>'
			)
		) . '</p></div>';
	} elseif ( get_option( 'sspw_armed_at' ) ) {
		/* translators: %s: date */
		echo '<p><strong>' . esc_html( sprintf( __( 'Recording since %s.', 'staging-superpowers-for-woocommerce' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) get_option( 'sspw_armed_at' ) ) ) ) . '</strong></p>';
	}

	printf( '<p><a class="button button-primary" href="%1$s">%2$s</a></p>', esc_url( sspw_changelog_url() ), esc_html__( 'View the changelog', 'staging-superpowers-for-woocommerce' ) );

	echo '<p class="description">' . wp_kses_post(
		sprintf(
			/* translators: 1: number of days, 2: link to the WooCommerce logs settings */
			__( 'Entries are deleted after %1$d days, like all WooCommerce logs. You can change this in %2$s.', 'staging-superpowers-for-woocommerce' ),
			(int) get_option( 'woocommerce_logs_retention_period_days', 30 ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=logs' ) ) . '">' . esc_html__( 'WooCommerce > Settings > Advanced > Logs', 'staging-superpowers-for-woocommerce' ) . '</a>'
		)
	) . '</p>';
}

/**
 * The log viewer link works whether or not the changelog module is loaded.
 */
function sspw_changelog_url() {
	return admin_url( 'admin.php?page=wc-status&tab=logs&source=staging-superpowers' );
}
