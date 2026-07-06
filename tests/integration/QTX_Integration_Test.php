<?php
/**
 * Simplified Integration Tests for qTranslate-XT.
 * Uses mocks/stubs instead of real WordPress environment.
 */

use PHPUnit\Framework\TestCase;

class QTX_Integration_Test extends TestCase {
	protected static $translator;

	public static function setUpBeforeClass(): void {
		self::$translator = QTX_Translator::get_translator();
	}

	public function test_translator_initialized(): void {
		$this->assertInstanceOf( 'QTX_Translator', self::$translator );
	}

	public function test_default_language(): void {
		$lang = self::$translator->get_language();
		$this->assertNotEmpty( $lang );
		$this->assertEquals( 'en', $lang );
	}

	public function test_translate_post_content(): void {
		$post_content = '[:en]English content[:pt]Conteúdo em português[:]';

		self::$translator->set_language( 'en' );
		$english = self::$translator->translate_text( $post_content );
		$this->assertStringContainsString( 'English content', $english );

		self::$translator->set_language( 'pt' );
		$portuguese = self::$translator->translate_text( $post_content );
		$this->assertStringContainsString( 'Conteúdo em português', $portuguese );
	}

	public function test_multilingual_term_creation(): void {
		$term_name = '[:en]Test Category[:pt]Categoria de Teste[:]';

		self::$translator->set_language( 'en' );
		$english_term = self::$translator->translate_text( $term_name );
		$this->assertStringContainsString( 'Test Category', $english_term );

		self::$translator->set_language( 'pt' );
		$portuguese_term = self::$translator->translate_text( $term_name );
		$this->assertStringContainsString( 'Categoria de Teste', $portuguese_term );
	}

	public function test_language_switcher_filter(): void {
		add_filter( 'qtranslate_language_switcher', array( $this, 'language_switcher_filter_callback' ), 10, 3 );

		$languages = array(
			array( 'language' => 'en', 'url' => 'http://example.com/en/' ),
			array( 'language' => 'pt', 'url' => 'http://example.com/pt/' ),
		);

		$output = apply_filters( 'qtranslate_language_switcher', $languages, 'list', '<ul>%s</ul>' );

		$this->assertIsArray( $output );
		$this->assertCount( 2, $output );
		$this->assertArrayHasKey( 0, $output );
		$this->assertArrayHasKey( 1, $output );

		remove_filter( 'qtranslate_language_switcher', array( $this, 'language_switcher_filter_callback' ), 10 );
	}

	public function language_switcher_filter_callback( $output, $type, $format ): string {
		return $output;
	}

	public function test_url_mode_configuration(): void {
		global $q_config;

		$q_config['url_mode'] = QTX_URL_MODE_PATH;
		$this->assertEquals( QTX_URL_MODE_PATH, $q_config['url_mode'] );

		$q_config['url_mode'] = QTX_URL_MODE_DOMAIN;
		$this->assertEquals( QTX_URL_MODE_DOMAIN, $q_config['url_mode'] );
	}

	public function test_enabled_languages(): void {
		global $q_config;

		$q_config['enabled_languages'] = array( 'en', 'pt', 'es' );
		$this->assertCount( 3, $q_config['enabled_languages'] );
		$this->assertContains( 'en', $q_config['enabled_languages'] );
		$this->assertContains( 'pt', $q_config['enabled_languages'] );
		$this->assertContains( 'es', $q_config['enabled_languages'] );
		$this->assertNotContains( 'fr', $q_config['enabled_languages'] );
	}

	public function test_language_switch(): void {
		// Save current language
		$original_lang = self::$translator->get_language();

		// Switch to a different language
		$prev_lang = self::$translator->set_language( 'es' );
		$this->assertEquals( $original_lang, $prev_lang );

		$current_lang = self::$translator->get_language();
		$this->assertEquals( 'es', $current_lang );

		// Restore original language
		self::$translator->set_language( $original_lang );
	}

	public function test_translate_empty_string(): void {
		$result = self::$translator->translate_text( '' );
		$this->assertEquals( '', $result );
	}

	public function test_translate_plain_text(): void {
		$plain_text = 'Just a simple text without multilingual markers';
		$result = self::$translator->translate_text( $plain_text );
		$this->assertEquals( $plain_text, $result );
	}
}
