<?php
/**
 * Troubleshooting sub-page: switch plugins and the theme off and back on to find
 * conflicts. Only works while the site is armed, so a live site can never be
 * bulk-disabled from here.
 *
 * Plugins are switched silently (no activation/deactivation routines), because
 * this is a temporary test and those routines can reset schedules or settings.
 *
 * Every switch is checked: the site is loaded in the background, logged out and
 * as the current user, and if that hits a fatal error the switch is undone.
 * Each action writes one changelog entry, however many plugins it touched.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_sspw_troubleshoot', 'sspw_handle_troubleshoot' );
add_action( 'admin_post_sspw_troubleshoot_cancel', 'sspw_cancel_troubleshoot_plan' );
add_action( 'wp_ajax_sspw_release_dates', 'sspw_ajax_release_dates' );
add_action( 'admin_enqueue_scripts', 'sspw_troubleshoot_assets' );

/**
 * Only this plugin is never switched off. WooCommerce can be, like any other.
 */
function sspw_always_kept_plugins() {
	return array( plugin_basename( SSPW_PLUGIN_FILE ) );
}

/**
 * Plugins a plugin cannot run without: its "Requires Plugins" header, and
 * WooCommerce for WooCommerce.com extensions (the "Woo" header) that do not
 * have it. "WC requires at least" alone is not enough: plugins like MailPoet
 * declare it for an optional integration and run fine without WooCommerce.
 * Anything this misses is caught by the check after switching.
 *
 * @return string[] Plugin files.
 */
function sspw_plugin_needs( $file ) {
	static $by_slug = null;

	if ( null === $by_slug ) {
		$by_slug = array();
		foreach ( array_keys( get_plugins() ) as $installed ) {
			$by_slug[ dirname( $installed ) ] = $installed;
		}
	}

	$needs = array();

	if ( class_exists( 'WP_Plugin_Dependencies' ) ) {
		WP_Plugin_Dependencies::initialize();
		foreach ( WP_Plugin_Dependencies::get_dependencies( $file ) as $slug ) {
			if ( isset( $by_slug[ $slug ] ) ) {
				$needs[] = $by_slug[ $slug ];
			}
		}
	}

	$headers = get_file_data( WP_PLUGIN_DIR . '/' . $file, array( 'woo' => 'Woo' ) );
	if ( '' !== $headers['woo'] && isset( $by_slug['woocommerce'] ) && 'woocommerce' !== dirname( $file ) ) {
		$needs[] = $by_slug['woocommerce'];
	}

	return array_values( array_unique( $needs ) );
}

/**
 * What "switch off" will do with the selected plugins. An active plugin that
 * needs one of them would break, so it goes off too, and that repeats until
 * nothing left on needs something that is off.
 *
 * @param string[] $selected Plugin files ticked in the table.
 * @return array 'off' => files, 'also' => file => names of what it needs.
 */
function sspw_plan_switch_off( $selected ) {
	$active = (array) get_option( 'active_plugins', array() );
	$off    = array_values( array_diff( array_intersect( $selected, $active ), sspw_always_kept_plugins() ) );
	$also   = array();

	do {
		$moved = false;
		foreach ( array_diff( $active, $off, sspw_always_kept_plugins() ) as $file ) {
			$missing = array_intersect( sspw_plugin_needs( $file ), $off );
			if ( $missing ) {
				$off[]         = $file;
				$also[ $file ] = array_map( 'sspw_plugin_label', $missing );
				$moved         = true;
			}
		}
	} while ( $moved );

	return array(
		'off'  => $off,
		'also' => $also,
	);
}

/**
 * The selected plugins plus anything they need that is off, so WordPress does
 * not refuse them.
 *
 * @return array 'on' => files, 'also' => file => names of what needs it.
 */
function sspw_plan_switch_on( $selected ) {
	$active = (array) get_option( 'active_plugins', array() );
	$on     = array_values( array_diff( array_intersect( $selected, array_keys( get_plugins() ) ), $active ) );
	$also   = array();

	do {
		$moved = false;
		foreach ( $on as $file ) {
			foreach ( array_diff( sspw_plugin_needs( $file ), $active, $on ) as $needed ) {
				$on[]            = $needed;
				$also[ $needed ] = sspw_plugin_label( $file );
				$moved           = true;
			}
		}
	} while ( $moved );

	return array(
		'on'   => $on,
		'also' => $also,
	);
}

function sspw_plugin_label( $file ) {
	$all = get_plugins();

	return isset( $all[ $file ] ) ? $all[ $file ]['Name'] : $file;
}

function sspw_disabled_plugins() {
	return (array) get_option( 'sspw_disabled_plugins', array() );
}

function sspw_previous_theme() {
	return (string) get_option( 'sspw_previous_theme', '' );
}

/**
 * WordPress's plugin details window, for the "View changelog" links.
 */
function sspw_troubleshoot_assets() {
	if ( sspw_is_settings_page( 'troubleshooting' ) ) {
		add_thickbox();
	}
}

function sspw_troubleshoot_url() {
	return sspw_settings_url( 'troubleshooting' );
}

/*
 * Version details for the tables.
 */

/**
 * WordPress.org slugs, from WordPress's own update check: it lists every plugin
 * and theme it knows on WordPress.org. Before the first update check, the folder
 * name is tried instead.
 *
 * @return array 'plugins' => file => slug, 'themes' => stylesheet => slug.
 */
function sspw_wporg_slugs() {
	$slugs   = array(
		'plugins' => array(),
		'themes'  => array(),
	);
	$plugins = get_site_transient( 'update_plugins' );
	$themes  = get_site_transient( 'update_themes' );

	foreach ( array_keys( get_plugins() ) as $file ) {
		if ( is_object( $plugins ) && isset( $plugins->checked ) ) {
			foreach ( array( 'response', 'no_update' ) as $list ) {
				$item = isset( $plugins->{$list}[ $file ] ) ? (object) $plugins->{$list}[ $file ] : null;
				if ( $item && isset( $item->id, $item->slug ) && 0 === strpos( (string) $item->id, 'w.org/' ) ) {
					$slugs['plugins'][ $file ] = $item->slug;
				}
			}
		} elseif ( '.' !== dirname( $file ) ) {
			$slugs['plugins'][ $file ] = dirname( $file );
		}
	}

	foreach ( array_keys( wp_get_themes() ) as $stylesheet ) {
		if ( is_object( $themes ) && isset( $themes->checked ) ) {
			foreach ( array( 'response', 'no_update' ) as $list ) {
				$item = isset( $themes->{$list}[ $stylesheet ] ) ? (array) $themes->{$list}[ $stylesheet ] : array();
				if ( isset( $item['url'] ) && false !== strpos( $item['url'], 'wordpress.org/themes/' ) ) {
					$slugs['themes'][ $stylesheet ] = $stylesheet;
				}
			}
		} else {
			$slugs['themes'][ $stylesheet ] = $stylesheet;
		}
	}

	return $slugs;
}

/**
 * What WordPress's last update check (never forced here) says about the
 * installed version: 'old' with the newer version, 'current', or '' if unknown.
 *
 * @return array 'state' => 'old', 'current' or '', 'new' => version.
 */
function sspw_version_state( $type, $key ) {
	$updates = get_site_transient( 'plugin' === $type ? 'update_plugins' : 'update_themes' );

	if ( is_object( $updates ) && ! empty( $updates->response[ $key ] ) ) {
		$item = (array) $updates->response[ $key ];

		return array(
			'state' => 'old',
			'new'   => isset( $item['new_version'] ) ? (string) $item['new_version'] : '',
		);
	}

	return array(
		'state' => ( is_object( $updates ) && ! empty( $updates->no_update[ $key ] ) ) ? 'current' : '',
		'new'   => '',
	);
}

/**
 * Installed version, red when an update is waiting, green when up to date.
 */
function sspw_version_cell( $type, $key, $version ) {
	$state = sspw_version_state( $type, $key );

	if ( 'old' === $state['state'] ) {
		/* translators: %s: version number */
		return sprintf( '<span style="color:#d63638;font-weight:600" title="%1$s">%2$s</span>', esc_attr( sprintf( __( 'Update available: %s', 'staging-superpowers' ), $state['new'] ) ), esc_html( $version ) );
	}

	if ( 'current' === $state['state'] ) {
		return sprintf( '<span style="color:#00a32a;font-weight:600" title="%1$s">%2$s</span>', esc_attr__( 'Up to date', 'staging-superpowers' ), esc_html( $version ) );
	}

	return esc_html( $version );
}

/**
 * Newest version and release date of every WordPress.org plugin and theme
 * installed, in one request each, cached for 12 hours. Called after the page
 * has loaded, so the screen is never slow.
 */
function sspw_ajax_release_dates() {
	check_ajax_referer( 'sspw_release_dates', 'nonce' );

	if ( ! current_user_can( 'activate_plugins' ) && ! current_user_can( 'switch_themes' ) ) {
		wp_send_json_error();
	}

	$slugs    = sspw_wporg_slugs();
	$releases = get_transient( 'sspw_releases' );
	$releases = is_array( $releases ) ? $releases : array();
	$apis     = array(
		'plugins' => 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information',
		'themes'  => 'https://api.wordpress.org/themes/info/1.2/?action=theme_information',
	);
	$output   = array();

	foreach ( $apis as $type => $api ) {
		$missing = array_diff( array_values( $slugs[ $type ] ), array_keys( isset( $releases[ $type ] ) ? $releases[ $type ] : array() ) );

		if ( $missing ) {
			$url = $api;
			foreach ( array_unique( $missing ) as $slug ) {
				$url .= '&request[slugs][]=' . rawurlencode( $slug );
			}
			$url .= '&request[fields][sections]=0&request[fields][description]=0&request[fields][last_updated]=1';

			$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
			$found    = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );

			// Remember "not found" too, so local plugins are not looked up every time.
			if ( is_array( $found ) ) {
				foreach ( $missing as $slug ) {
					$releases[ $type ][ $slug ] = isset( $found[ $slug ]['last_updated'], $found[ $slug ]['version'] ) ? array( strtotime( $found[ $slug ]['last_updated'] ), (string) $found[ $slug ]['version'] ) : array();
				}
				set_transient( 'sspw_releases', $releases, 12 * HOUR_IN_SECONDS );
			}
		}

		$keys = 'plugins' === $type ? array_keys( get_plugins() ) : array_keys( wp_get_themes() );
		foreach ( $keys as $key ) {
			$slug    = isset( $slugs[ $type ][ $key ] ) ? $slugs[ $type ][ $key ] : '';
			$release = $slug && isset( $releases[ $type ][ $slug ] ) ? $releases[ $type ][ $slug ] : null;

			if ( $release ) {
				$html = sprintf(
					'<span title="%1$s">%2$s</span>',
					esc_attr( wp_date( get_option( 'date_format' ), (int) $release[0] ) ),
					/* translators: 1: version number, 2: time span, e.g. "3 days" */
					esc_html( sprintf( __( '%1$s, %2$s ago', 'staging-superpowers' ), $release[1], human_time_diff( (int) $release[0] ) ) )
				);
			} elseif ( $slug && null === $release ) {
				$html = esc_html__( 'Could not check', 'staging-superpowers' );
			} else {
				$html = esc_html__( 'Not on WordPress.org', 'staging-superpowers' );
			}

			$output[ ( 'plugins' === $type ? 'p:' : 't:' ) . $key ] = $html;
		}
	}

	wp_send_json_success( $output );
}

/**
 * WordPress's own plugin details window, on the changelog tab.
 */
function sspw_changelog_link( $slug ) {
	return sprintf(
		'<a href="%1$s" class="thickbox open-plugin-details-modal">%2$s</a>',
		esc_url( self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . rawurlencode( $slug ) . '&section=changelog&TB_iframe=true&width=772&height=600' ) ),
		esc_html__( 'View changelog', 'staging-superpowers' )
	);
}

/**
 * Active first, then alphabetical by name.
 *
 * @param array    $items  key => name.
 * @param string[] $active Keys that are active.
 * @return string[] Keys in order.
 */
function sspw_sorted_keys( $items, $active ) {
	$keys = array_keys( $items );

	usort(
		$keys,
		function ( $a, $b ) use ( $items, $active ) {
			$on = (int) in_array( $b, $active, true ) - (int) in_array( $a, $active, true );

			return $on ? $on : strnatcasecmp( $items[ $a ], $items[ $b ] );
		}
	);

	return $keys;
}

/*
 * The page.
 */

function sspw_output_troubleshooting() {
	$notice = get_transient( 'sspw_notice_' . get_current_user_id() );
	if ( $notice ) {
		delete_transient( 'sspw_notice_' . get_current_user_id() );
		echo '<div class="notice notice-success inline"><p>' . esc_html( $notice ) . '</p></div>';
	}

	echo '<h2>' . esc_html__( 'Troubleshooting', 'staging-superpowers' ) . '</h2>';

	if ( ! sspw_is_armed() ) {
		echo '<p>' . esc_html__( 'Troubleshooting is only available while Staging Superpowers is turned on for this site, so a live site can never be switched off by accident.', 'staging-superpowers' ) . '</p>';
		return;
	}

	echo '<p>' . esc_html__( 'When something is broken, the cause is often another plugin or the theme. Switch them off here, check if the problem is gone, then switch them back on. A plugin or theme that was updated recently is a likely suspect. After every switch the site is loaded in the background, and if that shows a fatal error, the switch is undone straight away.', 'staging-superpowers' ) . '</p>';

	$action = admin_url( 'admin-post.php?action=sspw_troubleshoot' );
	wp_nonce_field( 'sspw_troubleshoot', 'sspw_nonce' );

	if ( current_user_can( 'activate_plugins' ) ) {
		sspw_output_plugin_tools( $action );
	}

	if ( current_user_can( 'switch_themes' ) ) {
		sspw_output_theme_tools( $action );
	}

	sspw_output_more_tools();

	sspw_inline_script(
		'var sspwCells = jQuery( ".sspw-released" );
		if ( sspwCells.length ) {
			jQuery.post( ajaxurl, { action: "sspw_release_dates", nonce: ' . wp_json_encode( wp_create_nonce( 'sspw_release_dates' ) ) . ' } ).done( function ( r ) {
				sspwCells.each( function () {
					var key = jQuery( this ).data( "key" );
					jQuery( this ).find( ".sspw-release-text" ).html( r && r.success && r.data[ key ] ? r.data[ key ] : ' . wp_json_encode( __( 'Could not check', 'staging-superpowers' ) ) . ' );
				} );
			} ).fail( function () {
				sspwCells.find( ".sspw-release-text" ).text( ' . wp_json_encode( __( 'Could not check', 'staging-superpowers' ) ) . ' );
			} );
		}'
	);
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

function sspw_table_head( $columns, $checkbox = false ) {
	echo '<thead><tr>';
	if ( $checkbox ) {
		echo '<td class="manage-column column-cb check-column"><label class="screen-reader-text" for="sspw-select-all">' . esc_html__( 'Select all', 'staging-superpowers' ) . '</label><input id="sspw-select-all" type="checkbox" /></td>';
	}
	foreach ( $columns as $label => $width ) {
		printf( '<th scope="col" class="manage-column"%1$s>%2$s</th>', $width ? ' style="width:' . esc_attr( $width ) . '"' : '', esc_html( $label ) );
	}
	echo '</tr></thead>';
}

function sspw_output_plugin_tools( $action ) {
	$all      = get_plugins();
	$active   = (array) get_option( 'active_plugins', array() );
	$kept     = sspw_always_kept_plugins();
	$disabled = array_intersect( sspw_disabled_plugins(), array_keys( $all ) );
	$plan     = get_transient( 'sspw_plan_' . get_current_user_id() );

	echo '<h3>' . esc_html__( 'Plugins', 'staging-superpowers' ) . '</h3>';

	// Step 2 of switching off: show exactly what will happen before anything changes.
	if ( is_array( $plan ) && ! empty( $plan['off'] ) ) {
		echo '<div class="notice notice-info inline"><p><strong>' . esc_html__( 'Check before switching off', 'staging-superpowers' ) . '</strong></p>';
		/* translators: %s: list of plugin names */
		echo '<p>' . esc_html( sprintf( __( 'These plugins will be switched off: %s.', 'staging-superpowers' ), implode( ', ', array_map( 'sspw_plugin_label', $plan['off'] ) ) ) ) . '</p>';
		if ( ! empty( $plan['also'] ) ) {
			echo '<p>' . esc_html__( 'Some of them were not selected, but they need a plugin that is being switched off, so they go off too:', 'staging-superpowers' ) . '</p><ul style="list-style:disc;margin-left:20px">';
			foreach ( $plan['also'] as $file => $needs ) {
				/* translators: 1: plugin name, 2: list of plugin names it needs */
				echo '<li>' . esc_html( sprintf( __( '%1$s, because it needs %2$s', 'staging-superpowers' ), sspw_plugin_label( $file ), implode( ', ', $needs ) ) ) . '</li>';
			}
			echo '</ul>';
		}
		echo '<p>';
		sspw_button( $action, 'disable_plugins', __( 'Switch them off', 'staging-superpowers' ), '', true );
		sspw_button( admin_url( 'admin-post.php?action=sspw_troubleshoot_cancel' ), 'cancel', __( 'Cancel', 'staging-superpowers' ) );
		echo '</p></div>';
		return;
	}

	if ( $disabled ) {
		echo '<div class="notice notice-warning inline"><p><strong>' . esc_html(
			sprintf(
				/* translators: %d: number of plugins */
				_n( '%d plugin is switched off for troubleshooting:', '%d plugins are switched off for troubleshooting:', count( $disabled ), 'staging-superpowers' ),
				count( $disabled )
			)
		) . '</strong> ' . esc_html( implode( ', ', array_map( 'sspw_plugin_label', $disabled ) ) ) . '</p><p>';
		sspw_button( $action, 'restore_plugins', __( 'Switch everything back on', 'staging-superpowers' ), '', true );
		echo '</p></div>';
	}

	echo '<p>' . esc_html__( 'Select plugins, then switch them off or on. Staging Superpowers always stays on.', 'staging-superpowers' ) . '</p>';
	echo '<div class="tablenav top"><div class="alignleft actions">';
	sspw_button( $action, 'plan_off', __( 'Switch off', 'staging-superpowers' ) );
	sspw_button( $action, 'switch_on', __( 'Switch on', 'staging-superpowers' ) );
	echo '</div><br class="clear" /></div>';

	$slugs = sspw_wporg_slugs();

	echo '<table class="wp-list-table widefat fixed striped sspw-table">';
	sspw_table_head(
		array(
			__( 'Plugin', 'staging-superpowers' )         => '',
			__( 'Status', 'staging-superpowers' )         => '16%',
			__( 'On this site', 'staging-superpowers' )   => '11%',
			__( 'Latest release', 'staging-superpowers' ) => '20%',
		),
		true
	);
	echo '<tbody>';

	foreach ( sspw_sorted_keys( wp_list_pluck( $all, 'Name' ), $active ) as $file ) {
		$data      = $all[ $file ];
		$is_active = in_array( $file, $active, true );
		$is_kept   = in_array( $file, $kept, true );
		$id        = 'sspw-plugin-' . md5( $file );

		if ( $is_kept ) {
			$status = __( 'Active, always on', 'staging-superpowers' );
		} elseif ( $is_active ) {
			$status = __( 'Active', 'staging-superpowers' );
		} elseif ( in_array( $file, $disabled, true ) ) {
			$status = __( 'Inactive, switched off here', 'staging-superpowers' );
		} else {
			$status = __( 'Inactive', 'staging-superpowers' );
		}

		echo '<tr' . ( $is_active ? ' class="active"' : '' ) . '>';
		printf(
			'<th scope="row" class="check-column"><label class="screen-reader-text" for="%1$s">%2$s</label><input id="%1$s" type="checkbox" name="sspw_plugins[]" value="%3$s"%4$s /></th>',
			esc_attr( $id ),
			/* translators: %s: plugin name */
			esc_html( sprintf( __( 'Select %s', 'staging-superpowers' ), $data['Name'] ) ),
			esc_attr( $file ),
			$is_kept ? ' disabled' : ''
		);
		printf( '<td><label for="%1$s"><strong>%2$s</strong></label></td>', esc_attr( $id ), esc_html( $data['Name'] ) );
		echo '<td>' . ( $is_active ? '<strong>' . esc_html( $status ) . '</strong>' : esc_html( $status ) ) . '</td>';
		echo '<td>' . wp_kses_post( sspw_version_cell( 'plugin', $file, $data['Version'] ) ) . '</td>';
		echo '<td class="sspw-released" data-key="' . esc_attr( 'p:' . $file ) . '"><span class="sspw-release-text">' . esc_html__( 'Checking...', 'staging-superpowers' ) . '</span>';
		if ( isset( $slugs['plugins'][ $file ] ) ) {
			echo '<br />' . wp_kses_post( sspw_changelog_link( $slugs['plugins'][ $file ] ) );
		}
		echo '</td>';
		echo '</tr>';
	}

	echo '</tbody></table>';
}

function sspw_output_theme_tools( $action ) {
	$current  = get_stylesheet();
	$previous = sspw_previous_theme() ? wp_get_theme( sspw_previous_theme() ) : false;

	echo '<h3>' . esc_html__( 'Themes', 'staging-superpowers' ) . '</h3>';
	echo '<p>' . esc_html__( 'Menus, widgets and customizer settings are kept for each theme, so switching back restores them.', 'staging-superpowers' ) . '</p>';

	if ( $previous && $previous->exists() && $previous->get_stylesheet() !== $current ) {
		echo '<p>';
		/* translators: %s: theme name */
		sspw_button( $action, 'theme_restore', sprintf( __( 'Back to %s', 'staging-superpowers' ), $previous->get( 'Name' ) ), '', true );
		echo '</p>';
	}

	$themes = wp_get_themes();
	$names  = array();
	foreach ( $themes as $stylesheet => $theme ) {
		$names[ $stylesheet ] = $theme->get( 'Name' );
	}

	echo '<table class="wp-list-table widefat fixed striped sspw-table">';
	sspw_table_head(
		array(
			__( 'Theme', 'staging-superpowers' )          => '',
			__( 'Status', 'staging-superpowers' )         => '16%',
			__( 'On this site', 'staging-superpowers' )   => '11%',
			__( 'Latest release', 'staging-superpowers' ) => '20%',
			''                                            => '13%',
		)
	);
	echo '<tbody>';

	foreach ( sspw_sorted_keys( $names, array( $current ) ) as $stylesheet ) {
		$theme     = $themes[ $stylesheet ];
		$is_active = $stylesheet === $current;
		$parent    = $theme->parent();

		if ( $is_active ) {
			$status = __( 'Active', 'staging-superpowers' );
		} elseif ( $previous && $previous->get_stylesheet() === $stylesheet ) {
			$status = __( 'Inactive, switched away from here', 'staging-superpowers' );
		} else {
			$status = __( 'Inactive', 'staging-superpowers' );
		}

		echo '<tr' . ( $is_active ? ' class="active"' : '' ) . '>';
		echo '<td><strong>' . esc_html( $theme->get( 'Name' ) ) . '</strong>';
		if ( $parent ) {
			/* translators: %s: parent theme name */
			echo '<br /><span class="description">' . esc_html( sprintf( __( 'Child theme of %s', 'staging-superpowers' ), $parent->get( 'Name' ) ) ) . '</span>';
		}
		echo '</td>';
		echo '<td>' . ( $is_active ? '<strong>' . esc_html( $status ) . '</strong>' : esc_html( $status ) ) . '</td>';
		echo '<td>' . wp_kses_post( sspw_version_cell( 'theme', $stylesheet, $theme->get( 'Version' ) ) ) . '</td>';
		echo '<td class="sspw-released" data-key="' . esc_attr( 't:' . $stylesheet ) . '"><span class="sspw-release-text">' . esc_html__( 'Checking...', 'staging-superpowers' ) . '</span></td>';
		echo '<td>';
		if ( ! $is_active ) {
			printf(
				'<button type="submit" class="button sspw-do" formaction="%1$s" formmethod="post" name="sspw_theme" value="%2$s">%3$s</button>',
				esc_url( $action ),
				esc_attr( $stylesheet ),
				esc_html__( 'Use this theme', 'staging-superpowers' )
			);
		}
		echo '</td></tr>';
	}

	echo '</tbody></table>';
}

function sspw_output_more_tools() {
	$links = array();

	if ( sspw_has_woocommerce() ) {
		$links[ admin_url( 'admin.php?page=wc-status&tab=tools' ) ] = __( 'WooCommerce tools (clear transients, regenerate lookup tables, and more)', 'staging-superpowers' );
		$links[ admin_url( 'admin.php?page=wc-status&tab=logs' ) ]  = __( 'WooCommerce logs', 'staging-superpowers' );
	}

	if ( sspw_has_action_scheduler() ) {
		$links[ sspw_admin_links()['actions'] ] = __( 'Pending scheduled actions', 'staging-superpowers' );
	}

	$links[ admin_url( 'site-health.php' ) ] = __( 'Site Health', 'staging-superpowers' );

	echo '<h3>' . esc_html__( 'More tools', 'staging-superpowers' ) . '</h3><ul style="list-style:disc;margin-left:20px">';
	foreach ( $links as $url => $label ) {
		printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $url ), esc_html( $label ) );
	}
	echo '</ul>';
}

/*
 * Doing it.
 */

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

/**
 * Loads the home page logged out and the dashboard as the current user, in the
 * background. Each request carries a one-time key, so a fatal error page can
 * add the file that caused it (see sspw_mark_fatal_error()).
 *
 * @return array 'status' => 'ok', 'broken' or 'unchecked', 'culprit' => name or ''.
 */
function sspw_site_check() {
	$key = wp_generate_password( 20, false );
	set_transient( 'sspw_check_key', $key, 2 * MINUTE_IN_SECONDS );

	$cookies = array();
	foreach ( $_COOKIE as $name => $value ) {
		if ( 0 === strpos( (string) $name, 'wordpress_' ) && is_string( $value ) ) {
			$cookies[] = new WP_Http_Cookie(
				array(
					'name'  => (string) $name,
					'value' => sanitize_text_field( wp_unslash( $value ) ),
				)
			);
		}
	}

	$requests = array(
		array( home_url( '/' ), array() ),
		array( admin_url(), $cookies ),
	);
	$checked  = 0;

	foreach ( $requests as $request ) {
		$response = wp_remote_get(
			add_query_arg( 'sspw_check', $key, $request[0] ),
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'cookies'     => $request[1],
				// Core's own filter for loopback requests (see class-wp-http-streams.php).
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter.
				'headers'     => array( 'Cache-Control' => 'no-cache' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			continue;
		}

		++$checked;
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( 500 === $code || false !== stripos( $body, 'critical error on this website' ) || preg_match( '#<b>Fatal error</b>#i', $body ) ) {
			delete_transient( 'sspw_check_key' );

			return array(
				'status'  => 'broken',
				'culprit' => sspw_fatal_culprit( $body ),
			);
		}
	}

	delete_transient( 'sspw_check_key' );

	return array(
		'status'  => $checked ? 'ok' : 'unchecked',
		'culprit' => '',
	);
}

/**
 * The plugin or theme the fatal error came from, by the path in the error.
 */
function sspw_fatal_culprit( $body ) {
	if ( ! preg_match( '#<!-- sspw-fatal-file: (.+?) -->#', $body, $m ) && ! preg_match( '#in <b>([^<]+\.php)</b> on line#', $body, $m ) ) {
		return '';
	}

	$file = wp_normalize_path( html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5 ) );

	foreach ( array_keys( get_plugins() ) as $plugin ) {
		$folder = wp_normalize_path( WP_PLUGIN_DIR . '/' . dirname( $plugin ) . '/' );
		if ( '.' !== dirname( $plugin ) && 0 === strpos( $file, $folder ) ) {
			return sspw_plugin_label( $plugin );
		}
	}

	foreach ( wp_get_themes() as $theme ) {
		if ( 0 === strpos( $file, wp_normalize_path( $theme->get_stylesheet_directory() . '/' ) ) ) {
			return $theme->get( 'Name' );
		}
	}

	return '';
}

/**
 * After a switch broke the site and was undone: one changelog entry and the message.
 */
function sspw_rolled_back( $check ) {
	$GLOBALS['sspw_quiet_log'] = false;

	if ( $check['culprit'] ) {
		/* translators: %s: plugin or theme name */
		sspw_troubleshoot_log( sprintf( __( 'Troubleshooting: switch undone because %s caused a fatal error', 'staging-superpowers' ), $check['culprit'] ) );
		/* translators: %s: plugin or theme name */
		sspw_troubleshoot_done( sprintf( __( 'That broke the site: %s caused a fatal error. Nothing was changed, everything is back as it was.', 'staging-superpowers' ), $check['culprit'] ) );
	}

	sspw_troubleshoot_log( __( 'Troubleshooting: switch undone because of a fatal error', 'staging-superpowers' ) );
	sspw_troubleshoot_done( __( 'That broke the site with a fatal error. Nothing was changed, everything is back as it was.', 'staging-superpowers' ) );
}

function sspw_troubleshoot_log( $message ) {
	if ( function_exists( 'sspw_log' ) ) {
		sspw_log( $message );
	}
}

/**
 * Plugin names, comma separated, for the changelog and messages.
 */
function sspw_names( $files ) {
	return implode( ', ', array_map( 'sspw_plugin_label', $files ) );
}

function sspw_unchecked_note( $check ) {
	return 'unchecked' === $check['status'] ? ' ' . __( 'The site could not load itself in the background to check for errors, so check it yourself.', 'staging-superpowers' ) : '';
}

function sspw_cancel_troubleshoot_plan() {
	check_admin_referer( 'sspw_troubleshoot', 'sspw_nonce' );
	delete_transient( 'sspw_plan_' . get_current_user_id() );
	wp_safe_redirect( sspw_troubleshoot_url() );
	exit;
}

function sspw_troubleshoot_done( $message ) {
	set_transient( 'sspw_notice_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );
	wp_safe_redirect( sspw_troubleshoot_url() );
	exit;
}

/**
 * Switch plugins on (back on), check the site, write one entry.
 *
 * @param string[] $files Plugin files to switch on.
 * @param array    $also  file => name of the selected plugin that needs it.
 */
function sspw_switch_on( $files, $also = array() ) {
	$before = (array) get_option( 'active_plugins', array() );

	// Plugins turned off by troubleshooting come back silently; plugins that were never on
	// get a normal activation, so they can create their tables and defaults.
	$GLOBALS['sspw_quiet_log'] = true;
	$failed                    = sspw_activate_plugins( $files, sspw_disabled_plugins() );

	$check = sspw_site_check();
	if ( 'broken' === $check['status'] ) {
		update_option( 'active_plugins', $before );
		sspw_rolled_back( $check );
	}
	$GLOBALS['sspw_quiet_log'] = false;

	$on = array_values( array_diff( $files, $failed ) );

	if ( $on ) {
		/* translators: 1: number of plugins, 2: list of plugin names */
		sspw_troubleshoot_log( sprintf( _n( 'Troubleshooting: switched on %1$d plugin: %2$s', 'Troubleshooting: switched on %1$d plugins: %2$s', count( $on ), 'staging-superpowers' ), count( $on ), sspw_names( $on ) ) );
	}

	// Whatever is on again is no longer "switched off here"; anything that failed stays listed.
	$still_off = array_values( array_diff( sspw_disabled_plugins(), $on ) );
	if ( $still_off ) {
		update_option( 'sspw_disabled_plugins', $still_off, false );
	} else {
		delete_option( 'sspw_disabled_plugins' );
	}

	/* translators: %d: number of plugins */
	$message = sprintf( _n( '%d plugin switched on.', '%d plugins switched on.', count( $on ), 'staging-superpowers' ), count( $on ) );

	foreach ( $also as $file => $needed_by ) {
		if ( in_array( $file, $on, true ) ) {
			/* translators: 1: plugin name, 2: plugin name */
			$message .= ' ' . sprintf( __( '%1$s was switched on too, because %2$s needs it.', 'staging-superpowers' ), sspw_plugin_label( $file ), $needed_by );
		}
	}

	if ( $failed ) {
		/* translators: %s: list of plugin names */
		$message .= ' ' . sprintf( __( 'Could not switch on: %s', 'staging-superpowers' ), sspw_names( $failed ) );
	}

	sspw_troubleshoot_done( $message . sspw_unchecked_note( $check ) );
}

function sspw_handle_troubleshoot() {
	check_admin_referer( 'sspw_troubleshoot', 'sspw_nonce' );

	if ( ! sspw_is_armed() ) {
		wp_die( esc_html__( 'Troubleshooting is only available while Staging Superpowers is turned on for this site.', 'staging-superpowers' ) );
	}

	$do    = isset( $_POST['sspw_do'] ) ? sanitize_key( wp_unslash( $_POST['sspw_do'] ) ) : '';
	$theme = isset( $_POST['sspw_theme'] ) ? sanitize_text_field( wp_unslash( $_POST['sspw_theme'] ) ) : '';

	if ( '' !== $theme ) {
		$do = 'theme_use';
	}

	if ( in_array( $do, array( 'plan_off', 'disable_plugins', 'switch_on', 'restore_plugins' ), true ) && ! current_user_can( 'activate_plugins' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'staging-superpowers' ) );
	}

	if ( 0 === strpos( $do, 'theme_' ) && ! current_user_can( 'switch_themes' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'staging-superpowers' ) );
	}

	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	$selected = isset( $_POST['sspw_plugins'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['sspw_plugins'] ) ) : array();
	$selected = array_values( array_intersect( $selected, array_keys( get_plugins() ) ) );

	switch ( $do ) {
		case 'plan_off':
			$plan = sspw_plan_switch_off( $selected );

			if ( ! $plan['off'] ) {
				sspw_troubleshoot_done( __( 'Select at least one active plugin to switch off.', 'staging-superpowers' ) );
			}

			set_transient( 'sspw_plan_' . get_current_user_id(), $plan, HOUR_IN_SECONDS );
			wp_safe_redirect( sspw_troubleshoot_url() );
			exit;

		case 'disable_plugins':
			$plan = get_transient( 'sspw_plan_' . get_current_user_id() );
			delete_transient( 'sspw_plan_' . get_current_user_id() );

			$before = (array) get_option( 'active_plugins', array() );
			$off    = is_array( $plan ) ? array_values( array_intersect( (array) $plan['off'], $before ) ) : array();
			$off    = array_values( array_diff( $off, sspw_always_kept_plugins() ) );

			if ( ! $off ) {
				sspw_troubleshoot_done( __( 'Nothing to switch off.', 'staging-superpowers' ) );
			}

			$GLOBALS['sspw_quiet_log'] = true;
			deactivate_plugins( $off, true );

			$check = sspw_site_check();
			if ( 'broken' === $check['status'] ) {
				update_option( 'active_plugins', $before );
				sspw_rolled_back( $check );
			}
			$GLOBALS['sspw_quiet_log'] = false;

			/* translators: 1: number of plugins, 2: list of plugin names */
			sspw_troubleshoot_log( sprintf( _n( 'Troubleshooting: switched off %1$d plugin: %2$s', 'Troubleshooting: switched off %1$d plugins: %2$s', count( $off ), 'staging-superpowers' ), count( $off ), sspw_names( $off ) ) );

			// Several rounds add up, so "switch everything back on" restores everything.
			update_option( 'sspw_disabled_plugins', array_values( array_unique( array_merge( sspw_disabled_plugins(), $off ) ) ), false );

			/* translators: %d: number of plugins */
			sspw_troubleshoot_done( sprintf( _n( '%d plugin switched off.', '%d plugins switched off.', count( $off ), 'staging-superpowers' ), count( $off ) ) . sspw_unchecked_note( $check ) );
			break;

		case 'switch_on':
			$plan = sspw_plan_switch_on( $selected );

			if ( ! $plan['on'] ) {
				sspw_troubleshoot_done( __( 'Select at least one inactive plugin to switch on.', 'staging-superpowers' ) );
			}

			sspw_switch_on( $plan['on'], $plan['also'] );
			break;

		case 'restore_plugins':
			$back = array_values( array_diff( array_intersect( sspw_disabled_plugins(), array_keys( get_plugins() ) ), (array) get_option( 'active_plugins', array() ) ) );

			if ( ! $back ) {
				delete_option( 'sspw_disabled_plugins' );
				sspw_troubleshoot_done( __( 'Everything is already back on.', 'staging-superpowers' ) );
			}

			sspw_switch_on( $back );
			break;

		case 'theme_use':
		case 'theme_restore':
			$current = get_stylesheet();
			$target  = 'theme_restore' === $do ? sspw_previous_theme() : $theme;
			$new     = $target ? wp_get_theme( $target ) : false;

			if ( ! $new || ! $new->exists() || $target === $current ) {
				sspw_troubleshoot_done( __( 'That theme is not available.', 'staging-superpowers' ) );
			}

			$old_name                  = wp_get_theme()->get( 'Name' );
			$GLOBALS['sspw_quiet_log'] = true;
			switch_theme( $target );

			$check = sspw_site_check();
			if ( 'broken' === $check['status'] ) {
				switch_theme( $current );
				sspw_rolled_back( $check );
			}
			$GLOBALS['sspw_quiet_log'] = false;

			/* translators: 1: old theme name, 2: new theme name */
			sspw_troubleshoot_log( sprintf( __( 'Troubleshooting: theme switched from %1$s to %2$s', 'staging-superpowers' ), $old_name, $new->get( 'Name' ) ) );

			// Remember the theme from before troubleshooting started, not each step in between.
			if ( 'theme_restore' === $do || sspw_previous_theme() === $target ) {
				delete_option( 'sspw_previous_theme' );
			} elseif ( '' === sspw_previous_theme() ) {
				update_option( 'sspw_previous_theme', $current, false );
			}

			/* translators: %s: theme name */
			sspw_troubleshoot_done( sprintf( __( 'Switched to %s.', 'staging-superpowers' ), $new->get( 'Name' ) ) . sspw_unchecked_note( $check ) );
			break;
	}

	wp_safe_redirect( sspw_troubleshoot_url() );
	exit;
}
