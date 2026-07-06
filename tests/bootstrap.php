<?php
/**
 * Bootstrap file for qTranslate-XT PHPUnit tests.
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/vendor/wp-coding-standards/wpcs/WordPress/' );

if ( ! defined( 'QTRANSLATE_DIR' ) ) {
	define( 'QTRANSLATE_DIR', dirname( __DIR__ ) );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		global $wp_filter;
		if ( ! isset( $wp_filter[ $tag ] ) ) {
			$wp_filter[ $tag ] = array();
		}
		$wp_filter[ $tag ][] = array(
			'function'      => $function_to_add,
			'accepted_args' => $accepted_args,
		);
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		return $value;
	}
}

function qtranxf_isEnabled( $lang ) {
	global $q_config;
	return isset( $q_config['enabled_languages'] ) && in_array( $lang, $q_config['enabled_languages'], true );
}

function qtranxf_use( $lang, $text, $show_available = false, $show_empty = false ) {
	global $q_config;
	$default_lang = isset( $q_config['default_language'] ) ? $q_config['default_language'] : 'en';

	if ( is_string( $text ) && preg_match_all( '/\[:([a-z]{2})\](.*?)(?=\[:|$)/s', $text, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $match ) {
			if ( $match[1] === $lang ) {
				$content = trim( $match[2] );
				if ( empty( $content ) && $show_empty ) {
					return '';
				}
				return $content;
			}
		}
		if ( $show_empty ) {
			return '';
		}
	}
	return $text;
}

function qtranxf_term_use( $lang, $term, $taxonomy = null ) {
	return $term;
}

function qtranxf_get_url_for_language( $url, $lang, $showLanguage = true ) {
	if ( $showLanguage ) {
		$url = trailingslashit( $url ) . $lang . '/';
	}
	return $url;
}

function trailingslashit( $string ) {
	return rtrim( $string, '/\\' ) . '/';
}

if ( ! defined( 'QTX_TRANSLATOR_SHOW_DEFAULT' ) ) {
	define( 'QTX_TRANSLATOR_SHOW_DEFAULT', 1 );
}
if ( ! defined( 'QTX_TRANSLATOR_SHOW_AVAILABLE' ) ) {
	define( 'QTX_TRANSLATOR_SHOW_AVAILABLE', 2 );
}
if ( ! defined( 'QTX_TRANSLATOR_SHOW_EMPTY' ) ) {
	define( 'QTX_TRANSLATOR_SHOW_EMPTY', 4 );
}

require_once dirname( __DIR__ ) . '/src/translator_interface.php';
require_once dirname( __DIR__ ) . '/src/class_translator.php';

if ( file_exists( dirname( __DIR__ ) . '/tests/acf_stubs.php' ) ) {
	require_once dirname( __DIR__ ) . '/tests/acf_stubs.php';
}

if ( file_exists( dirname( __DIR__ ) . '/tests/woocommerce_stubs.php' ) ) {
	require_once dirname( __DIR__ ) . '/tests/woocommerce_stubs.php';
}

if ( file_exists( dirname( __DIR__ ) . '/tests/polylang_stubs.php' ) ) {
	require_once dirname( __DIR__ ) . '/tests/polylang_stubs.php';
}
