<?php
/**
 * Coverage for the nonce/capability checks and step routing in
 * qtx-polylang-migrator/includes/admin-actions.php, plus the migration-run
 * bookkeeping helpers in bootstrap.php.
 *
 * qtxpm_redirect_to_step() ends with a hard PHP `exit;`, which cannot be
 * intercepted from a stub (unlike a WordPress function call). These tests
 * therefore stop at the boundary just before a redirect would occur:
 * capability/nonce decisions and the "no matching request" early return of
 * qtxpm_handle_import_process(). The success path that reaches
 * qtxpm_redirect_to_step() is intentionally not exercised here to avoid
 * terminating the PHPUnit process.
 */

use PHPUnit\Framework\TestCase;

class QTX_Polylang_Migrator_Admin_Actions_Test extends TestCase {

	private static function loadMigrationEngine(): void {
		if ( ! function_exists( 'qtxpm_process_wxr_content' ) ) {
			require_once dirname( __DIR__, 2 ) . '/qtx-polylang-migrator/includes/migration-engine.php';
		}

		if ( ! function_exists( 'qtxpm_handle_import_process' ) ) {
			require_once dirname( __DIR__, 2 ) . '/qtx-polylang-migrator/includes/admin-actions.php';
		}
	}

	public static function setUpBeforeClass(): void {
		self::loadMigrationEngine();
	}

	protected function setUp(): void {
		global $q_config;

		$q_config['language'] = 'pt';
		$q_config['default_language'] = 'pt';
		$q_config['enabled_languages'] = array( 'pt', 'en' );

		if ( function_exists( 'qtx_polylang_stub_reset' ) ) {
			qtx_polylang_stub_reset();
		}

		if ( function_exists( 'qtx_wordpress_stub_reset' ) ) {
			qtx_wordpress_stub_reset();
		}

		$_GET = array();
		$_POST = array();
		$_FILES = array();

		self::loadMigrationEngine();
	}

	protected function tearDown(): void {
		$_GET = array();
		$_POST = array();
		$_FILES = array();
	}

	// ------------------------------------------------------------------
	// Nonce/capability decision helpers.
	// ------------------------------------------------------------------

	public function test_is_upload_request_requires_submit_field_and_valid_nonce(): void {
		$_POST = array(
			'submit'              => '1',
			'qtxpm_migrator_nonce' => 'valid-nonce',
		);
		$GLOBALS['qtx_wp_verify_nonce_result'] = 1;

		$this->assertTrue( qtxpm_is_upload_request() );
	}

	public function test_is_upload_request_is_false_when_nonce_field_is_missing(): void {
		$_POST = array( 'submit' => '1' );

		$this->assertFalse( qtxpm_is_upload_request() );
	}

	public function test_is_upload_request_is_false_when_nonce_verification_fails(): void {
		$_POST = array(
			'submit'              => '1',
			'qtxpm_migrator_nonce' => 'tampered-nonce',
		);
		$GLOBALS['qtx_wp_verify_nonce_result'] = false;

		$this->assertFalse( qtxpm_is_upload_request() );
	}

	public function test_is_upload_request_is_false_when_submit_field_is_missing(): void {
		$_POST = array( 'qtxpm_migrator_nonce' => 'valid-nonce' );
		$GLOBALS['qtx_wp_verify_nonce_result'] = 1;

		$this->assertFalse( qtxpm_is_upload_request() );
	}

	public function test_is_import_request_requires_wordpress_import_field(): void {
		$_POST = array(
			'wordpress_import'     => '1',
			'qtxpm_migrator_nonce' => 'valid-nonce',
		);
		$GLOBALS['qtx_wp_verify_nonce_result'] = 1;

		$this->assertTrue( qtxpm_is_import_request() );
		$this->assertFalse( qtxpm_is_upload_request() );
		$this->assertFalse( qtxpm_is_finalize_request() );
		$this->assertFalse( qtxpm_is_repair_request() );
	}

	public function test_is_finalize_request_requires_finalize_migration_field(): void {
		$_POST = array(
			'finalize_migration'   => '1',
			'qtxpm_migrator_nonce' => 'valid-nonce',
		);
		$GLOBALS['qtx_wp_verify_nonce_result'] = 1;

		$this->assertTrue( qtxpm_is_finalize_request() );
	}

	public function test_is_repair_request_requires_repair_translation_duplicates_field(): void {
		$_POST = array(
			'repair_translation_duplicates' => '1',
			'qtxpm_migrator_nonce'          => 'valid-nonce',
		);
		$GLOBALS['qtx_wp_verify_nonce_result'] = 1;

		$this->assertTrue( qtxpm_is_repair_request() );
	}

	// ------------------------------------------------------------------
	// qtxpm_handle_import_process() routing.
	// ------------------------------------------------------------------

	public function test_handle_import_process_is_a_noop_outside_the_migration_page(): void {
		$_GET = array( 'page' => 'some-other-admin-page' );
		$_POST = array(
			'submit'              => '1',
			'qtxpm_migrator_nonce' => 'valid-nonce',
		);
		$GLOBALS['qtx_wp_verify_nonce_result'] = 1;
		$GLOBALS['qtx_wp_current_user_can'] = false; // Would wp_die() if reached.

		// Must return quietly: no exception, no processing side effects.
		qtxpm_handle_import_process();

		$this->assertSame( array(), $GLOBALS['qtx_wp_redirects'] ?? array() );
	}

	public function test_handle_import_process_is_a_noop_when_no_action_field_matches(): void {
		$_GET = array( 'page' => qtxpm_get_migration_page_slug() );
		$_POST = array(); // No submit/wordpress_import/finalize_migration/repair field.

		qtxpm_handle_import_process();

		$this->assertSame( array(), $GLOBALS['qtx_wp_redirects'] ?? array() );
	}

	// ------------------------------------------------------------------
	// Capability enforcement inside qtxpm_process_uploaded_xml().
	// ------------------------------------------------------------------

	public function test_process_uploaded_xml_denies_access_without_manage_options_capability(): void {
		$GLOBALS['qtx_wp_current_user_can'] = false;

		$this->expectException( QTX_WP_Die_Stub_Exception::class );

		qtxpm_process_uploaded_xml();
	}

	public function test_process_uploaded_xml_rejects_missing_file_even_with_capability(): void {
		$GLOBALS['qtx_wp_current_user_can'] = true;
		$_FILES = array(); // No wxr_file uploaded.

		$this->expectException( QTX_WP_Die_Stub_Exception::class );

		qtxpm_process_uploaded_xml();
	}

	// ------------------------------------------------------------------
	// qtxpm_register_admin_menu() wiring.
	// ------------------------------------------------------------------

	public function test_register_admin_menu_registers_the_migration_page_under_manage_options(): void {
		qtxpm_register_admin_menu();

		$pages = $GLOBALS['qtx_wp_admin_pages'] ?? array();
		$this->assertNotEmpty( $pages );

		$registered = $pages[ count( $pages ) - 1 ];
		$this->assertSame( 'manage_options', $registered['capability'] );
		$this->assertSame( qtxpm_get_migration_page_slug(), $registered['menu_slug'] );
		$this->assertSame( 'qtxpm_render_migration_page', $registered['callback'] );
	}

	// ------------------------------------------------------------------
	// bootstrap.php migration-run bookkeeping helpers.
	// ------------------------------------------------------------------

	public function test_migration_page_slug_has_a_default_value(): void {
		$this->assertSame( 'qtx-polylang-migrator', qtxpm_get_migration_page_slug() );
	}

	public function test_migration_transient_key_is_prefixed(): void {
		$this->assertSame( 'qtxpm_staged_xml', qtxpm_get_migration_transient_key( 'staged_xml' ) );
	}

	public function test_generate_migration_run_id_returns_unique_values(): void {
		$first = qtxpm_generate_migration_run_id();
		$second = qtxpm_generate_migration_run_id();

		$this->assertNotSame( $first, $second );
		$this->assertStringStartsWith( 'qtxpm-', $first );
	}

	public function test_current_migration_run_defaults_to_empty_context(): void {
		$this->assertSame(
			array(
				'run'    => '',
				'source' => '',
			),
			qtxpm_get_current_migration_run()
		);
	}
}
