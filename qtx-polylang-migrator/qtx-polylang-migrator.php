<?php
/**
 * Plugin Name: qTranslate to Polylang Migrator
 * Plugin URI: https://github.com/qtranslate/qtranslate-xt/
 * Description: Standalone migration tools to convert qTranslate-formatted WXR exports into a Polylang-ready WordPress import flow.
 * Version: 0.1.0
 * Requires at least: 6.9.4
 * Tested up to: 7.0
 * Requires PHP: 8.4
 * Requires Plugins: polylang
 * Author: qTranslate Community
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: qtx-polylang-migrator
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'QTXPM_PLUGIN_VERSION' ) ) {
	define( 'QTXPM_PLUGIN_VERSION', '0.1.0' );
	define( 'QTXPM_PLUGIN_FILE', __FILE__ );
	define( 'QTXPM_PLUGIN_DIR', __DIR__ );
	define( 'QTXPM_MIGRATION_PAGE_SLUG', 'qtx-polylang-migrator' );
	define( 'QTXPM_MIGRATION_PAGE_TITLE', 'qTranslate → Polylang Migrator' );
	define( 'QTXPM_MIGRATION_MENU_TITLE', 'qTranslate Migrator' );
	define( 'QTXPM_MIGRATION_TRANSIENT_PREFIX', 'qtxpm_' );
}

/**
 * Load translations for the standalone migrator.
 *
 * @return void
 */
function qtxpm_load_textdomain(): void {
	load_plugin_textdomain(
		'qtx-polylang-migrator',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}

/**
 * Clean standalone migrator artifacts on uninstall.
 *
 * @return void
 */
function qtxpm_uninstall(): void {
	delete_transient( QTXPM_MIGRATION_TRANSIENT_PREFIX . 'staged_xml' );
	delete_transient( QTXPM_MIGRATION_TRANSIENT_PREFIX . 'import_report' );
	delete_transient( QTXPM_MIGRATION_TRANSIENT_PREFIX . 'migration_results' );
	delete_option( 'qtxpm_current_migration_run' );
}

add_action( 'init', 'qtxpm_load_textdomain' );
register_uninstall_hook( __FILE__, 'qtxpm_uninstall' );

require_once __DIR__ . '/admin/bootstrap.php';
