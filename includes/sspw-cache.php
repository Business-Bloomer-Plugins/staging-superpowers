<?php
/**
 * Page caching off on staging: cached pages are served before WordPress runs,
 * so they would skip the visitor redirect and show stale pages while testing.
 * Cache plugins keep their own settings; they are only told not to store pages.
 * Only loaded when the site is armed.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'update_option_sspw_no_cache', 'sspw_no_cache_switched', 10, 2 );
add_action( 'add_option_sspw_no_cache', 'sspw_no_cache_added', 10, 2 );

if ( 'yes' === sspw_get( 'sspw_no_cache' ) ) {
	// The shared "don't cache this page" signal honored by WP Rocket, W3 Total Cache,
	// WP Super Cache, LiteSpeed Cache, WP Fastest Cache, Cache Enabler, SiteGround,
	// Breeze, Hummingbird, WP-Optimize and others.
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- shared constant read by cache plugins.
	}

	add_action( 'init', 'sspw_no_cache_litespeed' );
	add_action( 'send_headers', 'sspw_no_cache_headers' );
}

function sspw_no_cache_litespeed() {
	do_action( 'litespeed_control_set_nocache', 'Staging Superpowers' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's own hook.
}

/**
 * Host and CDN caches (Varnish, Nginx, Cloudflare APO) do not read the constant,
 * but they do respect these headers.
 */
function sspw_no_cache_headers() {
	if ( ! is_admin() ) {
		nocache_headers();
	}
}

function sspw_no_cache_switched( $old_value, $value ) {
	if ( 'yes' === $value && 'yes' !== $old_value ) {
		sspw_purge_page_caches();
	}
}

function sspw_no_cache_added( $option, $value ) {
	sspw_no_cache_switched( '', $value );
}
