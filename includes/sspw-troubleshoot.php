<?php
/**
 * Troubleshooting sub-page: switch plugins and the theme off and back on to find
 * conflicts. Only works while the site is armed, so a live site can never be
 * bulk-disabled from here.
 *
 * Plugins are switched silently (no activation/deactivation routines), because
 * this is a temporary test and those routines can reset schedules or settings.
 * Plugins that need a plugin being switched off go off with it, and switching
 * one on brings back what it needs.
 *
 * Every switch is checked: the site is loaded in the background, logged out and
 * as the current user, and if that hits a fatal error the switch is undone.
 * Each action writes one changelog entry, however many plugins it touched.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_sspw_troubleshoot', 'sspw_handle_troubleshoot' );
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
 * The selected plugins plus every active plugin that needs one of them (it
 * would break otherwise), repeated until nothing left on needs something off.
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
 * Versions.
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
 * WordPress's last update check (never forced here) for one plugin or theme.
 * WordPress.org and many premium updaters fill it in.
 *
 * @return string Newest version it knows, or ''.
 */
function sspw_update_check_version( $type, $key ) {
	$updates = get_site_transient( 'plugin' === $type ? 'update_plugins' : 'update_themes' );

	if ( ! is_object( $updates ) ) {
		return '';
	}

	foreach ( array( 'response', 'no_update' ) as $list ) {
		if ( ! empty( $updates->{$list}[ $key ] ) ) {
			$item = (array) $updates->{$list}[ $key ];

			return isset( $item['new_version'] ) ? (string) $item['new_version'] : '';
		}
	}

	return '';
}

/**
 * Latest release of a plugin: WordPress.org data (from the cache filled by the
 * release check), else the version WordPress's update check knows, which covers
 * premium plugins with their own updater.
 *
 * @return array 'version', 'date' (timestamp or 0), 'changelog_url' (or '').
 */
function sspw_plugin_release_info( $file, $slug = '', $release = array() ) {
	$info = array(
		'version'       => '',
		'date'          => 0,
		'changelog_url' => '',
	);

	if ( $release ) {
		$info['version'] = $release[1];
		$info['date']    = (int) $release[0];
	} elseif ( ! $slug ) {
		$info['version'] = sspw_update_check_version( 'plugin', $file );
	}

	if ( $slug ) {
		$info['changelog_url'] = self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . rawurlencode( $slug ) . '&section=changelog&TB_iframe=true&width=772&height=600' );
	}

	/**
	 * Release details shown for a plugin in Troubleshooting, for plugins that are
	 * not on WordPress.org or that need better details.
	 *
	 * @param array  $info {
	 *     @type string $version       Newest version.
	 *     @type int    $date          Release date as a timestamp, or 0 if unknown.
	 *     @type string $changelog_url Changelog page, or '' for none.
	 * }
	 * @param string $file Plugin file, for example "my-plugin/my-plugin.php".
	 */
	$info = (array) apply_filters( 'sspw_plugin_release_info', $info, $file );

	return array(
		'version'       => isset( $info['version'] ) ? (string) $info['version'] : '',
		'date'          => isset( $info['date'] ) ? (int) $info['date'] : 0,
		'changelog_url' => isset( $info['changelog_url'] ) ? (string) $info['changelog_url'] : '',
	);
}

/**
 * The Latest release cell.
 */
function sspw_release_html( $info, $not_found ) {
	if ( '' === $info['version'] ) {
		return esc_html( $not_found );
	}

	if ( $info['date'] ) {
		$html = sprintf(
			'<span title="%1$s">%2$s</span>',
			esc_attr( wp_date( get_option( 'date_format' ), $info['date'] ) ),
			/* translators: 1: version number, 2: time span, e.g. "3 days" */
			esc_html( sprintf( __( '%1$s, %2$s ago', 'staging-superpowers' ), $info['version'], human_time_diff( $info['date'] ) ) )
		);
	} else {
		$html = esc_html( $info['version'] );
	}

	if ( '' !== $info['changelog_url'] ) {
		$thickbox = false !== strpos( $info['changelog_url'], 'TB_iframe=true' );
		$html    .= sprintf(
			'<br /><a href="%1$s"%2$s>%3$s</a>',
			esc_url( $info['changelog_url'] ),
			$thickbox ? ' class="thickbox open-plugin-details-modal"' : ' target="_blank" rel="noopener noreferrer"',
			esc_html__( 'View changelog', 'staging-superpowers' )
		);
	}

	return $html;
}

/**
 * Installed version, red when a newer one is known, green when it is the newest.
 */
function sspw_version_cell( $installed, $latest ) {
	if ( '' === $latest ) {
		return esc_html( $installed );
	}

	if ( version_compare( $latest, $installed, '>' ) ) {
		/* translators: %s: version number */
		return sprintf( '<span style="color:#d63638;font-weight:600" title="%1$s">%2$s</span>', esc_attr( sprintf( __( 'Update available: %s', 'staging-superpowers' ), $latest ) ), esc_html( $installed ) );
	}

	return sprintf( '<span style="color:#00a32a;font-weight:600" title="%1$s">%2$s</span>', esc_attr__( 'Up to date', 'staging-superpowers' ), esc_html( $installed ) );
}

/**
 * WordPress.org's newest version and release date of every plugin and theme it
 * hosts here, one request per type, cached for 12 hours.
 *
 * @param bool $fetch False to only read the cache.
 * @return array 'plugins'|'themes' => slug => array( time, version ), or array() if not found.
 */
function sspw_wporg_releases( $fetch ) {
	$releases = get_transient( 'sspw_releases' );
	$releases = is_array( $releases ) ? $releases : array();

	if ( ! $fetch ) {
		return $releases;
	}

	$slugs = sspw_wporg_slugs();
	$apis  = array(
		'plugins' => 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information',
		'themes'  => 'https://api.wordpress.org/themes/info/1.2/?action=theme_information',
	);

	foreach ( $apis as $type => $api ) {
		$missing = array_unique( array_diff( array_values( $slugs[ $type ] ), array_keys( isset( $releases[ $type ] ) ? $releases[ $type ] : array() ) ) );

		if ( ! $missing ) {
			continue;
		}

		$url = $api;
		foreach ( $missing as $slug ) {
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

	return $releases;
}

/**
 * The release and version cells of every row, keyed 'p:file' or 't:stylesheet'.
 * Rows still waiting for WordPress.org get null, unless $fetch asks for it.
 */
function sspw_release_cells( $fetch ) {
	$slugs    = sspw_wporg_slugs();
	$releases = sspw_wporg_releases( $fetch );
	$cells    = array();

	foreach ( get_plugins() as $file => $data ) {
		$slug = isset( $slugs['plugins'][ $file ] ) ? $slugs['plugins'][ $file ] : '';

		if ( $slug && ! isset( $releases['plugins'][ $slug ] ) ) {
			$cells[ 'p:' . $file ] = null;
			continue;
		}

		$info    = sspw_plugin_release_info( $file, $slug && $releases['plugins'][ $slug ] ? $slug : '', $slug ? $releases['plugins'][ $slug ] : array() );
		$updates = sspw_update_check_version( 'plugin', $file );

		$cells[ 'p:' . $file ] = array(
			'release' => sspw_release_html( $info, __( 'Not on WordPress.org', 'staging-superpowers' ) ),
			'version' => sspw_version_cell( $data['Version'], $updates ? $updates : $info['version'] ),
		);
	}

	foreach ( wp_get_themes() as $stylesheet => $theme ) {
		$slug    = isset( $slugs['themes'][ $stylesheet ] ) ? $slugs['themes'][ $stylesheet ] : '';
		$updates = sspw_update_check_version( 'theme', $stylesheet );

		if ( $slug && ! isset( $releases['themes'][ $slug ] ) ) {
			$cells[ 't:' . $stylesheet ] = null;
			continue;
		}

		$release = $slug ? $releases['themes'][ $slug ] : array();
		$info    = array(
			'version'       => $release ? $release[1] : $updates,
			'date'          => $release ? (int) $release[0] : 0,
			'changelog_url' => '',
		);

		$cells[ 't:' . $stylesheet ] = array(
			'release' => sspw_release_html( $info, __( 'Not on WordPress.org', 'staging-superpowers' ) ),
			'version' => sspw_version_cell( $theme->get( 'Version' ), $updates ? $updates : $info['version'] ),
		);
	}

	return $cells;
}

/**
 * Called after the page has loaded, so the screen is never slow.
 */
function sspw_ajax_release_dates() {
	check_ajax_referer( 'sspw_release_dates', 'nonce' );

	if ( ! current_user_can( 'activate_plugins' ) && ! current_user_can( 'switch_themes' ) ) {
		wp_send_json_error();
	}

	$cells = array();
	foreach ( sspw_release_cells( true ) as $key => $cell ) {
		$cells[ $key ] = $cell ? $cell : array(
			'release' => esc_html__( 'Could not check', 'staging-superpowers' ),
			'version' => '',
		);
	}

	wp_send_json_success( $cells );
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

	echo '<p>' . esc_html__( 'When something is broken, the cause is often another plugin or the theme. Disable them here, check if the problem is gone, then enable them again. A plugin or theme with a recent release is a likely suspect. Plugins that need a plugin you disable are disabled with it, and enabling a plugin also enables what it needs. After every change the site is loaded in the background, and if that shows a fatal error, the change is undone straight away.', 'staging-superpowers' ) . '</p>';

	$action = admin_url( 'admin-post.php?action=sspw_troubleshoot' );
	wp_nonce_field( 'sspw_troubleshoot', 'sspw_nonce' );

	$cells = sspw_release_cells( false );

	if ( current_user_can( 'activate_plugins' ) ) {
		sspw_output_plugin_tools( $action, $cells );
	}

	if ( current_user_can( 'switch_themes' ) ) {
		sspw_output_theme_tools( $action, $cells );
	}

	if ( in_array( null, $cells, true ) ) {
		sspw_inline_script(
			'jQuery.post( ajaxurl, { action: "sspw_release_dates", nonce: ' . wp_json_encode( wp_create_nonce( 'sspw_release_dates' ) ) . ' } ).always( function ( r ) {
				jQuery( ".sspw-released" ).each( function () {
					var cell = r && r.success ? r.data[ jQuery( this ).data( "key" ) ] : null;
					jQuery( this ).html( cell ? cell.release : ' . wp_json_encode( esc_html__( 'Could not check', 'staging-superpowers' ) ) . ' );
					if ( cell && cell.version ) {
						jQuery( this ).siblings( ".sspw-version" ).html( cell.version );
					}
				} );
			} );'
		);
	}
}

/**
 * A submit button for the page form. $name and $value say what to do.
 */
function sspw_action_button( $action, $name, $value, $label, $icon = '', $classes = 'button' ) {
	printf(
		'<button type="submit" class="%1$s sspw-do" formaction="%2$s" formmethod="post" name="%3$s" value="%4$s">%5$s%6$s</button> ',
		esc_attr( $classes ),
		esc_url( $action ),
		esc_attr( $name ),
		esc_attr( $value ),
		$icon ? '<span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true" style="font-size:16px;width:16px;height:16px;line-height:inherit;vertical-align:top;margin-right:3px"></span>' : '',
		esc_html( $label )
	);
}

function sspw_bulk_buttons( $action, $where ) {
	echo '<div class="tablenav ' . esc_attr( $where ) . '"><div class="alignleft actions">';
	sspw_action_button( $action, 'sspw_do', 'disable', __( 'Disable selected', 'staging-superpowers' ), 'dashicons-controls-pause' );
	sspw_action_button( $action, 'sspw_do', 'enable', __( 'Enable selected', 'staging-superpowers' ), 'dashicons-controls-play' );
	echo '</div><br class="clear" /></div>';
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

/**
 * Release and version cells, or placeholders the release check fills in.
 */
function sspw_version_and_release( $key, $installed, $cells ) {
	if ( ! empty( $cells[ $key ] ) ) {
		echo '<td class="sspw-version">' . wp_kses_post( $cells[ $key ]['version'] ) . '</td>';
		echo '<td>' . wp_kses_post( $cells[ $key ]['release'] ) . '</td>';
		return;
	}

	echo '<td class="sspw-version">' . esc_html( $installed ) . '</td>';
	echo '<td class="sspw-released" data-key="' . esc_attr( $key ) . '">' . esc_html__( 'Checking...', 'staging-superpowers' ) . '</td>';
}

function sspw_output_plugin_tools( $action, $cells ) {
	$all      = get_plugins();
	$active   = (array) get_option( 'active_plugins', array() );
	$kept     = sspw_always_kept_plugins();
	$disabled = array_intersect( sspw_disabled_plugins(), array_keys( $all ) );

	echo '<h3>' . esc_html__( 'Plugins', 'staging-superpowers' ) . '</h3>';

	if ( $disabled ) {
		echo '<div class="notice notice-warning inline"><p><strong>' . esc_html(
			sprintf(
				/* translators: %d: number of plugins */
				_n( '%d plugin is disabled for troubleshooting:', '%d plugins are disabled for troubleshooting:', count( $disabled ), 'staging-superpowers' ),
				count( $disabled )
			)
		) . '</strong> ' . esc_html( sspw_names( $disabled ) ) . '</p><p>';
		sspw_action_button( $action, 'sspw_do', 'restore_plugins', __( 'Enable them all again', 'staging-superpowers' ), '', 'button button-primary' );
		echo '</p></div>';
	}

	sspw_bulk_buttons( $action, 'top' );

	echo '<table class="wp-list-table widefat fixed striped sspw-table">';
	sspw_table_head(
		array(
			__( 'Plugin', 'staging-superpowers' )         => '',
			__( 'Status', 'staging-superpowers' )         => '15%',
			__( 'On this site', 'staging-superpowers' )   => '11%',
			__( 'Latest release', 'staging-superpowers' ) => '20%',
			__( 'Actions', 'staging-superpowers' )        => '11%',
		),
		true
	);
	echo '<tbody>';

	// This plugin goes last: it is always on and cannot be selected.
	$order = array_merge( array_diff( sspw_sorted_keys( wp_list_pluck( $all, 'Name' ), $active ), $kept ), array_intersect( $kept, array_keys( $all ) ) );

	foreach ( $order as $file ) {
		$data      = $all[ $file ];
		$is_active = in_array( $file, $active, true );
		$is_kept   = in_array( $file, $kept, true );
		$id        = 'sspw-plugin-' . md5( $file );

		if ( $is_kept ) {
			$status = __( 'Always on', 'staging-superpowers' );
		} elseif ( $is_active ) {
			$status = __( 'Active', 'staging-superpowers' );
		} elseif ( in_array( $file, $disabled, true ) ) {
			$status = __( 'Inactive, disabled here', 'staging-superpowers' );
		} else {
			$status = __( 'Inactive', 'staging-superpowers' );
		}

		echo '<tr' . ( $is_active ? ' class="active"' : '' ) . '>';
		if ( $is_kept ) {
			echo '<th scope="row" class="check-column"></th>';
			echo '<td><strong>' . esc_html( $data['Name'] ) . '</strong></td>';
		} else {
			printf(
				'<th scope="row" class="check-column"><label class="screen-reader-text" for="%1$s">%2$s</label><input id="%1$s" type="checkbox" name="sspw_plugins[]" value="%3$s" /></th>',
				esc_attr( $id ),
				/* translators: %s: plugin name */
				esc_html( sprintf( __( 'Select %s', 'staging-superpowers' ), $data['Name'] ) ),
				esc_attr( $file )
			);
			printf( '<td><label for="%1$s"><strong>%2$s</strong></label></td>', esc_attr( $id ), esc_html( $data['Name'] ) );
		}
		echo '<td>' . ( $is_active ? '<strong>' . esc_html( $status ) . '</strong>' : esc_html( $status ) ) . '</td>';
		sspw_version_and_release( 'p:' . $file, $data['Version'], $cells );
		echo '<td>';
		if ( ! $is_kept ) {
			if ( $is_active ) {
				sspw_action_button( $action, 'sspw_disable_one', $file, __( 'Disable', 'staging-superpowers' ), 'dashicons-controls-pause', 'button-link' );
			} else {
				sspw_action_button( $action, 'sspw_enable_one', $file, __( 'Enable', 'staging-superpowers' ), 'dashicons-controls-play', 'button-link' );
			}
		}
		echo '</td></tr>';
	}

	echo '</tbody></table>';

	sspw_bulk_buttons( $action, 'bottom' );
}

function sspw_output_theme_tools( $action, $cells ) {
	$current  = get_stylesheet();
	$previous = sspw_previous_theme() ? wp_get_theme( sspw_previous_theme() ) : false;

	echo '<h3>' . esc_html__( 'Themes', 'staging-superpowers' ) . '</h3>';
	echo '<p>' . esc_html__( 'Menus, widgets and customizer settings are kept for each theme, so switching back restores them.', 'staging-superpowers' ) . '</p>';

	if ( $previous && $previous->exists() && $previous->get_stylesheet() !== $current ) {
		echo '<p>';
		/* translators: %s: theme name */
		sspw_action_button( $action, 'sspw_do', 'theme_restore', sprintf( __( 'Back to %s', 'staging-superpowers' ), $previous->get( 'Name' ) ), '', 'button button-primary' );
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
			__( 'Status', 'staging-superpowers' )         => '15%',
			__( 'On this site', 'staging-superpowers' )   => '11%',
			__( 'Latest release', 'staging-superpowers' ) => '20%',
			__( 'Actions', 'staging-superpowers' )        => '13%',
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
		sspw_version_and_release( 't:' . $stylesheet, $theme->get( 'Version' ), $cells );
		echo '<td>';
		if ( ! $is_active ) {
			sspw_action_button( $action, 'sspw_theme', $stylesheet, __( 'Use this theme', 'staging-superpowers' ) );
		}
		echo '</td></tr>';
	}

	echo '</tbody></table>';
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

function sspw_troubleshoot_done( $message ) {
	set_transient( 'sspw_notice_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );
	wp_safe_redirect( sspw_troubleshoot_url() );
	exit;
}

/**
 * "Also switched off: X, because it needs Y." for the result notice.
 *
 * @param array  $also file => names of what it needs, or name of what needs it.
 * @param string $kind 'off' or 'on'.
 */
function sspw_also_note( $also, $kind ) {
	$notes = array();

	foreach ( $also as $file => $reason ) {
		if ( 'off' === $kind ) {
			/* translators: 1: plugin name, 2: list of plugin names it needs */
			$notes[] = sprintf( __( '%1$s, because it needs %2$s', 'staging-superpowers' ), sspw_plugin_label( $file ), implode( ', ', (array) $reason ) );
		} else {
			/* translators: 1: plugin name, 2: plugin name that needs it */
			$notes[] = sprintf( __( '%1$s, because %2$s needs it', 'staging-superpowers' ), sspw_plugin_label( $file ), $reason );
		}
	}

	if ( ! $notes ) {
		return '';
	}

	if ( 'off' === $kind ) {
		/* translators: %s: list of plugins and why */
		return ' ' . sprintf( __( 'Also disabled: %s.', 'staging-superpowers' ), implode( '; ', $notes ) );
	}

	/* translators: %s: list of plugins and why */
	return ' ' . sprintf( __( 'Also enabled: %s.', 'staging-superpowers' ), implode( '; ', $notes ) );
}

/**
 * Switch plugins off (with what needs them), check the site, write one entry.
 */
function sspw_switch_off( $selected ) {
	$plan   = sspw_plan_switch_off( $selected );
	$before = (array) get_option( 'active_plugins', array() );
	$off    = array_values( array_diff( array_intersect( $plan['off'], $before ), sspw_always_kept_plugins() ) );

	if ( ! $off ) {
		sspw_troubleshoot_done( __( 'Select at least one active plugin to disable.', 'staging-superpowers' ) );
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
	sspw_troubleshoot_log( sprintf( _n( 'Troubleshooting: disabled %1$d plugin: %2$s', 'Troubleshooting: disabled %1$d plugins: %2$s', count( $off ), 'staging-superpowers' ), count( $off ), sspw_names( $off ) ) );

	// Several rounds add up, so "enable them all again" restores everything.
	update_option( 'sspw_disabled_plugins', array_values( array_unique( array_merge( sspw_disabled_plugins(), $off ) ) ), false );

	/* translators: %d: number of plugins */
	sspw_troubleshoot_done( sprintf( _n( '%d plugin disabled.', '%d plugins disabled.', count( $off ), 'staging-superpowers' ), count( $off ) ) . sspw_also_note( $plan['also'], 'off' ) . sspw_unchecked_note( $check ) );
}

/**
 * Switch plugins on (with what they need), check the site, write one entry.
 */
function sspw_switch_on( $selected ) {
	$plan   = sspw_plan_switch_on( $selected );
	$before = (array) get_option( 'active_plugins', array() );

	if ( ! $plan['on'] ) {
		sspw_troubleshoot_done( __( 'Select at least one inactive plugin to enable.', 'staging-superpowers' ) );
	}

	// Plugins disabled by troubleshooting come back silently; plugins that were never on
	// get a normal activation, so they can create their tables and defaults.
	$GLOBALS['sspw_quiet_log'] = true;
	$failed                    = sspw_activate_plugins( $plan['on'], sspw_disabled_plugins() );

	$check = sspw_site_check();
	if ( 'broken' === $check['status'] ) {
		update_option( 'active_plugins', $before );
		sspw_rolled_back( $check );
	}
	$GLOBALS['sspw_quiet_log'] = false;

	$on = array_values( array_diff( $plan['on'], $failed ) );

	if ( $on ) {
		/* translators: 1: number of plugins, 2: list of plugin names */
		sspw_troubleshoot_log( sprintf( _n( 'Troubleshooting: enabled %1$d plugin: %2$s', 'Troubleshooting: enabled %1$d plugins: %2$s', count( $on ), 'staging-superpowers' ), count( $on ), sspw_names( $on ) ) );
	}

	// Whatever is on again is no longer "disabled here"; anything that failed stays listed.
	$still_off = array_values( array_diff( sspw_disabled_plugins(), $on ) );
	if ( $still_off ) {
		update_option( 'sspw_disabled_plugins', $still_off, false );
	} else {
		delete_option( 'sspw_disabled_plugins' );
	}

	/* translators: %d: number of plugins */
	$message = sprintf( _n( '%d plugin enabled.', '%d plugins enabled.', count( $on ), 'staging-superpowers' ), count( $on ) ) . sspw_also_note( array_intersect_key( $plan['also'], array_flip( $on ) ), 'on' );

	if ( $failed ) {
		/* translators: %s: list of plugin names */
		$message .= ' ' . sprintf( __( 'Could not enable: %s', 'staging-superpowers' ), sspw_names( $failed ) );
	}

	sspw_troubleshoot_done( $message . sspw_unchecked_note( $check ) );
}

function sspw_handle_troubleshoot() {
	check_admin_referer( 'sspw_troubleshoot', 'sspw_nonce' );

	if ( ! sspw_is_armed() ) {
		wp_die( esc_html__( 'Troubleshooting is only available while Staging Superpowers is turned on for this site.', 'staging-superpowers' ) );
	}

	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	$installed = array_keys( get_plugins() );
	$do        = isset( $_POST['sspw_do'] ) ? sanitize_key( wp_unslash( $_POST['sspw_do'] ) ) : '';
	$selected  = isset( $_POST['sspw_plugins'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['sspw_plugins'] ) ) : array();
	$theme     = isset( $_POST['sspw_theme'] ) ? sanitize_text_field( wp_unslash( $_POST['sspw_theme'] ) ) : '';

	// A row's own Disable or Enable acts on that plugin only.
	foreach ( array( 'disable', 'enable' ) as $one ) {
		if ( isset( $_POST[ 'sspw_' . $one . '_one' ] ) ) {
			$do       = $one;
			$selected = array( sanitize_text_field( wp_unslash( $_POST[ 'sspw_' . $one . '_one' ] ) ) );
		}
	}

	if ( '' !== $theme ) {
		$do = 'theme_use';
	}

	$selected = array_values( array_intersect( $selected, $installed ) );

	if ( in_array( $do, array( 'disable', 'enable', 'restore_plugins' ), true ) && ! current_user_can( 'activate_plugins' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'staging-superpowers' ) );
	}

	if ( 0 === strpos( $do, 'theme_' ) && ! current_user_can( 'switch_themes' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'staging-superpowers' ) );
	}

	switch ( $do ) {
		case 'disable':
			sspw_switch_off( $selected );
			break;

		case 'enable':
			sspw_switch_on( $selected );
			break;

		case 'restore_plugins':
			$back = array_values( array_diff( array_intersect( sspw_disabled_plugins(), $installed ), (array) get_option( 'active_plugins', array() ) ) );

			if ( ! $back ) {
				delete_option( 'sspw_disabled_plugins' );
				sspw_troubleshoot_done( __( 'Everything is already enabled again.', 'staging-superpowers' ) );
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
