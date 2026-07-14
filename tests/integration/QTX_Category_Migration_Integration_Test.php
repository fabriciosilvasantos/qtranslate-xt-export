<?php
/**
 * Integration tests for taxonomy (category/tag) migration.
 *
 * Covers the P0 fix where real WXR `<category domain="category">` and
 * `<category domain="post_tag">` elements were silently discarded during
 * import (only `domain="language"` and empty domains were handled).
 */

use PHPUnit\Framework\TestCase;

class QTX_Category_Migration_Integration_Test extends TestCase {

	private static function loadMigrationEngine(): void {
		if ( ! function_exists( 'qtxpm_direct_xml_import' ) ) {
			require_once dirname( __DIR__, 2 ) . '/qtx-polylang-migrator/includes/migration-engine.php';
		}
	}

	public static function setUpBeforeClass(): void {
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

	private static function importXml( string $xml, bool $force = true ): array {
		$temp_file = tempnam( sys_get_temp_dir(), 'qtx-wxr-cat-' );
		file_put_contents( $temp_file, $xml );

		try {
			return qtxpm_direct_xml_import( $temp_file, $force );
		} finally {
			@unlink( $temp_file );
		}
	}

	public function test_multilingual_category_creates_one_term_per_language_and_links_translations(): void {
		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title>Post PT</title>
		<guid isPermaLink="false">post-pt</guid>
		<content:encoded><![CDATA[Conteudo]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="pt">pt</category>
		<category domain="category" nicename="noticias"><![CDATA[[:pt]Noticias[:en]News[:]]]></category>
		<wp:post_id>1</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:00:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[post-pt]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[post]]></wp:post_type>
	</item>
	<item>
		<title>Post EN</title>
		<guid isPermaLink="false">post-en</guid>
		<content:encoded><![CDATA[Content]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="en">en</category>
		<category domain="category" nicename="noticias"><![CDATA[[:pt]Noticias[:en]News[:]]]></category>
		<wp:post_id>2</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:00:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[post-en]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[post]]></wp:post_type>
	</item>
</channel>
</rss>
XML;

		$result = self::importXml( $xml );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 2, $result['imported'] );

		$terms = $GLOBALS['qtx_wp_terms'];
		$this->assertCount( 2, $terms, 'Exactly one term per language should be created, no more.' );

		$pt_term = null;
		$en_term = null;
		foreach ( $terms as $term ) {
			if ( 'Noticias' === $term['name'] ) {
				$pt_term = $term;
			} elseif ( 'News' === $term['name'] ) {
				$en_term = $term;
			}
		}

		$this->assertNotNull( $pt_term, 'PT term variant must be created.' );
		$this->assertNotNull( $en_term, 'EN term variant must be created.' );
		$this->assertSame( 'category', $pt_term['taxonomy'] );
		$this->assertSame( 'pt', $GLOBALS['qtx_polylang_term_languages'][ $pt_term['term_id'] ] ?? null );
		$this->assertSame( 'en', $GLOBALS['qtx_polylang_term_languages'][ $en_term['term_id'] ] ?? null );

		// The two variants must be linked as Polylang term translations.
		$this->assertCount( 1, $GLOBALS['qtx_polylang_saved_term_translations'] );
		$this->assertSame(
			array(
				'pt' => $pt_term['term_id'],
				'en' => $en_term['term_id'],
			),
			$GLOBALS['qtx_polylang_saved_term_translations'][0]
		);

		// Each post must be associated with the term matching its own language.
		$post_pt_terms = $GLOBALS['qtx_wp_object_terms'][1]['category'] ?? array();
		$post_en_terms = $GLOBALS['qtx_wp_object_terms'][2]['category'] ?? array();

		$this->assertSame( array( $pt_term['term_id'] ), $post_pt_terms );
		$this->assertSame( array( $en_term['term_id'] ), $post_en_terms );
	}

	public function test_multilingual_post_tag_uses_object_terms_not_categories(): void {
		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title>Tagged Post</title>
		<guid isPermaLink="false">tagged-post</guid>
		<content:encoded><![CDATA[Conteudo]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="pt">pt</category>
		<category domain="post_tag" nicename="destaque"><![CDATA[[:pt]Destaque[:en]Featured[:]]]></category>
		<wp:post_id>10</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:00:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[tagged-post]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[post]]></wp:post_type>
	</item>
</channel>
</rss>
XML;

		$result = self::importXml( $xml );
		$this->assertTrue( $result['success'] );

		// Both language variants are created (for translation completeness,
		// same as the WXR transformer splits post content per language) even
		// though only one post references this tag; only the post's own
		// language variant gets attached to it below.
		$terms = $GLOBALS['qtx_wp_terms'];
		$this->assertCount( 2, $terms );

		$pt_term = null;
		$en_term = null;
		foreach ( $terms as $term ) {
			if ( 'Destaque' === $term['name'] ) {
				$pt_term = $term;
			} elseif ( 'Featured' === $term['name'] ) {
				$en_term = $term;
			}
		}

		$this->assertNotNull( $pt_term );
		$this->assertNotNull( $en_term );
		$this->assertSame( 'post_tag', $pt_term['taxonomy'] );
		$this->assertSame( 'post_tag', $en_term['taxonomy'] );

		// The stub's wp_insert_post() assigns sequential new IDs starting at
		// 1, independent of the WXR wp:post_id; a single imported item here
		// always becomes post ID 1.
		$post_terms = $GLOBALS['qtx_wp_object_terms'][1]['post_tag'] ?? array();
		$this->assertSame( array( $pt_term['term_id'] ), $post_terms );

		// wp_set_post_categories() must not be used for tags.
		$this->assertArrayNotHasKey( 'category', $GLOBALS['qtx_wp_object_terms'][1] ?? array() );
	}

	public function test_monolingual_category_creates_single_term_without_translation_group(): void {
		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title>Plain Post</title>
		<guid isPermaLink="false">plain-post</guid>
		<content:encoded><![CDATA[Content]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="en">en</category>
		<category domain="category" nicename="uncategorized">Uncategorized</category>
		<wp:post_id>20</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:00:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[plain-post]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[post]]></wp:post_type>
	</item>
</channel>
</rss>
XML;

		$result = self::importXml( $xml );
		$this->assertTrue( $result['success'] );

		$terms = $GLOBALS['qtx_wp_terms'];
		$this->assertCount( 1, $terms );

		$term = reset( $terms );
		$this->assertSame( 'Uncategorized', $term['name'] );
		$this->assertSame( 'en', $GLOBALS['qtx_polylang_term_languages'][ $term['term_id'] ] ?? null );
		$this->assertEmpty( $GLOBALS['qtx_polylang_saved_term_translations'] );

		// Single imported item -> stub-assigned post ID 1 (see note above).
		$post_terms = $GLOBALS['qtx_wp_object_terms'][1]['category'] ?? array();
		$this->assertSame( array( $term['term_id'] ), $post_terms );
	}

	public function test_existing_term_is_reused_not_duplicated_across_posts(): void {
		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title>First Post</title>
		<guid isPermaLink="false">first-post</guid>
		<content:encoded><![CDATA[Content]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="en">en</category>
		<category domain="category" nicename="news">News</category>
		<wp:post_id>30</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:00:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[first-post]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[post]]></wp:post_type>
	</item>
	<item>
		<title>Second Post</title>
		<guid isPermaLink="false">second-post</guid>
		<content:encoded><![CDATA[Content]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="en">en</category>
		<category domain="category" nicename="news">News</category>
		<wp:post_id>31</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:01:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:01:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:01:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:01:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[second-post]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[post]]></wp:post_type>
	</item>
</channel>
</rss>
XML;

		$result = self::importXml( $xml );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 2, $result['imported'] );

		$terms = $GLOBALS['qtx_wp_terms'];
		$this->assertCount( 1, $terms, 'Both posts share the same category name/language; only one term should exist.' );

		$term = reset( $terms );
		// Two imported items -> stub-assigned post IDs 1 and 2, in document order.
		$post_1_terms = $GLOBALS['qtx_wp_object_terms'][1]['category'] ?? array();
		$post_2_terms = $GLOBALS['qtx_wp_object_terms'][2]['category'] ?? array();

		$this->assertSame( array( $term['term_id'] ), $post_1_terms );
		$this->assertSame( array( $term['term_id'] ), $post_2_terms );
	}

	public function test_rebuild_term_hierarchy_reparents_migrated_categories(): void {
		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<title>Site A</title>
	<link>https://site-a.example</link>
	<wp:category>
		<wp:term_id>5</wp:term_id>
		<wp:category_nicename>noticias</wp:category_nicename>
		<wp:category_parent></wp:category_parent>
		<wp:cat_name>Noticias</wp:cat_name>
	</wp:category>
	<wp:category>
		<wp:term_id>6</wp:term_id>
		<wp:category_nicename>noticias-internas</wp:category_nicename>
		<wp:category_parent>noticias</wp:category_parent>
		<wp:cat_name>Noticias Internas</wp:cat_name>
	</wp:category>
	<item>
		<title>Post</title>
		<guid isPermaLink="false">post-1</guid>
		<content:encoded><![CDATA[Content]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="pt">pt</category>
		<category domain="category" nicename="noticias">Noticias</category>
		<wp:post_id>1</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:00:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[post-1]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[post]]></wp:post_type>
	</item>
	<item>
		<title>Child Post</title>
		<guid isPermaLink="false">post-2</guid>
		<content:encoded><![CDATA[Content]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="pt">pt</category>
		<category domain="category" nicename="noticias-internas">Noticias Internas</category>
		<wp:post_id>2</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:01:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:01:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:01:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:01:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[post-2]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[post]]></wp:post_type>
	</item>
</channel>
</rss>
XML;

		$result = self::importXml( $xml );
		$this->assertTrue( $result['success'] );

		$terms = $GLOBALS['qtx_wp_terms'];
		$this->assertCount( 2, $terms );

		$parent_term = null;
		$child_term = null;
		foreach ( $terms as $term ) {
			if ( 'Noticias' === $term['name'] ) {
				$parent_term = $term;
			} elseif ( 'Noticias Internas' === $term['name'] ) {
				$child_term = $term;
			}
		}

		$this->assertNotNull( $parent_term );
		$this->assertNotNull( $child_term );

		$this->assertSame(
			5,
			$GLOBALS['qtx_wp_term_meta'][ $parent_term['term_id'] ]['_pll_migration_original_id'] ?? null
		);
		$this->assertSame(
			6,
			$GLOBALS['qtx_wp_term_meta'][ $child_term['term_id'] ]['_pll_migration_original_id'] ?? null
		);
		$this->assertSame(
			5,
			$GLOBALS['qtx_wp_term_meta'][ $child_term['term_id'] ]['_pll_migration_parent_id'] ?? null
		);

		global $wpdb;
		$original_wpdb = $wpdb;
		$wpdb = new class() extends wpdb {
			public function get_results( $query, $output = OBJECT ) {
				$terms = $GLOBALS['qtx_wp_terms'] ?? array();
				$term_meta = $GLOBALS['qtx_wp_term_meta'] ?? array();

				if ( false !== strpos( $query, "tm_original.meta_key = '_pll_migration_original_id'" )
					&& false === strpos( $query, "tm_parent.meta_key" ) ) {
					$rows = array();
					foreach ( $terms as $term ) {
						$meta = $term_meta[ $term['term_id'] ] ?? array();
						if ( ! isset( $meta['_pll_migration_original_id'] ) ) {
							continue;
						}
						$rows[] = (object) array(
							'term_id'     => $term['term_id'],
							'original_id' => $meta['_pll_migration_original_id'],
							'lang'        => $meta['_pll_migration_lang'] ?? '',
						);
					}
					return $rows;
				}

				if ( false !== strpos( $query, "tm_parent.meta_key = '_pll_migration_parent_id'" ) ) {
					$rows = array();
					foreach ( $terms as $term ) {
						$meta = $term_meta[ $term['term_id'] ] ?? array();
						if ( ! isset( $meta['_pll_migration_parent_id'] ) || 0 === (int) $meta['_pll_migration_parent_id'] ) {
							continue;
						}
						$rows[] = (object) array(
							'term_id'             => $term['term_id'],
							'original_parent_id'  => $meta['_pll_migration_parent_id'],
							'lang'                => $meta['_pll_migration_lang'] ?? '',
						);
					}
					return $rows;
				}

				return array();
			}

			public function update( $table, $data, $where, $format = null, $where_format = null ) {
				if ( $table === $this->term_taxonomy && isset( $where['term_id'] ) ) {
					$GLOBALS['qtx_wp_term_taxonomy_parent'][ $where['term_id'] ] = $data['parent'];
				}
				return 1;
			}
		};

		try {
			$hierarchy_result = qtxpm_rebuild_term_hierarchy();

			$this->assertSame( 1, $hierarchy_result['updated'] );
			$this->assertSame(
				$parent_term['term_id'],
				$GLOBALS['qtx_wp_term_taxonomy_parent'][ $child_term['term_id'] ] ?? null
			);
		} finally {
			$wpdb = $original_wpdb;
		}
	}
}
