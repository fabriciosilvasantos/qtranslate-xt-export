<?php
/**
 * Bootstrap for Simplified Integration Tests.
 * This bootstrap does NOT require WordPress or WP_Test_Suite.
 * It uses mocks/stubs to simulate WordPress environment.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'QTRANSLATE_DIR', dirname( __DIR__ ) );
define( 'WP_PLUGIN_DIR', dirname( __DIR__ ) );
define( 'WP_CONTENT_DIR', dirname( dirname( __DIR__ ) ) );
define( 'QTX_TRANSLATOR_SHOW_DEFAULT', 1 );
define( 'QTX_TRANSLATOR_SHOW_AVAILABLE', 2 );
define( 'QTX_TRANSLATOR_SHOW_EMPTY', 4 );
define( 'QTX_URL_MODE_QUERY', 1 );
define( 'QTX_URL_MODE_PATH', 2 );
define( 'QTX_URL_MODE_DOMAIN', 3 );
define( 'QTX_URL_MODE_CUSTOM', 4 );
define( 'QTX_LANG_CODE_FORMAT', '[a-z]{2,3}' );
define( 'OBJECT', 'OBJECT' );

// Load Composer autoloader
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load WordPress stubs (must be before plugin code)
require_once __DIR__ . '/wordpress_stubs.php';
require_once __DIR__ . '/polylang_stubs.php';

// Load qTranslate translator
require_once QTRANSLATE_DIR . '/src/translator_interface.php';
require_once QTRANSLATE_DIR . '/src/class_translator.php';

// Initialize global $q_config for tests
global $q_config;
$q_config = array(
	'language'              => 'en',
	'default_language'     => 'en',
	'enabled_languages'    => array( 'en', 'pt', 'es' ),
	'hide_default_language' => false,
	'url_mode'             => QTX_URL_MODE_PATH,
	'flag_location'        => QTRANSLATE_DIR . '/flags/',
);

// Helper functions for tests (stubs for qTranslate functions)
if ( ! function_exists( 'qtranxf_isEnabled' ) ) {
	function qtranxf_isEnabled( $lang ) {
		global $q_config;
		return isset( $q_config['enabled_languages'] ) && in_array( $lang, $q_config['enabled_languages'], true );
	}
}

if ( ! function_exists( 'qtranxf_use' ) ) {
	function qtranxf_use( $lang, $text, $show_available = false, $show_empty = false ) {
		global $q_config;
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
}

if ( ! function_exists( 'qtranxf_isMultilingual' ) ) {
	function qtranxf_isMultilingual( ?string $str ): bool {
		return ! is_null( $str ) && preg_match( '/\[:[a-z]{2,3}\]/i', $str ) === 1;
	}
}

if ( ! function_exists( 'qtranxf_split' ) ) {
	function qtranxf_split( string $text ): array {
		global $q_config;

		$result = array();
		foreach ( $q_config['enabled_languages'] as $language ) {
			$result[ $language ] = '';
		}

		if ( preg_match_all( '/\[:([a-z]{2,3})\](.*?)(?=\[:[a-z]{2,3}\]|\[:\]|$)/is', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$language = strtolower( $match[1] );
				$result[ $language ] = trim( $match[2] );
			}

			return $result;
		}

		foreach ( $result as $language => $value ) {
			$result[ $language ] = $text;
		}

		return $result;
	}
}

if ( ! function_exists( 'qtranxf_get_language' ) ) {
	function qtranxf_get_language() {
		global $q_config;
		return $q_config['language'] ?? 'en';
	}
}
