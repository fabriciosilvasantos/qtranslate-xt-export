<?php

// Define common WordPress constants that PHPStan might miss
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', './' );
}
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}
if ( ! defined( 'WP_LANG_DIR' ) ) {
	define( 'WP_LANG_DIR', './wp-content/languages' );
}
if ( ! defined( 'ADMIN_COOKIE_PATH' ) ) {
	define( 'ADMIN_COOKIE_PATH', '/wp-admin' );
}
if ( ! defined( 'COOKIEPATH' ) ) {
	define( 'COOKIEPATH', '/' );
}
if ( ! defined( 'SITECOOKIEPATH' ) ) {
	define( 'SITECOOKIEPATH', '/' );
}
if ( ! defined( 'PLUGINS_COOKIE_PATH' ) ) {
	define( 'PLUGINS_COOKIE_PATH', '/wp-content/plugins' );
}
if ( ! defined( 'COOKIE_DOMAIN' ) ) {
	define( 'COOKIE_DOMAIN', '' );
}
