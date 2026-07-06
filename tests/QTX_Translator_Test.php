<?php
/**
 * Tests for QTX_Translator class.
 */

use PHPUnit\Framework\TestCase;

class QTX_Translator_Test extends TestCase {
	protected function setUp(): void {
		global $q_config;
		$q_config = array(
			'language'              => 'en',
			'default_language'     => 'en',
			'enabled_languages'    => array( 'en', 'pt', 'es' ),
			'hide_default_language' => false,
		);
	}

	public function test_get_translator_returns_instance(): void {
		$translator = QTX_Translator::get_translator();
		$this->assertInstanceOf( QTX_Translator::class, $translator );
	}

	public function test_get_language_returns_current(): void {
		$translator = QTX_Translator::get_translator();
		$this->assertEquals( 'en', $translator->get_language() );
	}

	public function test_set_language_changes_language(): void {
		$translator = QTX_Translator::get_translator();
		$previous   = $translator->set_language( 'pt' );
		$this->assertEquals( 'en', $previous );
		$this->assertEquals( 'pt', $translator->get_language() );
	}

	public function test_set_language_rejects_invalid_language(): void {
		$translator = QTX_Translator::get_translator();
		$translator->set_language( 'pt' );
		$previous = $translator->set_language( 'fr' );
		$this->assertEquals( 'pt', $previous );
		$this->assertEquals( 'pt', $translator->get_language() );
	}

	public function test_translate_text_with_multilingual_string(): void {
		$translator = QTX_Translator::get_translator();
		$translator->set_language( 'en' );

		$text = '[:en]Hello[:pt]Olá[:es]Hola[:]';
		$result = $translator->translate_text( $text );

		$this->assertEquals( 'Hello', $result );
	}

	public function test_translate_text_with_different_language(): void {
		$translator = QTX_Translator::get_translator();
		$translator->set_language( 'en' );

		$text = '[:en]Hello[:pt]Olá[:es]Hola[:]';
		$result = $translator->translate_text( $text, 'pt' );

		$this->assertEquals( 'Olá', $result );
	}

	public function test_translate_text_with_empty_string(): void {
		$translator = QTX_Translator::get_translator();
		$translator->set_language( 'en' );

		$result = $translator->translate_text( '' );
		$this->assertEquals( '', $result );
	}

	public function test_translate_text_with_non_multilingual_string(): void {
		$translator = QTX_Translator::get_translator();
		$translator->set_language( 'en' );

		$result = $translator->translate_text( 'Plain text' );
		$this->assertEquals( 'Plain text', $result );
	}

	public function test_translate_term_returns_term(): void {
		$translator = QTX_Translator::get_translator();
		$translator->set_language( 'en' );

		$result = $translator->translate_term( 'Category' );
		$this->assertEquals( 'Category', $result );
	}

	public function test_translate_url_with_language(): void {
		$translator = QTX_Translator::get_translator();
		$translator->set_language( 'en' );

		$result = $translator->translate_url( 'http://example.com', 'pt' );
		$this->assertStringContainsString( 'pt', $result );
	}

	public function test_translate_text_with_flags_show_empty(): void {
		$translator = QTX_Translator::get_translator();
		$translator->set_language( 'fr' );

		$text = '[:en]Hello[:pt]Olá[:]';
		$result = $translator->translate_text( $text, 'fr', QTX_TRANSLATOR_SHOW_EMPTY );

		$this->assertEquals( '', $result );
	}
}
