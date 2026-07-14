<?php
/**
 * Simplified Integration Tests for Polylang migration features.
 * Uses mocks/stubs instead of real WordPress environment.
 */

use PHPUnit\Framework\TestCase;

class QTX_Polylang_Migration_Integration_Test extends TestCase {
	protected static $translator;

	private static function loadMigrationEngine(): void {
		if ( ! function_exists( 'qtxpm_process_wxr_content' ) ) {
			require_once dirname( __DIR__, 2 ) . '/qtx-polylang-migrator/includes/migration-engine.php';
		}
	}

	public static function setUpBeforeClass(): void {
		self::$translator = QTX_Translator::get_translator();
		self::loadMigrationEngine();
	}

	protected function setUp(): void {
		global $q_config;

		$q_config['language'] = 'en';
		$q_config['default_language'] = 'en';
		$q_config['enabled_languages'] = array( 'en', 'pt', 'es' );

		if ( function_exists( 'qtx_polylang_stub_reset' ) ) {
			qtx_polylang_stub_reset();
		}

		if ( function_exists( 'qtx_wordpress_stub_reset' ) ) {
			qtx_wordpress_stub_reset();
		}

		self::loadMigrationEngine();
	}

	public function test_multilingual_content_parsing(): void {
		$content = '[:en]Hello World[:pt]Olá Mundo[:es]Hola Mundo[:]';

		self::$translator->set_language( 'en' );
		$english = self::$translator->translate_text( $content );
		$this->assertStringContainsString( 'Hello World', $english );

		self::$translator->set_language( 'pt' );
		$portuguese = self::$translator->translate_text( $content );
		$this->assertStringContainsString( 'Olá Mundo', $portuguese );

		self::$translator->set_language( 'es' );
		$spanish = self::$translator->translate_text( $content );
		$this->assertStringContainsString( 'Hola Mundo', $spanish );
	}

	public function test_post_multilingual_format(): void {
		$content = '[:en]English post[:pt]Post em português[:]';

		// Verify format is preserved
		$this->assertStringContainsString( '[:en]', $content );
		$this->assertStringContainsString( '[:pt]', $content );

		// Verify translation works
		$translated = self::$translator->translate_text( $content, 'en' );
		$this->assertEquals( 'English post', $translated );
	}

	public function test_category_multilingual(): void {
		$term_content = '[:en]Technology[:pt]Tecnologia[:]';

		self::$translator->set_language( 'en' );
		$english_term = self::$translator->translate_text( $term_content );
		$this->assertStringContainsString( 'Technology', $english_term );

		self::$translator->set_language( 'pt' );
		$portuguese_term = self::$translator->translate_text( $term_content );
		$this->assertStringContainsString( 'Tecnologia', $portuguese_term );
	}

	public function test_postmeta_multilingual_storage(): void {
		// Simulate storing multilingual content in post meta
		$meta_value = '[:en]Meta English[:pt]Meta Português[:]';

		self::$translator->set_language( 'en' );
		$english_meta = self::$translator->translate_text( $meta_value );
		$this->assertStringContainsString( 'Meta English', $english_meta );

		self::$translator->set_language( 'pt' );
		$portuguese_meta = self::$translator->translate_text( $meta_value );
		$this->assertStringContainsString( 'Meta Português', $portuguese_meta );
	}

	public function test_menu_item_multilingual(): void {
		$menu_item = '[:en]Home[:pt]Início[:]';

		self::$translator->set_language( 'en' );
		$english_menu = self::$translator->translate_text( $menu_item );
		$this->assertStringContainsString( 'Home', $english_menu );

		self::$translator->set_language( 'pt' );
		$portuguese_menu = self::$translator->translate_text( $menu_item );
		$this->assertStringContainsString( 'Início', $portuguese_menu );
	}

	public function test_url_mode_path(): void {
		global $q_config;

		$q_config['url_mode'] = QTX_URL_MODE_PATH;
		$this->assertEquals( QTX_URL_MODE_PATH, $q_config['url_mode'] );
	}

	public function test_url_mode_domain(): void {
		global $q_config;

		$q_config['url_mode'] = QTX_URL_MODE_DOMAIN;
		$this->assertEquals( QTX_URL_MODE_DOMAIN, $q_config['url_mode'] );
	}

	public function test_languages_configuration(): void {
		global $q_config;

		$this->assertCount( 3, $q_config['enabled_languages'] );
		$this->assertContains( 'en', $q_config['enabled_languages'] );
		$this->assertContains( 'pt', $q_config['enabled_languages'] );
		$this->assertContains( 'es', $q_config['enabled_languages'] );
		$this->assertNotContains( 'fr', $q_config['enabled_languages'] );
	}

	public function test_default_language_is_en(): void {
		global $q_config;

		$this->assertEquals( 'en', $q_config['default_language'] );
	}

	public function test_hierarchy_sorting(): void {
		// Test that items are sorted by hierarchy correctly
		// Items are processed depth-first: parent, then its children recursively
		$items = array(
			array( 'original_id' => 1, 'original_parent' => 0, 'menu_order' => 0 ),
			array( 'original_id' => 2, 'original_parent' => 1, 'menu_order' => 1 ),
			array( 'original_id' => 3, 'original_parent' => 1, 'menu_order' => 2 ),
			array( 'original_id' => 4, 'original_parent' => 2, 'menu_order' => 1 ),
			array( 'original_id' => 5, 'original_parent' => 0, 'menu_order' => 1 ),
		);

		// Include the function if not already loaded
		if ( ! function_exists( 'qtxpm_sort_items_by_hierarchy' ) ) {
			self::loadMigrationEngine();
		}

		$sorted = qtxpm_sort_items_by_hierarchy( $items );

		// Depth-first order:
		// 1. Root item 1 (menu_order=0)
		// 2. Child 2 of 1 (menu_order=1) - processed immediately after parent 1
		// 3. Grandchild 4 of 2 (menu_order=1) - processed immediately after parent 2
		// 4. Child 3 of 1 (menu_order=2) - next sibling of 2
		// 5. Root item 5 (menu_order=1) - next root after finishing 1's subtree
		$this->assertEquals( 1, $sorted[0]['original_id'], 'First root item should be ID 1' );
		$this->assertEquals( 2, $sorted[1]['original_id'], 'First child of 1 should be ID 2' );
		$this->assertEquals( 4, $sorted[2]['original_id'], 'Grandchild (child of 2) should be ID 4' );
		$this->assertEquals( 3, $sorted[3]['original_id'], 'Second child of 1 should be ID 3' );
		$this->assertEquals( 5, $sorted[4]['original_id'], 'Second root item should be ID 5' );
	}

	public function test_hierarchy_preserves_parent_child_relationship(): void {
		// Test that parent-child relationships are maintained
		$items = array(
			array( 'original_id' => 10, 'original_parent' => 0, 'menu_order' => 0 ),
			array( 'original_id' => 20, 'original_parent' => 10, 'menu_order' => 0 ),
			array( 'original_id' => 30, 'original_parent' => 20, 'menu_order' => 0 ),
		);

		if ( ! function_exists( 'qtxpm_sort_items_by_hierarchy' ) ) {
			self::loadMigrationEngine();
		}

		$sorted = qtxpm_sort_items_by_hierarchy( $items );

		// Build parent map from sorted items
		$parent_map = array();
		foreach ( $sorted as $item ) {
			$parent_map[ $item['original_id'] ] = $item['original_parent'];
		}

		// Verify relationships
		$this->assertEquals( 0, $parent_map[10], 'Item 10 should have no parent' );
		$this->assertEquals( 10, $parent_map[20], 'Item 20 should be child of 10' );
		$this->assertEquals( 20, $parent_map[30], 'Item 30 should be child of 20' );
	}

	public function test_menu_order_preservation(): void {
		// Test that menu_order is preserved during sorting
		$items = array(
			array( 'original_id' => 1, 'original_parent' => 0, 'menu_order' => 2 ),
			array( 'original_id' => 2, 'original_parent' => 0, 'menu_order' => 1 ),
			array( 'original_id' => 3, 'original_parent' => 0, 'menu_order' => 3 ),
		);

		if ( ! function_exists( 'qtxpm_sort_items_by_hierarchy' ) ) {
			self::loadMigrationEngine();
		}

		$sorted = qtxpm_sort_items_by_hierarchy( $items );

		// Items should be sorted by menu_order
		$this->assertEquals( 2, $sorted[0]['original_id'], 'First item should be ID 2 (menu_order 1)' );
		$this->assertEquals( 1, $sorted[1]['original_id'], 'Second item should be ID 1 (menu_order 2)' );
		$this->assertEquals( 3, $sorted[2]['original_id'], 'Third item should be ID 3 (menu_order 3)' );
	}

	public function test_deep_hierarchy(): void {
		// Test with multiple levels of hierarchy
		$items = array(
			array( 'original_id' => 1, 'original_parent' => 0, 'menu_order' => 0 ),
			array( 'original_id' => 2, 'original_parent' => 1, 'menu_order' => 0 ),
			array( 'original_id' => 3, 'original_parent' => 2, 'menu_order' => 0 ),
			array( 'original_id' => 4, 'original_parent' => 3, 'menu_order' => 0 ),
			array( 'original_id' => 5, 'original_parent' => 4, 'menu_order' => 0 ),
		);

		if ( ! function_exists( 'qtxpm_sort_items_by_hierarchy' ) ) {
			self::loadMigrationEngine();
		}

		$sorted = qtxpm_sort_items_by_hierarchy( $items );

		// Verify order: root first, then children in depth-first order
		$this->assertEquals( 1, $sorted[0]['original_id'] );
		$this->assertEquals( 2, $sorted[1]['original_id'] );
		$this->assertEquals( 3, $sorted[2]['original_id'] );
		$this->assertEquals( 4, $sorted[3]['original_id'] );
		$this->assertEquals( 5, $sorted[4]['original_id'] );
	}

	public function test_parent_resolution_prefers_same_language(): void {
		if ( ! function_exists( 'qtxpm_resolve_parent_post_id' ) ) {
			self::loadMigrationEngine();
		}

		$post_map = array(
			100 => array(
				'en' => 1001,
				'pt' => 1002,
				'*'  => 1002,
			),
		);

		$this->assertSame( 1002, qtxpm_resolve_parent_post_id( $post_map, 100, 'pt' ) );
		$this->assertSame( 1001, qtxpm_resolve_parent_post_id( $post_map, 100, 'en' ) );
	}

	public function test_parent_resolution_falls_back_to_any_language(): void {
		if ( ! function_exists( 'qtxpm_resolve_parent_post_id' ) ) {
			self::loadMigrationEngine();
		}

		$post_map = array(
			200 => array(
				'en' => 2001,
				'*'  => 2001,
			),
		);

		$this->assertSame( 2001, qtxpm_resolve_parent_post_id( $post_map, 200, 'pt' ) );
		$this->assertSame( 0, qtxpm_resolve_parent_post_id( $post_map, 999, 'pt' ) );
	}

	public function test_assign_post_language_uses_polylang_api(): void {
		if ( ! function_exists( 'qtxpm_assign_post_language' ) ) {
			self::loadMigrationEngine();
		}

		$this->assertTrue( qtxpm_assign_post_language( 55, 'pt' ) );
		$this->assertSame( 'pt', $GLOBALS['qtx_polylang_post_languages'][55] ?? null );
	}

	public function test_assign_post_language_rejects_unrecognized_language(): void {
		if ( ! function_exists( 'qtxpm_assign_post_language' ) ) {
			self::loadMigrationEngine();
		}

		$this->assertFalse( qtxpm_assign_post_language( 55, 'xx' ) );
		$this->assertArrayNotHasKey( 55, $GLOBALS['qtx_polylang_post_languages'] ?? array() );
	}

	public function test_assign_post_language_normalizes_pb_to_pt(): void {
		if ( ! function_exists( 'qtxpm_assign_post_language' ) ) {
			self::loadMigrationEngine();
		}

		$this->assertTrue( qtxpm_assign_post_language( 55, 'pb' ) );
		$this->assertSame( 'pt', $GLOBALS['qtx_polylang_post_languages'][55] ?? null );
	}

	public function test_language_value_uses_pb_as_alias_for_pt(): void {
		if ( ! function_exists( 'qtxpm_get_language_value' ) ) {
			self::loadMigrationEngine();
		}

		$values = array(
			'en' => 'Dissertations and Theses',
			'pb' => 'Dissertacoes e Teses',
		);

		$this->assertTrue( qtxpm_has_language_value( $values, 'pt' ) );
		$this->assertSame( 'Dissertacoes e Teses', qtxpm_get_language_value( $values, 'pt', 'en', '' ) );
		$this->assertSame( 'Dissertations and Theses', qtxpm_get_language_value( $values, 'en', 'en', '' ) );
	}

	public function test_language_value_falls_back_to_first_available_variant(): void {
		if ( ! function_exists( 'qtxpm_get_language_value' ) ) {
			self::loadMigrationEngine();
		}

		$values = array(
			'pb' => 'Sobre o Programa',
		);

		$this->assertSame( 'Sobre o Programa', qtxpm_get_language_value( $values, 'en', 'en', '[:pb]Sobre o Programa[:]' ) );
	}

	public function test_detect_wxr_languages_includes_pb_from_source_xml(): void {
		if ( ! function_exists( 'qtxpm_detect_wxr_languages' ) ) {
			self::loadMigrationEngine();
		}

		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title><![CDATA[[:pb]Sobre o Programa[:en]About the Program[:] ]]></title>
		<content:encoded><![CDATA[[:pb]Conteudo[:] ]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
	</item>
</channel>
</rss>
XML;

		$doc = new DOMDocument();
		$doc->loadXML( $xml );

		$languages = qtxpm_detect_wxr_languages( $doc, array( 'en', 'de' ), 'en' );

		$this->assertContains( 'en', $languages );
		$this->assertContains( 'pb', $languages );
		$this->assertNotContains( 'de', $languages );
	}

	public function test_detect_wxr_languages_preserves_de_as_a_detected_language(): void {
		if ( ! function_exists( 'qtxpm_detect_wxr_languages' ) ) {
			self::loadMigrationEngine();
		}

		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title><![CDATA[[:en]About the Program[:de]About the Program[:pb]Sobre o Programa[:] ]]></title>
		<content:encoded><![CDATA[[:pb]Conteudo[:] ]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
	</item>
</channel>
</rss>
XML;

		$doc = new DOMDocument();
		$doc->loadXML( $xml );

		$languages = qtxpm_detect_wxr_languages( $doc, array( 'pt', 'en' ), 'pt' );

		$this->assertSame( array( 'pt', 'en', 'de' ), $languages );
	}

	public function test_process_wxr_content_creates_pb_variant_without_empty_default_variant(): void {
		if ( ! function_exists( 'qtxpm_process_wxr_content' ) ) {
			self::loadMigrationEngine();
		}

		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title><![CDATA[[:pb]Sobre o Programa[:]]]></title>
		<guid isPermaLink="false">page-1</guid>
		<content:encoded><![CDATA[[:pb]Conteudo[:]]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<wp:post_id>1</wp:post_id>
		<wp:post_parent>0</wp:post_parent>
		<wp:post_type><![CDATA[page]]></wp:post_type>
		<wp:menu_order>0</wp:menu_order>
	</item>
</channel>
</rss>
XML;

		$doc = new DOMDocument();
		$doc->loadXML( $xml );

		$processed = qtxpm_process_wxr_content( $doc, array( 'en', 'pb' ), 'en' );

		$processed_doc = new DOMDocument();
		$processed_doc->loadXML( $processed );

		$items = $processed_doc->getElementsByTagName( 'item' );
		$this->assertSame( 1, $items->length );
		$this->assertSame( 'Sobre o Programa', $processed_doc->getElementsByTagName( 'title' )->item( 0 )->textContent );
		$this->assertStringNotContainsString( '[:pb]', $processed_doc->saveXML() );
		$this->assertStringContainsString( 'nicename="pb"', $processed_doc->saveXML() );
	}

	public function test_process_wxr_content_skips_de_variant_when_destination_uses_pt_and_en(): void {
		if ( ! function_exists( 'qtxpm_process_wxr_content' ) ) {
			self::loadMigrationEngine();
		}

		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title><![CDATA[[:en]About the Program[:de]About the Program[:pb]Sobre o Programa[:] ]]></title>
		<guid isPermaLink="false">page-legacy-1</guid>
		<content:encoded><![CDATA[[:en]English body[:de]English body[:pb]Conteudo em portugues[:] ]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<wp:post_id>1</wp:post_id>
		<wp:post_parent>0</wp:post_parent>
		<wp:post_type><![CDATA[page]]></wp:post_type>
		<wp:menu_order>0</wp:menu_order>
	</item>
</channel>
</rss>
XML;

		$doc = new DOMDocument();
		$doc->loadXML( $xml );

		$processed = qtxpm_process_wxr_content( $doc, array( 'pt', 'en' ), 'pt' );

		$processed_doc = new DOMDocument();
		$processed_doc->loadXML( $processed );

		$items = $processed_doc->getElementsByTagName( 'item' );
		$this->assertSame( 2, $items->length );
		$this->assertStringNotContainsString( 'nicename="de"', $processed_doc->saveXML() );
		$this->assertStringContainsString( 'nicename="pt"', $processed_doc->saveXML() );
		$this->assertStringContainsString( 'nicename="en"', $processed_doc->saveXML() );
	}

	public function test_direct_import_assigns_parent_during_insert_for_duplicate_child_slugs(): void {
		if ( ! function_exists( 'qtxpm_direct_xml_import' ) ) {
			self::loadMigrationEngine();
		}

		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title>Parent A</title>
		<guid isPermaLink="false">parent-a</guid>
		<content:encoded><![CDATA[]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="pt">pt</category>
		<wp:post_id>1</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:00:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[parent-a]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[page]]></wp:post_type>
	</item>
	<item>
		<title>Child A</title>
		<guid isPermaLink="false">child-a</guid>
		<content:encoded><![CDATA[]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="pt">pt</category>
		<wp:post_id>2</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:01:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:01:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:01:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:01:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[2020-2]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[page]]></wp:post_type>
		<wp:postmeta>
			<wp:meta_key>_pll_migration_parent_id</wp:meta_key>
			<wp:meta_value>1</wp:meta_value>
		</wp:postmeta>
		<wp:postmeta>
			<wp:meta_key>_pll_migration_original_id</wp:meta_key>
			<wp:meta_value>2</wp:meta_value>
		</wp:postmeta>
		<wp:postmeta>
			<wp:meta_key>_pll_migration_menu_order</wp:meta_key>
			<wp:meta_value>0</wp:meta_value>
		</wp:postmeta>
	</item>
	<item>
		<title>Parent B</title>
		<guid isPermaLink="false">parent-b</guid>
		<content:encoded><![CDATA[]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="pt">pt</category>
		<wp:post_id>3</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:02:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:02:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:02:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:02:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[parent-b]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[page]]></wp:post_type>
	</item>
	<item>
		<title>Child B</title>
		<guid isPermaLink="false">child-b</guid>
		<content:encoded><![CDATA[]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="pt">pt</category>
		<wp:post_id>4</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:03:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:03:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:03:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:03:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[2020-2]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[page]]></wp:post_type>
		<wp:postmeta>
			<wp:meta_key>_pll_migration_parent_id</wp:meta_key>
			<wp:meta_value>3</wp:meta_value>
		</wp:postmeta>
		<wp:postmeta>
			<wp:meta_key>_pll_migration_original_id</wp:meta_key>
			<wp:meta_value>4</wp:meta_value>
		</wp:postmeta>
		<wp:postmeta>
			<wp:meta_key>_pll_migration_menu_order</wp:meta_key>
			<wp:meta_value>0</wp:meta_value>
		</wp:postmeta>
	</item>
</channel>
</rss>
XML;

		$temp_file = tempnam( sys_get_temp_dir(), 'qtx-wxr-' );
		file_put_contents( $temp_file, $xml );

		try {
			$result = qtxpm_direct_xml_import( $temp_file, true );
			$inserted_posts = qtx_wordpress_stub_get_inserted_posts();

			$this->assertTrue( $result['success'] );
			$this->assertCount( 4, $inserted_posts );
			$this->assertSame( 0, (int) $inserted_posts[1]['post_parent'] );
			$this->assertSame( 1, (int) $inserted_posts[2]['post_parent'] );
			$this->assertSame( 0, (int) $inserted_posts[3]['post_parent'] );
			$this->assertSame( 3, (int) $inserted_posts[4]['post_parent'] );
		} finally {
			@unlink( $temp_file );
		}
	}

	public function test_direct_import_keeps_same_batch_language_variants_with_shared_guid(): void {
		if ( ! function_exists( 'qtxpm_direct_xml_import' ) ) {
			self::loadMigrationEngine();
		}

		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title>Dissertations and Theses</title>
		<guid isPermaLink="false">shared-guid-18</guid>
		<content:encoded><![CDATA[]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="en">en</category>
		<wp:post_id>18</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:00:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[teses]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[page]]></wp:post_type>
		<wp:postmeta>
			<wp:meta_key>_pll_migration_group</wp:meta_key>
			<wp:meta_value>10</wp:meta_value>
		</wp:postmeta>
		<wp:postmeta>
			<wp:meta_key>_pll_migration_lang</wp:meta_key>
			<wp:meta_value>en</wp:meta_value>
		</wp:postmeta>
		<wp:postmeta>
			<wp:meta_key>_pll_migration_original_id</wp:meta_key>
			<wp:meta_value>18</wp:meta_value>
		</wp:postmeta>
		<wp:postmeta>
			<wp:meta_key>_pll_migration_parent_id</wp:meta_key>
			<wp:meta_value>0</wp:meta_value>
		</wp:postmeta>
	</item>
	<item>
		<title>Dissertações e Teses</title>
		<guid isPermaLink="false">shared-guid-18</guid>
		<content:encoded><![CDATA[]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="pb">pb</category>
		<wp:post_id>18</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:00:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[teses]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[page]]></wp:post_type>
		<wp:postmeta>
			<wp:meta_key>_pll_migration_group</wp:meta_key>
			<wp:meta_value>10</wp:meta_value>
		</wp:postmeta>
		<wp:postmeta>
			<wp:meta_key>_pll_migration_lang</wp:meta_key>
			<wp:meta_value>pb</wp:meta_value>
		</wp:postmeta>
		<wp:postmeta>
			<wp:meta_key>_pll_migration_original_id</wp:meta_key>
			<wp:meta_value>18</wp:meta_value>
		</wp:postmeta>
		<wp:postmeta>
			<wp:meta_key>_pll_migration_parent_id</wp:meta_key>
			<wp:meta_value>0</wp:meta_value>
		</wp:postmeta>
	</item>
</channel>
</rss>
XML;

		$temp_file = tempnam( sys_get_temp_dir(), 'qtx-wxr-' );
		file_put_contents( $temp_file, $xml );

		try {
			$result = qtxpm_direct_xml_import( $temp_file, false );
			$inserted_posts = qtx_wordpress_stub_get_inserted_posts();

			$this->assertTrue( $result['success'] );
			$this->assertSame( 2, $result['imported'] );
			$this->assertSame( 0, $result['skipped'] );
			$this->assertCount( 2, $inserted_posts );
		} finally {
			@unlink( $temp_file );
		}
	}

	public function test_connect_translations_normalizes_polylang_languages(): void {
		global $wpdb;

		if ( ! function_exists( 'qtxpm_connect_translations_process' ) ) {
			self::loadMigrationEngine();
		}

		$original_wpdb = $wpdb;
		$wpdb = new class() extends wpdb {
			public function get_results( $query, $output = OBJECT ) {
				if ( false !== strpos( $query, "WHERE meta_key = '_pll_migration_lang'" ) ) {
					return array(
						(object) array(
							'post_id' => 101,
							'language_code' => 'en',
						),
						(object) array(
							'post_id' => 102,
							'language_code' => 'pb',
						),
						(object) array(
							'post_id' => 103,
							'language_code' => 'de',
						),
					);
				}

				if ( false !== strpos( $query, "WHERE pm.meta_key = '_pll_migration_group'" ) ) {
					return array(
						(object) array(
							'ID' => 101,
							'post_type' => 'page',
							'group_id' => '77',
							'lang' => 'en',
						),
						(object) array(
							'ID' => 102,
							'post_type' => 'page',
							'group_id' => '77',
							'lang' => 'pb',
						),
						(object) array(
							'ID' => 103,
							'post_type' => 'page',
							'group_id' => '77',
							'lang' => 'de',
						),
					);
				}

				return array();
			}
		};

		try {
			$result = qtxpm_connect_translations_process();

			$this->assertTrue( $result['success'] );
			$this->assertSame( 'pt', $GLOBALS['qtx_polylang_post_languages'][102] ?? null );
			$this->assertSame( 'en', $GLOBALS['qtx_polylang_post_languages'][101] ?? null );
			$this->assertSame( 'de', $GLOBALS['qtx_polylang_post_languages'][103] ?? null );
			$this->assertContains( 'de', $GLOBALS['qtx_polylang_added_languages'] ?? array() );
			$this->assertCount( 1, $GLOBALS['qtx_polylang_saved_translations'] ?? array() );
			$this->assertSame(
				array(
					'en' => 101,
					'pt' => 102,
					'de' => 103,
				),
				$GLOBALS['qtx_polylang_saved_translations'][0] ?? array()
			);
			$this->assertContains( 'Idiomas restaurados: 3 posts atribuidos, 0 ignorados.', $result['details'] );
			$this->assertContains( 'Grupo 77 conectado com os idiomas: en, pt, de.', $result['details'] );
		} finally {
			$wpdb = $original_wpdb;
		}
	}

	public function test_resolve_polylang_language_code_provisions_catalog_languages(): void {
		if ( ! function_exists( 'qtxpm_resolve_polylang_language_code' ) ) {
			self::loadMigrationEngine();
		}

		$this->assertSame( 'fr', qtxpm_resolve_polylang_language_code( 'fr' ) );
		$this->assertContains( 'fr', $GLOBALS['qtx_polylang_added_languages'] ?? array() );
		$this->assertSame( 'pt', qtxpm_resolve_polylang_language_code( 'pb' ) );
	}

	public function test_resolve_polylang_language_code_uses_runtime_registry_when_public_list_is_stale(): void {
		if ( ! function_exists( 'qtxpm_resolve_polylang_language_code' ) ) {
			self::loadMigrationEngine();
		}

		$GLOBALS['qtx_polylang_languages'] = array(
			'pt' => (object) array( 'slug' => 'pt', 'locale' => 'pt_BR' ),
		);
		$GLOBALS['qtx_polylang_visible_language_slugs'] = array( 'pt' );
		$GLOBALS['qtxpm_runtime_polylang_languages'] = array();

		$this->assertSame( 'en', qtxpm_resolve_polylang_language_code( 'en' ) );
		$this->assertContains( 'en', $GLOBALS['qtx_polylang_added_languages'] ?? array() );
		$this->assertContains( 'en', $GLOBALS['qtxpm_runtime_polylang_languages'] ?? array() );
	}

	public function test_resolve_polylang_language_code_supports_dynamic_add_language_model(): void {
		if ( ! function_exists( 'qtxpm_resolve_polylang_language_code' ) ) {
			self::loadMigrationEngine();
		}

		$GLOBALS['qtx_polylang_languages'] = array();
		$GLOBALS['qtx_polylang_visible_language_slugs'] = array();
		$GLOBALS['qtxpm_runtime_polylang_languages'] = array();
		$GLOBALS['qtx_polylang_instance'] = new class() {
			public $model;

			public function __construct() {
				$this->model = new class() {
					public function __call( string $name, array $arguments ) {
						if ( 'add_language' !== $name ) {
							return null;
						}

						$args = $arguments[0] ?? array();
						$slug = strtolower( trim( (string) ( $args['slug'] ?? '' ) ) );
						if ( '' === $slug ) {
							$slug = strtolower( substr( (string) ( $args['locale'] ?? 'xx' ), 0, 2 ) );
						}

						$language = (object) array(
							'slug'   => $slug,
							'locale' => (string) ( $args['locale'] ?? $slug . '_' . strtoupper( $slug ) ),
							'name'   => (string) ( $args['name'] ?? strtoupper( $slug ) ),
							'flag'   => (string) ( $args['flag'] ?? $slug ),
							'rtl'    => ! empty( $args['rtl'] ),
						);

						$GLOBALS['qtx_polylang_languages'][ $slug ] = $language;
						$GLOBALS['qtx_polylang_added_languages'][] = $slug;

						return $language;
					}

					public function get_languages_list() {
						return array_values( $GLOBALS['qtx_polylang_languages'] ?? array() );
					}
				};
			}
		};

		$this->assertSame( 'fr', qtxpm_resolve_polylang_language_code( 'fr' ) );
		$this->assertContains( 'fr', $GLOBALS['qtx_polylang_added_languages'] ?? array() );
		$this->assertContains( 'fr', $GLOBALS['qtxpm_runtime_polylang_languages'] ?? array() );
	}

	public function test_deduplicate_translation_posts_prefers_post_already_in_translation_group(): void {
		global $wpdb;

		if ( ! function_exists( 'qtxpm_deduplicate_translation_posts_process' ) ) {
			self::loadMigrationEngine();
		}

		$GLOBALS['qtx_wp_inserted_posts'][101] = array(
			'ID'          => 101,
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_name'   => 'presentation',
		);
		$GLOBALS['qtx_wp_inserted_posts'][102] = array(
			'ID'          => 102,
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_name'   => 'presentation-2',
		);

		$original_wpdb = $wpdb;
		$wpdb = new class() extends wpdb {
			public function get_results( $query, $output = OBJECT ) {
				if ( false !== strpos( $query, "_pll_migration_duplicate_of" ) ) {
					return array(
						(object) array(
							'ID'                  => 101,
							'post_type'           => 'page',
							'post_status'         => 'publish',
							'post_name'           => 'presentation',
							'original_id'         => '2',
							'lang'                => 'en',
							'migration_guid'      => 'guid-2',
							'migration_parent_id' => '0',
							'duplicate_of'        => '',
							'translation_term_id' => 500,
						),
						(object) array(
							'ID'                  => 102,
							'post_type'           => 'page',
							'post_status'         => 'publish',
							'post_name'           => 'presentation-2',
							'original_id'         => '2',
							'lang'                => 'en',
							'migration_guid'      => 'guid-2',
							'migration_parent_id' => '0',
							'duplicate_of'        => '',
							'translation_term_id' => null,
						),
					);
				}

				return array();
			}
		};

		try {
			$result = qtxpm_deduplicate_translation_posts_process();

			$this->assertTrue( $result['success'] );
			$this->assertSame( array( 101 ), $result['kept_ids'] );
			$this->assertSame( array( 102 ), $result['downgraded_ids'] );
			$this->assertSame( 'draft', $GLOBALS['qtx_wp_updated_posts'][102]['post_status'] ?? null );
			$this->assertSame( 101, $GLOBALS['qtx_wp_post_meta'][102]['_pll_migration_duplicate_of'] ?? null );
		} finally {
			$wpdb = $original_wpdb;
		}
	}

	public function test_deduplicate_translation_posts_normalizes_pb_and_pt_to_same_language(): void {
		global $wpdb;

		if ( ! function_exists( 'qtxpm_deduplicate_translation_posts_process' ) ) {
			self::loadMigrationEngine();
		}

		$GLOBALS['qtx_wp_inserted_posts'][201] = array(
			'ID'          => 201,
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_name'   => 'apresentacao-2',
		);
		$GLOBALS['qtx_wp_inserted_posts'][202] = array(
			'ID'          => 202,
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_name'   => 'apresentacao',
		);

		$original_wpdb = $wpdb;
		$wpdb = new class() extends wpdb {
			public function get_results( $query, $output = OBJECT ) {
				if ( false !== strpos( $query, "_pll_migration_duplicate_of" ) ) {
					return array(
						(object) array(
							'ID'                  => 201,
							'post_type'           => 'page',
							'post_status'         => 'publish',
							'post_name'           => 'apresentacao-2',
							'original_id'         => '2',
							'lang'                => 'pb',
							'migration_guid'      => 'guid-2',
							'migration_parent_id' => '0',
							'duplicate_of'        => '',
							'translation_term_id' => null,
						),
						(object) array(
							'ID'                  => 202,
							'post_type'           => 'page',
							'post_status'         => 'publish',
							'post_name'           => 'apresentacao',
							'original_id'         => '2',
							'lang'                => 'pt',
							'migration_guid'      => 'guid-2',
							'migration_parent_id' => '0',
							'duplicate_of'        => '',
							'translation_term_id' => 900,
						),
					);
				}

				return array();
			}
		};

		try {
			$result = qtxpm_deduplicate_translation_posts_process();

			$this->assertTrue( $result['success'] );
			$this->assertSame( array( 202 ), $result['kept_ids'] );
			$this->assertSame( array( 201 ), $result['downgraded_ids'] );
			$this->assertSame( 202, $GLOBALS['qtx_wp_post_meta'][201]['_pll_migration_duplicate_of'] ?? null );
		} finally {
			$wpdb = $original_wpdb;
		}
	}

	public function test_connect_translations_uses_canonical_posts_after_deduplication(): void {
		global $wpdb;

		if ( ! function_exists( 'qtxpm_connect_translations_process' ) ) {
			self::loadMigrationEngine();
		}

		$GLOBALS['qtx_wp_inserted_posts'][301] = array(
			'ID'          => 301,
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_name'   => 'presentation',
		);
		$GLOBALS['qtx_wp_inserted_posts'][302] = array(
			'ID'          => 302,
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_name'   => 'presentation-2',
		);
		$GLOBALS['qtx_wp_inserted_posts'][303] = array(
			'ID'          => 303,
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_name'   => 'apresentacao',
		);
		$GLOBALS['qtx_wp_inserted_posts'][304] = array(
			'ID'          => 304,
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'post_name'   => 'presentation-image',
		);

		$original_wpdb = $wpdb;
		$wpdb = new class() extends wpdb {
			public function get_results( $query, $output = OBJECT ) {
				if ( false !== strpos( $query, "_pll_migration_duplicate_of" ) ) {
					return array(
						(object) array(
							'ID'                  => 301,
							'post_type'           => 'page',
							'post_status'         => 'publish',
							'post_name'           => 'presentation',
							'original_id'         => '2',
							'lang'                => 'en',
							'migration_guid'      => 'guid-2',
							'migration_parent_id' => '0',
							'duplicate_of'        => '',
							'translation_term_id' => 1100,
						),
						(object) array(
							'ID'                  => 302,
							'post_type'           => 'page',
							'post_status'         => 'publish',
							'post_name'           => 'presentation-2',
							'original_id'         => '2',
							'lang'                => 'en',
							'migration_guid'      => 'guid-2',
							'migration_parent_id' => '0',
							'duplicate_of'        => '',
							'translation_term_id' => null,
						),
						(object) array(
							'ID'                  => 303,
							'post_type'           => 'page',
							'post_status'         => 'publish',
							'post_name'           => 'apresentacao',
							'original_id'         => '2',
							'lang'                => 'pb',
							'migration_guid'      => 'guid-2',
							'migration_parent_id' => '0',
							'duplicate_of'        => '',
							'translation_term_id' => 1100,
						),
						(object) array(
							'ID'                  => 304,
							'post_type'           => 'attachment',
							'post_status'         => 'inherit',
							'post_name'           => 'presentation-image',
							'original_id'         => '2',
							'lang'                => 'en',
							'migration_guid'      => 'guid-2',
							'migration_parent_id' => '0',
							'duplicate_of'        => '',
							'translation_term_id' => null,
						),
					);
				}

				if ( false !== strpos( $query, "WHERE meta_key = '_pll_migration_lang'" ) ) {
					return array(
						(object) array(
							'post_id' => 301,
							'language_code' => 'en',
						),
						(object) array(
							'post_id' => 302,
							'language_code' => 'en',
						),
						(object) array(
							'post_id' => 303,
							'language_code' => 'pb',
						),
						(object) array(
							'post_id' => 304,
							'language_code' => 'en',
						),
					);
				}

				if ( false !== strpos( $query, "WHERE pm.meta_key = '_pll_migration_group'" ) ) {
					return array(
						(object) array(
							'ID' => 301,
							'post_type' => 'page',
							'group_id' => '88',
							'lang' => 'en',
						),
						(object) array(
							'ID' => 302,
							'post_type' => 'page',
							'group_id' => '88',
							'lang' => 'en',
						),
						(object) array(
							'ID' => 303,
							'post_type' => 'page',
							'group_id' => '88',
							'lang' => 'pb',
						),
						(object) array(
							'ID' => 304,
							'post_type' => 'attachment',
							'group_id' => '88',
							'lang' => 'en',
						),
					);
				}

				return array();
			}
		};

		try {
			$result = qtxpm_connect_translations_process();

			$this->assertTrue( $result['success'] );
			$this->assertSame( 'draft', $GLOBALS['qtx_wp_updated_posts'][302]['post_status'] ?? null );
			$this->assertSame( 301, $GLOBALS['qtx_wp_post_meta'][302]['_pll_migration_duplicate_of'] ?? null );
			$this->assertSame(
				array(
					'en' => 301,
					'pt' => 303,
				),
				$GLOBALS['qtx_polylang_saved_translations'][0] ?? array()
			);
			$this->assertArrayNotHasKey( 302, $GLOBALS['qtx_polylang_post_languages'] ?? array() );
			$this->assertArrayNotHasKey( 304, $GLOBALS['qtx_polylang_post_languages'] ?? array() );
			$this->assertContains( 'Deduplicacao concluida: 2 grupos analisados, 1 grupos com duplicatas, 1 posts movidos para rascunho.', $result['details'] );
		} finally {
			$wpdb = $original_wpdb;
		}
	}

	public function test_migration_run_context_round_trip(): void {
		$this->assertSame( array( 'run' => '', 'source' => '' ), qtxpm_get_current_migration_run() );

		qtxpm_set_current_migration_run( 'run-abc', 'https://site-a.example' );

		$this->assertSame(
			array(
				'run'    => 'run-abc',
				'source' => 'https://site-a.example',
			),
			qtxpm_get_current_migration_run()
		);
	}

	public function test_wxr_source_key_extraction_normalizes_channel_link(): void {
		$xml = simplexml_load_string(
			'<rss><channel><title>Site A</title><link>https://Site-A.example/Blog/</link></channel></rss>'
		);

		$this->assertSame( 'https://site-a.example/blog', qtxpm_get_wxr_source_key( $xml ) );
	}

	public function test_wxr_source_key_falls_back_to_channel_title(): void {
		$xml = simplexml_load_string(
			'<rss><channel><title>Meu Site</title><link></link></channel></rss>'
		);

		$this->assertSame( 'meu site', qtxpm_get_wxr_source_key( $xml ) );
	}

	public function test_direct_import_records_run_context_and_tags_posts(): void {
		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<title>Site A</title>
	<link>https://site-a.example</link>
	<item>
		<title>Pagina</title>
		<guid isPermaLink="false">pagina</guid>
		<content:encoded><![CDATA[Conteudo]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="pt">pt</category>
		<wp:post_id>1</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:00:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[pagina]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[page]]></wp:post_type>
	</item>
</channel>
</rss>
XML;

		$temp_file = tempnam( sys_get_temp_dir(), 'qtx-wxr-' );
		file_put_contents( $temp_file, $xml );

		try {
			$result = qtxpm_direct_xml_import( $temp_file, true );

			$this->assertTrue( $result['success'] );

			$run_context = qtxpm_get_current_migration_run();
			$this->assertNotSame( '', $run_context['run'] );
			$this->assertSame( 'https://site-a.example', $run_context['source'] );

			$post_meta = $GLOBALS['qtx_wp_post_meta'][1] ?? array();
			$this->assertSame( $run_context['run'], $post_meta['_pll_migration_run'] ?? null );
			$this->assertSame( 'https://site-a.example', $post_meta['_pll_migration_source'] ?? null );
		} finally {
			@unlink( $temp_file );
		}
	}

	public function test_hierarchy_query_is_scoped_to_current_run(): void {
		global $wpdb;

		qtxpm_set_current_migration_run( 'run-scope-1', 'https://site-a.example' );

		$original_wpdb = $wpdb;
		$wpdb = new class() extends wpdb {
			public $captured_queries = array();

			public function get_results( $query, $output = OBJECT ) {
				$this->captured_queries[] = $query;
				return array();
			}
		};

		try {
			qtxpm_rebuild_hierarchy_process();

			$this->assertNotEmpty( $wpdb->captured_queries );
			$this->assertStringContainsString( '_pll_migration_run', $wpdb->captured_queries[0] );
			$this->assertStringContainsString( 'run-scope-1', $wpdb->captured_queries[0] );
		} finally {
			$wpdb = $original_wpdb;
		}
	}

	public function test_hierarchy_query_is_unscoped_without_run_context(): void {
		global $wpdb;

		$original_wpdb = $wpdb;
		$wpdb = new class() extends wpdb {
			public $captured_queries = array();

			public function get_results( $query, $output = OBJECT ) {
				$this->captured_queries[] = $query;
				return array();
			}
		};

		try {
			qtxpm_rebuild_hierarchy_process();

			$this->assertNotEmpty( $wpdb->captured_queries );
			$this->assertStringNotContainsString( '_pll_migration_run', $wpdb->captured_queries[0] );
		} finally {
			$wpdb = $original_wpdb;
		}
	}

	public function test_dedup_query_is_scoped_to_current_source(): void {
		global $wpdb;

		qtxpm_set_current_migration_run( 'run-scope-2', 'https://site-a.example' );

		$original_wpdb = $wpdb;
		$wpdb = new class() extends wpdb {
			public $captured_queries = array();

			public function get_results( $query, $output = OBJECT ) {
				$this->captured_queries[] = $query;
				return array();
			}
		};

		try {
			qtxpm_deduplicate_translation_posts_process();

			$this->assertNotEmpty( $wpdb->captured_queries );
			$this->assertStringContainsString( '_pll_migration_source', $wpdb->captured_queries[0] );
			$this->assertStringContainsString( 'https://site-a.example', $wpdb->captured_queries[0] );
		} finally {
			$wpdb = $original_wpdb;
		}
	}

	public function test_connect_translations_scopes_group_and_language_queries_to_run(): void {
		global $wpdb;

		qtxpm_set_current_migration_run( 'run-scope-3', 'https://site-a.example' );

		$original_wpdb = $wpdb;
		$wpdb = new class() extends wpdb {
			public $captured_queries = array();

			public function get_results( $query, $output = OBJECT ) {
				$this->captured_queries[] = $query;
				return array();
			}
		};

		try {
			$result = qtxpm_connect_translations_process();

			$this->assertTrue( $result['success'] );

			$group_queries = array_values(
				array_filter(
					$wpdb->captured_queries,
					static function ( $query ) {
						return false !== strpos( $query, '_pll_migration_group' );
					}
				)
			);
			$this->assertNotEmpty( $group_queries );
			$this->assertStringContainsString( '_pll_migration_run', $group_queries[0] );
			$this->assertStringContainsString( 'run-scope-3', $group_queries[0] );

			$language_queries = array_values(
				array_filter(
					$wpdb->captured_queries,
					static function ( $query ) {
						return false !== strpos( $query, "pm.meta_key = '_pll_migration_lang'" )
							&& false === strpos( $query, '_pll_migration_group' );
					}
				)
			);
			$this->assertNotEmpty( $language_queries );
			$this->assertStringContainsString( 'run-scope-3', $language_queries[0] );
		} finally {
			$wpdb = $original_wpdb;
		}
	}

	// ------------------------------------------------------------------
	// B1 — dc:creator -> post_author preservation.
	// ------------------------------------------------------------------

	private function buildAuthorWxr( string $creator ): string {
		return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/"
	xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
	<item>
		<title>Pagina com Autor</title>
		<dc:creator><![CDATA[{$creator}]]></dc:creator>
		<guid isPermaLink="false">pagina-autor</guid>
		<content:encoded><![CDATA[Conteudo]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="pt">pt</category>
		<wp:post_id>1</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:00:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[pagina-autor]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[page]]></wp:post_type>
	</item>
</channel>
</rss>
XML;
	}

	public function test_direct_import_assigns_post_author_when_creator_matches_existing_user(): void {
		if ( ! function_exists( 'qtxpm_direct_xml_import' ) ) {
			self::loadMigrationEngine();
		}

		$GLOBALS['qtx_wp_users'] = array(
			(object) array(
				'ID'            => 42,
				'user_login'    => 'joana',
				'user_nicename' => 'joana',
				'user_email'    => 'joana@example.com',
			),
		);

		$temp_file = tempnam( sys_get_temp_dir(), 'qtx-wxr-' );
		file_put_contents( $temp_file, $this->buildAuthorWxr( 'joana' ) );

		try {
			$result = qtxpm_direct_xml_import( $temp_file, true );
			$inserted_posts = qtx_wordpress_stub_get_inserted_posts();

			$this->assertTrue( $result['success'] );
			$this->assertSame( 42, (int) ( $inserted_posts[1]['post_author'] ?? 0 ) );
			$this->assertArrayNotHasKey( '_pll_migration_original_author', $GLOBALS['qtx_wp_post_meta'][1] ?? array() );

			$matched_details = array_filter(
				$result['details'],
				static fn( string $detail ): bool => false !== strpos( $detail, 'joana' ) && false !== strpos( $detail, 'login' )
			);
			$this->assertNotEmpty( $matched_details, 'A detail entry recording the author match by "login" is expected.' );
			$this->assertEmpty( $result['warnings'], 'No warning is expected when the author resolves successfully.' );
		} finally {
			@unlink( $temp_file );
		}
	}

	public function test_direct_import_records_original_author_meta_when_creator_has_no_matching_user(): void {
		if ( ! function_exists( 'qtxpm_direct_xml_import' ) ) {
			self::loadMigrationEngine();
		}

		$GLOBALS['qtx_wp_users'] = array();

		$temp_file = tempnam( sys_get_temp_dir(), 'qtx-wxr-' );
		file_put_contents( $temp_file, $this->buildAuthorWxr( 'autor-inexistente' ) );

		try {
			$result = qtxpm_direct_xml_import( $temp_file, true );
			$inserted_posts = qtx_wordpress_stub_get_inserted_posts();

			$this->assertTrue( $result['success'] );
			$this->assertArrayNotHasKey( 'post_author', $inserted_posts[1] );
			$this->assertSame(
				'autor-inexistente',
				$GLOBALS['qtx_wp_post_meta'][1]['_pll_migration_original_author'] ?? null
			);

			$matched_warnings = array_filter(
				$result['warnings'],
				static fn( string $warning ): bool => false !== strpos( $warning, 'autor-inexistente' )
			);
			$this->assertNotEmpty( $matched_warnings, 'A warning about the unresolved author is expected.' );
		} finally {
			@unlink( $temp_file );
		}
	}

	public function test_direct_import_without_dc_creator_does_not_set_author_or_meta(): void {
		if ( ! function_exists( 'qtxpm_direct_xml_import' ) ) {
			self::loadMigrationEngine();
		}

		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title>Pagina sem autor</title>
		<guid isPermaLink="false">pagina-sem-autor</guid>
		<content:encoded><![CDATA[Conteudo]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="pt">pt</category>
		<wp:post_id>1</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:00:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[pagina-sem-autor]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[page]]></wp:post_type>
	</item>
</channel>
</rss>
XML;

		$temp_file = tempnam( sys_get_temp_dir(), 'qtx-wxr-' );
		file_put_contents( $temp_file, $xml );

		try {
			$result = qtxpm_direct_xml_import( $temp_file, true );
			$inserted_posts = qtx_wordpress_stub_get_inserted_posts();

			$this->assertTrue( $result['success'] );
			$this->assertArrayNotHasKey( 'post_author', $inserted_posts[1] );
			$this->assertArrayNotHasKey( '_pll_migration_original_author', $GLOBALS['qtx_wp_post_meta'][1] ?? array() );
		} finally {
			@unlink( $temp_file );
		}
	}

	// ------------------------------------------------------------------
	// B2 — per-language postmeta splitting + serialized-meta safety.
	// ------------------------------------------------------------------

	public function test_process_wxr_content_splits_multilingual_postmeta_per_language_clone(): void {
		if ( ! function_exists( 'qtxpm_process_wxr_content' ) ) {
			self::loadMigrationEngine();
		}

		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title><![CDATA[[:en]About[:pt]Sobre[:]]]></title>
		<guid isPermaLink="false">page-meta-1</guid>
		<content:encoded><![CDATA[[:en]English body[:pt]Corpo em portugues[:]]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<wp:post_id>1</wp:post_id>
		<wp:post_parent>0</wp:post_parent>
		<wp:post_type><![CDATA[page]]></wp:post_type>
		<wp:menu_order>0</wp:menu_order>
		<wp:postmeta>
			<wp:meta_key>subtitle</wp:meta_key>
			<wp:meta_value><![CDATA[[:en]English subtitle[:pt]Subtitulo em portugues[:]]]></wp:meta_value>
		</wp:postmeta>
	</item>
</channel>
</rss>
XML;

		$doc = new DOMDocument();
		$doc->loadXML( $xml );

		$processed = qtxpm_process_wxr_content( $doc, array( 'en', 'pt' ), 'en' );

		$processed_doc = new DOMDocument();
		$processed_doc->loadXML( $processed );

		$this->assertStringNotContainsString( '[:en]', $processed );
		$this->assertStringNotContainsString( '[:pt]', $processed );
		$this->assertStringContainsString( 'English subtitle', $processed );
		$this->assertStringContainsString( 'Subtitulo em portugues', $processed );

		$items = $processed_doc->getElementsByTagName( 'item' );
		$this->assertSame( 2, $items->length );

		$meta_values_by_item = array();
		foreach ( $items as $item_index => $item_node ) {
			foreach ( $item_node->getElementsByTagName( 'meta_key' ) as $meta_key_node ) {
				if ( 'subtitle' === $meta_key_node->textContent ) {
					$meta_value_node = $meta_key_node->parentNode->getElementsByTagName( 'meta_value' )->item( 0 );
					$meta_values_by_item[ $item_index ] = $meta_value_node ? $meta_value_node->textContent : null;
				}
			}
		}

		$this->assertContains( 'English subtitle', $meta_values_by_item );
		$this->assertContains( 'Subtitulo em portugues', $meta_values_by_item );
	}

	public function test_process_wxr_content_leaves_serialized_postmeta_with_markers_untouched(): void {
		if ( ! function_exists( 'qtxpm_process_wxr_content' ) ) {
			self::loadMigrationEngine();
		}

		$serialized_value = 'a:1:{s:5:"label";s:33:"[:en]English label[:pt]Rotulo[:]";}';

		$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title><![CDATA[[:en]About[:pt]Sobre[:]]]></title>
		<guid isPermaLink="false">page-meta-2</guid>
		<content:encoded><![CDATA[[:en]English body[:pt]Corpo em portugues[:]]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<wp:post_id>2</wp:post_id>
		<wp:post_parent>0</wp:post_parent>
		<wp:post_type><![CDATA[page]]></wp:post_type>
		<wp:menu_order>0</wp:menu_order>
		<wp:postmeta>
			<wp:meta_key>serialized_field</wp:meta_key>
			<wp:meta_value><![CDATA[{$serialized_value}]]></wp:meta_value>
		</wp:postmeta>
	</item>
</channel>
</rss>
XML;

		$doc = new DOMDocument();
		$doc->loadXML( $xml );

		$processed = qtxpm_process_wxr_content( $doc, array( 'en', 'pt' ), 'en' );

		$processed_doc = new DOMDocument();
		$processed_doc->loadXML( $processed );

		$items = $processed_doc->getElementsByTagName( 'item' );
		$this->assertSame( 2, $items->length );

		foreach ( $items as $item_node ) {
			foreach ( $item_node->getElementsByTagName( 'meta_key' ) as $meta_key_node ) {
				if ( 'serialized_field' === $meta_key_node->textContent ) {
					$meta_value_node = $meta_key_node->parentNode->getElementsByTagName( 'meta_value' )->item( 0 );
					$this->assertSame( $serialized_value, $meta_value_node->textContent );
				}
			}
		}
	}

	public function test_direct_import_warns_about_serialized_postmeta_still_containing_markers(): void {
		if ( ! function_exists( 'qtxpm_direct_xml_import' ) ) {
			self::loadMigrationEngine();
		}

		$serialized_value = 'a:1:{s:5:"label";s:33:"[:en]English label[:pt]Rotulo[:]";}';

		$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title>Pagina com meta serializada</title>
		<guid isPermaLink="false">pagina-meta-serializada</guid>
		<content:encoded><![CDATA[Conteudo]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="pt">pt</category>
		<wp:post_id>1</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:00:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[pagina-meta-serializada]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[page]]></wp:post_type>
		<wp:postmeta>
			<wp:meta_key>serialized_field</wp:meta_key>
			<wp:meta_value><![CDATA[{$serialized_value}]]></wp:meta_value>
		</wp:postmeta>
	</item>
</channel>
</rss>
XML;

		$temp_file = tempnam( sys_get_temp_dir(), 'qtx-wxr-' );
		file_put_contents( $temp_file, $xml );

		try {
			$result = qtxpm_direct_xml_import( $temp_file, true );

			$this->assertTrue( $result['success'] );
			$this->assertNotEmpty( $result['warnings'] );
			$this->assertStringContainsString( 'serialized_field', $result['warnings'][0] );
			$this->assertSame(
				$serialized_value,
				$GLOBALS['qtx_wp_post_meta'][1]['serialized_field'] ?? null
			);
		} finally {
			@unlink( $temp_file );
		}
	}

	public function test_raw_blocks_guard_ignores_serialized_postmeta_but_catches_plain_postmeta(): void {
		if ( ! function_exists( 'qtxpm_wxr_has_raw_multilingual_blocks' ) ) {
			self::loadMigrationEngine();
		}

		$serialized_value = 'a:1:{s:5:"label";s:33:"[:en]English label[:pt]Rotulo[:]";}';

		$serialized_only_xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title>Pagina processada</title>
		<guid isPermaLink="false">pagina-processada</guid>
		<content:encoded><![CDATA[Conteudo ja processado]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<wp:post_type><![CDATA[page]]></wp:post_type>
		<wp:postmeta>
			<wp:meta_key>serialized_field</wp:meta_key>
			<wp:meta_value><![CDATA[{$serialized_value}]]></wp:meta_value>
		</wp:postmeta>
	</item>
</channel>
</rss>
XML;

		$xml = simplexml_load_string( $serialized_only_xml );
		$this->assertFalse( qtxpm_wxr_has_raw_multilingual_blocks( $xml ) );

		$plain_raw_meta_xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title>Pagina nao processada</title>
		<guid isPermaLink="false">pagina-nao-processada</guid>
		<content:encoded><![CDATA[Conteudo ja processado]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<wp:post_type><![CDATA[page]]></wp:post_type>
		<wp:postmeta>
			<wp:meta_key>subtitle</wp:meta_key>
			<wp:meta_value><![CDATA[[:en]English subtitle[:pt]Subtitulo em portugues[:]]]></wp:meta_value>
		</wp:postmeta>
	</item>
</channel>
</rss>
XML;

		$xml = simplexml_load_string( $plain_raw_meta_xml );
		$this->assertTrue( qtxpm_wxr_has_raw_multilingual_blocks( $xml ) );
	}

	// ------------------------------------------------------------------
	// B3a — qtxpm_get_polylang_languages() per-request memoization.
	// ------------------------------------------------------------------

	public function test_get_polylang_languages_is_memoized_until_force_refresh(): void {
		if ( ! function_exists( 'qtxpm_get_polylang_languages' ) ) {
			self::loadMigrationEngine();
		}

		$first_call = qtxpm_get_polylang_languages();
		$this->assertContains( 'en', $first_call );

		// Mutate the underlying Polylang stub state directly (bypassing the
		// migrator's own bookkeeping) to prove the *cached* value is what is
		// returned on the next call, not a freshly recomputed one.
		$GLOBALS['qtx_polylang_languages']['de'] = (object) array( 'slug' => 'de', 'locale' => 'de_DE' );

		$cached_call = qtxpm_get_polylang_languages();
		$this->assertNotContains( 'de', $cached_call, 'Cached result must not reflect the out-of-band mutation.' );

		$refreshed_call = qtxpm_get_polylang_languages( true );
		$this->assertContains( 'de', $refreshed_call, 'Forced refresh must recompute and pick up the new language.' );
	}

	public function test_ensure_polylang_language_invalidates_cache_after_provisioning_new_language(): void {
		if ( ! function_exists( 'qtxpm_ensure_polylang_language' ) ) {
			self::loadMigrationEngine();
		}

		// Prime the cache without 'fr'.
		$primed = qtxpm_get_polylang_languages();
		$this->assertNotContains( 'fr', $primed );

		qtxpm_ensure_polylang_language( 'fr' );

		$after_provisioning = qtxpm_get_polylang_languages();
		$this->assertContains( 'fr', $after_provisioning, 'Cache must be invalidated once a new language is provisioned.' );
	}

	// ------------------------------------------------------------------
	// B3b — O(n) hierarchy sort: equivalence with the previous O(n^2) contract.
	// ------------------------------------------------------------------

	public function test_sort_items_by_hierarchy_handles_multiple_subtrees_and_deep_nesting(): void {
		if ( ! function_exists( 'qtxpm_sort_items_by_hierarchy' ) ) {
			self::loadMigrationEngine();
		}

		// Two independent root subtrees, each several levels deep, with
		// siblings intentionally out of menu_order to exercise per-parent
		// sorting in the new indexed implementation.
		$items = array(
			array( 'original_id' => 10, 'original_parent' => 0, 'menu_order' => 1 ),
			array( 'original_id' => 1, 'original_parent' => 0, 'menu_order' => 0 ),
			array( 'original_id' => 11, 'original_parent' => 1, 'menu_order' => 1 ),
			array( 'original_id' => 12, 'original_parent' => 1, 'menu_order' => 0 ),
			array( 'original_id' => 111, 'original_parent' => 11, 'menu_order' => 0 ),
			array( 'original_id' => 121, 'original_parent' => 12, 'menu_order' => 0 ),
			array( 'original_id' => 101, 'original_parent' => 10, 'menu_order' => 0 ),
		);

		$sorted = qtxpm_sort_items_by_hierarchy( $items );
		$ids = array_column( $sorted, 'original_id' );

		// Root 1 (menu_order 0) before root 10 (menu_order 1); within root 1,
		// child 12 (menu_order 0) before child 11 (menu_order 1), each
		// immediately followed by its own child (depth-first).
		$this->assertSame( array( 1, 12, 121, 11, 111, 10, 101 ), $ids );
	}

	public function test_index_items_by_parent_groups_and_sorts_each_bucket(): void {
		if ( ! function_exists( 'qtxpm_index_items_by_parent' ) ) {
			self::loadMigrationEngine();
		}

		$items = array(
			array( 'original_id' => 20, 'original_parent' => 5, 'menu_order' => 0 ),
			array( 'original_id' => 10, 'original_parent' => 5, 'menu_order' => 0 ),
			array( 'original_id' => 1, 'original_parent' => 0, 'menu_order' => 0 ),
		);

		$index = qtxpm_index_items_by_parent( $items );

		$this->assertSame( array( 1 ), array_column( $index[0], 'original_id' ) );
		$this->assertSame( array( 10, 20 ), array_column( $index[5], 'original_id' ) );
	}

	// ------------------------------------------------------------------
	// B3c — staged XML storage is not autoloaded.
	// ------------------------------------------------------------------

	public function test_staged_xml_round_trips_via_non_autoloaded_option(): void {
		if ( ! function_exists( 'qtxpm_set_staged_xml' ) ) {
			self::loadMigrationEngine();
		}

		qtxpm_set_staged_xml( '<rss>processed</rss>', 3600 );

		$this->assertSame( '<rss>processed</rss>', qtxpm_get_staged_xml() );

		$stored = $GLOBALS['qtx_wp_options'][ qtxpm_get_staged_xml_option_name() ] ?? null;
		$this->assertIsArray( $stored );
		$this->assertArrayHasKey( 'value', $stored );
		$this->assertArrayHasKey( 'expires_at', $stored );

		qtxpm_delete_staged_xml();
		$this->assertFalse( qtxpm_get_staged_xml() );
	}

	public function test_staged_xml_expires_after_ttl(): void {
		if ( ! function_exists( 'qtxpm_set_staged_xml' ) ) {
			self::loadMigrationEngine();
		}

		qtxpm_set_staged_xml( '<rss>expired</rss>', -1 );

		$this->assertFalse( qtxpm_get_staged_xml() );
	}
}
