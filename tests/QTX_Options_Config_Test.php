<?php
/**
 * Tests for language option consistency (qtranxf_ensure_language_config_consistency).
 *
 * An inconsistent 'default_language'/'enabled_languages' pair (created via
 * WP-CLI, direct database edits or other plugins) used to crash the whole
 * site with a TypeError once the locale of a non-enabled language was
 * requested. These tests cover the runtime normalization that prevents it.
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/src/options.php';

class QTX_Options_Config_Test extends TestCase {
	protected function setUp(): void {
		global $q_config;
		$q_config = array();
	}

	public function test_consistent_state_is_left_untouched(): void {
		global $q_config;
		$q_config['default_language']  = 'en';
		$q_config['enabled_languages'] = array( 'en', 'pt' );

		qtranxf_ensure_language_config_consistency();

		$this->assertSame( 'en', $q_config['default_language'] );
		$this->assertSame( array( 'en', 'pt' ), $q_config['enabled_languages'] );
	}

	public function test_known_default_language_missing_from_enabled_is_enabled_back(): void {
		global $q_config;
		// Exact state that used to take the site down: default set via WP-CLI
		// while not present among the enabled languages.
		$q_config['default_language']  = 'pt';
		$q_config['enabled_languages'] = array( 'en', 'de' );

		qtranxf_ensure_language_config_consistency();

		$this->assertSame( 'pt', $q_config['default_language'] );
		$this->assertSame( array( 'en', 'de', 'pt' ), $q_config['enabled_languages'] );
	}

	public function test_unknown_default_language_falls_back_to_first_enabled(): void {
		global $q_config;
		$q_config['default_language']  = 'xx-invalid';
		$q_config['enabled_languages'] = array( 'pt', 'en' );

		qtranxf_ensure_language_config_consistency();

		$this->assertSame( 'pt', $q_config['default_language'] );
		$this->assertSame( array( 'pt', 'en' ), $q_config['enabled_languages'] );
	}

	public function test_corrupt_enabled_languages_with_known_default_enables_default(): void {
		global $q_config;
		$q_config['default_language']  = 'pt';
		$q_config['enabled_languages'] = 'corrompido';

		qtranxf_ensure_language_config_consistency();

		$this->assertSame( 'pt', $q_config['default_language'] );
		$this->assertSame( array( 'pt' ), $q_config['enabled_languages'] );
	}

	public function test_fully_missing_configuration_falls_back_to_english(): void {
		global $q_config;

		qtranxf_ensure_language_config_consistency();

		$this->assertSame( 'en', $q_config['default_language'] );
		$this->assertSame( array( 'en' ), $q_config['enabled_languages'] );
	}

	public function test_non_string_entries_are_filtered_from_enabled_languages(): void {
		global $q_config;
		$q_config['default_language']  = 'en';
		$q_config['enabled_languages'] = array( 'en', null, 42, 'pt' );

		qtranxf_ensure_language_config_consistency();

		$this->assertSame( 'en', $q_config['default_language'] );
		$this->assertSame( array( 'en', 'pt' ), $q_config['enabled_languages'] );
	}

	public function test_non_string_default_language_falls_back_to_first_enabled(): void {
		global $q_config;
		$q_config['default_language']  = array( 'pt' );
		$q_config['enabled_languages'] = array( 'en', 'pt' );

		qtranxf_ensure_language_config_consistency();

		$this->assertSame( 'en', $q_config['default_language'] );
	}
}
