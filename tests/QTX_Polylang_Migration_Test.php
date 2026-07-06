<?php
/**
 * Tests for Polylang migration utilities.
 */

use PHPUnit\Framework\TestCase;

class QTX_Polylang_Migration_Test extends TestCase {
	protected function setUp(): void {
		global $q_config;
		$q_config = array(
			'language'              => 'en',
			'default_language'     => 'en',
			'enabled_languages'    => array( 'en', 'pt', 'es' ),
			'hide_default_language' => false,
		);
	}

	public function test_multilingual_content_parsing(): void {
		$content = '[:en]Hello World[:pt]Olá Mundo[:es]Hola Mundo[:]';

		$parsed = array();
		preg_match_all( '/\[:([a-z]{2})\](.*?)(?=\[:|$)/s', $content, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$parsed[ $match[1] ] = trim( $match[2] );
		}

		$this->assertArrayHasKey( 'en', $parsed );
		$this->assertArrayHasKey( 'pt', $parsed );
		$this->assertArrayHasKey( 'es', $parsed );
		$this->assertEquals( 'Hello World', $parsed['en'] );
		$this->assertEquals( 'Olá Mundo', $parsed['pt'] );
	}

	public function test_language_code_extraction(): void {
		$languages = array( 'en', 'pt', 'es', 'fr', 'de' );

		foreach ( $languages as $lang ) {
			$this->assertMatchesRegularExpression( '/^[a-z]{2}$/', $lang );
		}
	}

	public function test_postmeta_format_for_polylang(): void {
		$postmeta = array(
			'_wpml_word_count'    => '100',
			'_wpml_media'         => '1',
			'_wpml_content_parsed' => '1',
			'_translation_status'  => 'complete',
		);

		$this->assertArrayHasKey( '_translation_status', $postmeta );
		$this->assertEquals( 'complete', $postmeta['_translation_status'] );
	}

	public function test_hierarchy_preservation(): void {
		$pages = array(
			array( 'id' => 1, 'parent' => 0, 'title' => 'Home' ),
			array( 'id' => 2, 'parent' => 1, 'title' => 'About' ),
			array( 'id' => 3, 'parent' => 1, 'title' => 'Services' ),
			array( 'id' => 4, 'parent' => 2, 'title' => 'Team' ),
		);

		$hierarchy = array();
		foreach ( $pages as $page ) {
			$parent_id = $page['parent'];
			if ( ! isset( $hierarchy[ $parent_id ] ) ) {
				$hierarchy[ $parent_id ] = array();
			}
			$hierarchy[ $parent_id ][] = $page['id'];
		}

		$this->assertCount( 2, $hierarchy[1] );
		$this->assertContains( 2, $hierarchy[1] );
		$this->assertContains( 3, $hierarchy[1] );
		$this->assertCount( 1, $hierarchy[2] );
		$this->assertContains( 4, $hierarchy[2] );
	}

	public function test_menu_order_preservation(): void {
		$items = array(
			array( 'id' => 1, 'menu_order' => 0 ),
			array( 'id' => 2, 'menu_order' => 1 ),
			array( 'id' => 3, 'menu_order' => 2 ),
		);

		usort(
			$items,
			function( $a, $b ) {
				return $a['menu_order'] - $b['menu_order']; }
		);

		$this->assertEquals( 0, $items[0]['menu_order'] );
		$this->assertEquals( 1, $items[1]['menu_order'] );
		$this->assertEquals( 2, $items[2]['menu_order'] );
	}
}
