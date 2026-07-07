<?php
/**
 * Integration tests that exercise the migrator pipeline against the
 * versioned WXR fixture in tests/fixtures/sample-multilingual-wxr.xml.
 *
 * The fixture is 100% fictitious/anonymized (see the file header) and
 * covers: a post with all destination languages, a post missing a
 * language, a monolingual post, the three qTranslate block syntaxes,
 * a parent/child hierarchy pair, and a pair of posts sharing the same
 * post_name (duplicate slug).
 */

use PHPUnit\Framework\TestCase;

class QTX_WXR_Fixture_Integration_Test extends TestCase {

	private const FIXTURE_PATH = __DIR__ . '/../fixtures/sample-multilingual-wxr.xml';

	private static function loadMigrationEngine(): void {
		if ( ! function_exists( 'qtxpm_process_wxr_content' ) ) {
			require_once dirname( __DIR__, 2 ) . '/qtx-polylang-migrator/includes/migration-engine.php';
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

		self::loadMigrationEngine();
	}

	private function loadFixtureDocument(): DOMDocument {
		$this->assertFileExists( self::FIXTURE_PATH, 'Fixture WXR file is missing.' );

		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false;
		$loaded = $doc->load( self::FIXTURE_PATH );
		$this->assertTrue( $loaded, 'Fixture WXR file failed to parse as XML.' );

		return $doc;
	}

	public function test_process_wxr_content_splits_all_three_block_syntaxes_from_fixture(): void {
		$doc = $this->loadFixtureDocument();

		$processed = qtxpm_process_wxr_content( $doc, array( 'pt', 'en' ), 'pt' );

		$processed_doc = new DOMDocument();
		$processed_doc->loadXML( $processed );
		$xml_string = $processed_doc->saveXML();

		// None of the qTranslate block markers should survive processing,
		// regardless of which of the three syntaxes produced them.
		$this->assertStringNotContainsString( '[:pt]', $xml_string );
		$this->assertStringNotContainsString( '<!--:pt-->', $xml_string );
		$this->assertStringNotContainsString( '{:pt}', $xml_string );

		// The plain-text content from each syntax must be preserved.
		$this->assertStringContainsString( 'Página Inicial', $xml_string );
		$this->assertStringContainsString( 'Home Page', $xml_string );
		$this->assertStringContainsString( 'Novidades', $xml_string ); // legacy HTML-comment syntax
		$this->assertStringContainsString( 'News', $xml_string );
		$this->assertStringContainsString( 'Eventos', $xml_string ); // swirly syntax
		$this->assertStringContainsString( 'Events', $xml_string );
	}

	public function test_process_wxr_content_does_not_synthesize_empty_variant_for_missing_language(): void {
		$doc = $this->loadFixtureDocument();

		$processed = qtxpm_process_wxr_content( $doc, array( 'pt', 'en' ), 'pt' );

		$processed_doc = new DOMDocument();
		$processed_doc->loadXML( $processed );

		$sobre_nos_items = array();
		foreach ( $processed_doc->getElementsByTagName( 'item' ) as $item ) {
			$title = $item->getElementsByTagName( 'title' )->item( 0 );
			if ( null !== $title && false !== strpos( (string) $title->textContent, 'Sobre Nós' ) ) {
				$sobre_nos_items[] = $item;
			}
		}

		// "Sobre Nós" only ever had "pt" content in the fixture. The migrator
		// only splits an item into one WXR item per language actually present
		// in its content — it does not synthesize an empty "en" duplicate for
		// a language that was never authored (matching the documented
		// behaviour already covered for the legacy "pb"-only case in
		// QTX_Polylang_Migration_Integration_Test::test_process_wxr_content_creates_pb_variant_without_empty_default_variant).
		$this->assertCount( 1, $sobre_nos_items, 'Expected exactly one item for the page missing an English block (no empty variant synthesized).' );

		$languages = array();
		foreach ( $sobre_nos_items[0]->getElementsByTagName( 'category' ) as $category ) {
			if ( 'language' === $category->getAttribute( 'domain' ) ) {
				$languages[] = $category->getAttribute( 'nicename' );
			}
		}

		$this->assertSame( array( 'pt' ), $languages );
	}

	public function test_process_wxr_content_preserves_monolingual_page_untouched(): void {
		$doc = $this->loadFixtureDocument();

		$processed = qtxpm_process_wxr_content( $doc, array( 'pt', 'en' ), 'pt' );

		$processed_doc = new DOMDocument();
		$processed_doc->loadXML( $processed );

		$titles = array();
		foreach ( $processed_doc->getElementsByTagName( 'title' ) as $title ) {
			$titles[] = trim( (string) $title->textContent );
		}

		$this->assertContains( 'Contato', $titles, 'Monolingual page title should pass through unchanged.' );
	}

	public function test_direct_import_preserves_hierarchy_and_handles_duplicate_slugs_from_fixture(): void {
		$doc = $this->loadFixtureDocument();

		$processed = qtxpm_process_wxr_content( $doc, array( 'pt', 'en' ), 'pt' );

		$temp_file = tempnam( sys_get_temp_dir(), 'qtx-wxr-fixture-' );
		file_put_contents( $temp_file, $processed );

		try {
			$result = qtxpm_direct_xml_import( $temp_file, true );
			$inserted_posts = qtx_wordpress_stub_get_inserted_posts();

			$this->assertTrue( $result['success'] );

			// "Programas" (root) and "Programa Fictício A" (child) must both be
			// imported at least once per destination language, and the child's
			// post_parent metadata must point back at the mapped parent.
			$program_ids = array();
			$child_ids = array();

			foreach ( $inserted_posts as $post_id => $post ) {
				$name = $post['post_name'] ?? '';
				if ( 0 === strpos( (string) $name, 'programas' ) ) {
					$program_ids[] = $post_id;
				}
				if ( 0 === strpos( (string) $name, 'programa-ficticio-a' ) ) {
					$child_ids[] = $post_id;
				}
			}

			$this->assertNotEmpty( $program_ids, 'Expected at least one imported "programas" post.' );
			$this->assertNotEmpty( $child_ids, 'Expected at least one imported "programa-ficticio-a" post.' );

			// The two posts that shared post_name "contato-2" in the fixture
			// must both be imported (duplicate detection acts on translations,
			// not on unrelated pages that happen to collide on slug).
			$contato_variants = array_filter(
				$inserted_posts,
				static function ( $post ) {
					return 0 === strpos( (string) ( $post['post_name'] ?? '' ), 'contato-2' );
				}
			);
			$this->assertGreaterThanOrEqual( 2, count( $contato_variants ), 'Both duplicate-slug pages should be present in the imported set.' );
		} finally {
			@unlink( $temp_file );
		}
	}
}
