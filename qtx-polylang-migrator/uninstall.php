<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_transient( 'qtxpm_staged_xml' );
delete_transient( 'qtxpm_import_report' );
delete_transient( 'qtxpm_migration_results' );
delete_option( 'qtxpm_current_migration_run' );
