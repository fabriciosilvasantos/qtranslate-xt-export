<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_admin() ) {
	return;
}

require_once dirname( __DIR__ ) . '/includes/migration-engine.php';
