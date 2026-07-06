<?php
/**
 * Tests for language configuration functions.
 */

use PHPUnit\Framework\TestCase;

class QTX_Language_Config_Test extends TestCase {
	protected function setUp(): void {
		global $q_config;
		$q_config = array(
			'language'              => 'en',
			'default_language'     => 'en',
			'enabled_languages'    => array( 'en', 'pt', 'es', 'fr' ),
			'hide_default_language' => false,
			'url_mode'             => QTX_URL_LANG_PATH,
			'flag_location'        => QTRANSLATE_DIR . '/flags/',
		);
	}

	public function test_language_enabled_check(): void {
		$this->assertTrue( qtranxf_isEnabled( 'en' ) );
		$this->assertTrue( qtranxf_isEnabled( 'pt' ) );
		$this->assertFalse( qtranxf_isEnabled( 'de' ) );
	}

	public function test_get_language_after_set(): void {
		global $q_config;

		$q_config['language'] = 'pt';
		$this->assertEquals( 'pt', $q_config['language'] );

		$q_config['language'] = 'es';
		$this->assertEquals( 'es', $q_config['language'] );
	}

	public function test_default_language_not_hidden(): void {
		global $q_config;

		$this->assertFalse( $q_config['hide_default_language'] );
		$this->assertEquals( 'en', $q_config['default_language'] );
	}

	public function test_enabled_languages_list(): void {
		global $q_config;

		$enabled = $q_config['enabled_languages'];
		$this->assertCount( 4, $enabled );
		$this->assertContains( 'en', $enabled );
		$this->assertContains( 'pt', $enabled );
		$this->assertContains( 'es', $enabled );
		$this->assertContains( 'fr', $enabled );
	}
}

if ( ! defined( 'QTX_URL_LANG_PATH' ) ) {
	define( 'QTX_URL_LANG_PATH', 1 );
}
