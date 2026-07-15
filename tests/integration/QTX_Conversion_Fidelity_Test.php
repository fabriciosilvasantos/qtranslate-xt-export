<?php
/**
 * Fidelity-focused integration tests for the qTranslate -> Polylang
 * migration pipeline (`qtxpm_process_wxr_content` + `qtxpm_direct_xml_import`).
 *
 * Unlike QTX_WXR_Fixture_Integration_Test (which mostly asserts substring
 * containment), these tests assert STRICT equality between the text a
 * language block held in the original WXR and the text that ends up stored
 * on the imported post for that language — covering title, content, and
 * excerpt — plus post_date/post_status preservation, multilingual excerpt
 * splitting, HTML-entity decoding (title without CDATA), and UTF-8 fidelity
 * (accents + CJK + emoji) end to end through DOMDocument -> transform ->
 * SimpleXML -> wp_insert_post.
 */

use PHPUnit\Framework\TestCase;

class QTX_Conversion_Fidelity_Test extends TestCase {

	private static function loadMigrationEngine(): void {
		if ( ! function_exists( 'qtxpm_process_wxr_content' ) ) {
			require_once dirname( __DIR__, 2 ) . '/qtx-polylang-migrator/includes/migration-engine.php';
		}
	}

	public static function setUpBeforeClass(): void {
		self::loadMigrationEngine();
	}

	/**
	 * @var array<string, mixed>|null
	 */
	private ?array $original_q_config = null;

	protected function setUp(): void {
		global $q_config;

		// Snapshot $q_config so it can be restored in tearDown(). Other
		// integration test files in this suite mutate this shared global
		// without restoring it, so tests here can otherwise leak language
		// state into whichever test class PHPUnit happens to run next
		// (observed as an order-dependent failure in
		// QTX_Integration_Test::test_default_language when this file is
		// picked up before that one alphabetically).
		$this->original_q_config = is_array( $q_config ) ? $q_config : null;

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

	protected function tearDown(): void {
		global $q_config;

		if ( null !== $this->original_q_config ) {
			$q_config = $this->original_q_config;
		}
	}

	/**
	 * Wrap one or more <item> XML fragments in a minimal valid WXR document.
	 */
	private function wrapWxrItems( string $itemsXml ): string {
		return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
{$itemsXml}
</channel>
</rss>
XML;
	}

	/**
	 * Build a single WXR <item> fragment from a field spec. Title, content,
	 * and excerpt are wrapped in CDATA (the common WXR export shape).
	 *
	 * @param array<string, mixed> $item
	 */
	private function buildWxrItem( array $item ): string {
		$item += array(
			'post_id'         => 1,
			'guid'            => 'item-' . $item['post_id'] ?? '1',
			'title'           => '',
			'content'         => '',
			'excerpt'         => '',
			'post_name'       => 'item',
			'status'          => 'publish',
			'post_type'       => 'page',
			'post_date'       => '2026-01-01 10:00:00',
			'post_date_gmt'   => '2026-01-01 13:00:00',
		);

		return <<<XML
	<item>
		<title><![CDATA[{$item['title']}]]></title>
		<guid isPermaLink="false">{$item['guid']}</guid>
		<content:encoded><![CDATA[{$item['content']}]]></content:encoded>
		<excerpt:encoded><![CDATA[{$item['excerpt']}]]></excerpt:encoded>
		<wp:post_id>{$item['post_id']}</wp:post_id>
		<wp:post_date><![CDATA[{$item['post_date']}]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[{$item['post_date_gmt']}]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[{$item['post_date']}]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[{$item['post_date_gmt']}]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[{$item['post_name']}]]></wp:post_name>
		<wp:status><![CDATA[{$item['status']}]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[{$item['post_type']}]]></wp:post_type>
	</item>
XML;
	}

	/**
	 * Run the full pipeline: DOMDocument -> qtxpm_process_wxr_content ->
	 * temp file -> qtxpm_direct_xml_import -> SimpleXML.
	 *
	 * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
	 */
	private function processAndImport( string $rawXml, array $languages, string $default_lang ): array {
		$doc = new DOMDocument();
		$loaded = $doc->loadXML( $rawXml );
		$this->assertTrue( $loaded, 'Test fixture XML failed to parse.' );

		$processed = qtxpm_process_wxr_content( $doc, $languages, $default_lang );

		$temp_file = tempnam( sys_get_temp_dir(), 'qtx-fidelity-' );
		file_put_contents( $temp_file, $processed );

		try {
			$result = qtxpm_direct_xml_import( $temp_file, true );
		} finally {
			@unlink( $temp_file );
		}

		return array( $result, qtx_wordpress_stub_get_inserted_posts() );
	}

	/**
	 * Find the imported post whose post_name starts with the given prefix
	 * AND whose Polylang-assigned language matches, using the real
	 * pll_get_post_language() stub state (not the WXR <category> element),
	 * since that is what qtxpm_assign_post_language() actually records.
	 *
	 * @param array<int, array<string, mixed>> $inserted_posts
	 */
	private function findImportedPostByNameAndLanguage( array $inserted_posts, string $post_name_prefix, string $language ): ?array {
		foreach ( $inserted_posts as $post_id => $post ) {
			$name = (string) ( $post['post_name'] ?? '' );
			if ( 0 !== strpos( $name, $post_name_prefix ) ) {
				continue;
			}

			if ( function_exists( 'pll_get_post_language' ) && pll_get_post_language( (int) $post_id ) === $language ) {
				return $post;
			}
		}

		return null;
	}

	// -------------------------------------------------------------------
	// 1. Strict round-trip of title/content/excerpt text per language.
	// -------------------------------------------------------------------

	public function test_round_trip_preserves_full_title_content_and_excerpt_per_language(): void {
		$title_a = '[:pt]Título Único: ção, ã, é[:en]Unique Title: composition[:]';
		$content_a = '[:pt]<p>Conteúdo <b>rico</b> em português, com acentuação completa.</p>[:en]<p>Rich <b>content</b> in English, fully preserved.</p>[:]';
		$excerpt_a = '[:pt]Resumo em português.[:en]Summary in English.[:]';

		// Item B exercises the legacy HTML-comment syntax to prove strict
		// equality holds regardless of which of the three block syntaxes
		// produced the split (not just substring containment).
		$title_b = '<!--:pt-->Segunda Página<!--:en-->Second Page<!--:-->';
		$content_b = '<!--:pt-->Parágrafo com <em>ênfase</em> e quebra de linha.
Segunda linha.<!--:en-->Paragraph with <em>emphasis</em> and a line break.
Second line.<!--:-->';
		$excerpt_b = '<!--:pt-->Resumo B.<!--:en-->Summary B.<!--:-->';

		$items_xml = $this->buildWxrItem(
			array(
				'post_id'   => 1,
				'guid'      => 'page-a',
				'title'     => $title_a,
				'content'   => $content_a,
				'excerpt'   => $excerpt_a,
				'post_name' => 'pagina-a',
			)
		) . $this->buildWxrItem(
			array(
				'post_id'   => 2,
				'guid'      => 'page-b',
				'title'     => $title_b,
				'content'   => $content_b,
				'excerpt'   => $excerpt_b,
				'post_name' => 'pagina-b',
			)
		);

		list( $result, $inserted_posts ) = $this->processAndImport(
			$this->wrapWxrItems( $items_xml ),
			array( 'pt', 'en' ),
			'pt'
		);

		$this->assertTrue( $result['success'] );

		$cases = array(
			array( 'pagina-a', $title_a, $content_a, $excerpt_a ),
			array( 'pagina-b', $title_b, $content_b, $excerpt_b ),
		);

		foreach ( $cases as list( $post_name, $raw_title, $raw_content, $raw_excerpt ) ) {
			$expected_titles = qtxpm_split_multilingual_text( $raw_title, array( 'pt', 'en' ) );
			$expected_contents = qtxpm_split_multilingual_text( $raw_content, array( 'pt', 'en' ) );
			$expected_excerpts = qtxpm_split_multilingual_text( $raw_excerpt, array( 'pt', 'en' ) );

			foreach ( array( 'pt', 'en' ) as $lang ) {
				$post = $this->findImportedPostByNameAndLanguage( $inserted_posts, $post_name, $lang );
				$this->assertNotNull( $post, "Expected an imported '{$lang}' post for '{$post_name}'." );

				$this->assertSame(
					$expected_titles[ $lang ],
					$post['post_title'],
					"Title mismatch for '{$post_name}' [{$lang}] — round trip must be exact, not a substring match."
				);
				$this->assertSame(
					$expected_contents[ $lang ],
					$post['post_content'],
					"Content mismatch for '{$post_name}' [{$lang}] — round trip must be exact, not a substring match."
				);
				$this->assertSame(
					$expected_excerpts[ $lang ],
					$post['post_excerpt'],
					"Excerpt mismatch for '{$post_name}' [{$lang}] — round trip must be exact, not a substring match."
				);
			}
		}
	}

	// -------------------------------------------------------------------
	// 2. post_date and post_status preservation.
	// -------------------------------------------------------------------

	public function test_direct_import_preserves_post_date_and_post_status_exactly(): void {
		$items_xml = $this->buildWxrItem(
			array(
				'post_id'       => 10,
				'guid'          => 'published-item',
				'title'         => 'Item Publicado',
				'content'       => 'Conteúdo publicado.',
				'post_name'     => 'item-publicado',
				'status'        => 'publish',
				'post_date'     => '2020-01-01 10:00:00',
				'post_date_gmt' => '2020-01-01 13:00:00',
			)
		) . $this->buildWxrItem(
			array(
				'post_id'       => 11,
				'guid'          => 'draft-item',
				'title'         => 'Item Rascunho',
				'content'       => 'Conteúdo rascunho.',
				'post_name'     => 'item-rascunho',
				'status'        => 'draft',
				'post_date'     => '2021-06-15 08:30:00',
				'post_date_gmt' => '2021-06-15 11:30:00',
			)
		) . $this->buildWxrItem(
			array(
				'post_id'       => 12,
				'guid'          => 'private-item',
				'title'         => 'Item Privado',
				'content'       => 'Conteúdo privado.',
				'post_name'     => 'item-privado',
				'status'        => 'private',
				'post_date'     => '1999-12-31 23:59:59',
				'post_date_gmt' => '2000-01-01 02:59:59',
			)
		);

		list( $result, $inserted_posts ) = $this->processAndImport(
			$this->wrapWxrItems( $items_xml ),
			array( 'pt', 'en' ),
			'pt'
		);

		$this->assertTrue( $result['success'] );
		$this->assertCount( 3, $inserted_posts );

		$expected = array(
			'item-publicado' => array(
				'status'      => 'publish',
				'date'        => '2020-01-01 10:00:00',
				'date_gmt'    => '2020-01-01 13:00:00',
			),
			'item-rascunho' => array(
				'status'      => 'draft',
				'date'        => '2021-06-15 08:30:00',
				'date_gmt'    => '2021-06-15 11:30:00',
			),
			'item-privado' => array(
				'status'      => 'private',
				'date'        => '1999-12-31 23:59:59',
				'date_gmt'    => '2000-01-01 02:59:59',
			),
		);

		foreach ( $expected as $post_name => $expectation ) {
			$post = null;
			foreach ( $inserted_posts as $candidate ) {
				if ( ( $candidate['post_name'] ?? '' ) === $post_name ) {
					$post = $candidate;
					break;
				}
			}

			$this->assertNotNull( $post, "Expected an imported post named '{$post_name}'." );
			$this->assertSame( $expectation['status'], $post['post_status'], "post_status mismatch for '{$post_name}'." );
			$this->assertSame( $expectation['date'], $post['post_date'], "post_date mismatch for '{$post_name}'." );
			$this->assertSame( $expectation['date_gmt'], $post['post_date_gmt'], "post_date_gmt mismatch for '{$post_name}'." );
		}
	}

	// -------------------------------------------------------------------
	// 3. Multilingual excerpt splitting.
	// -------------------------------------------------------------------

	public function test_multilingual_excerpt_is_split_per_language_while_monolingual_title_passes_through(): void {
		// Title/content are monolingual (no language blocks); only the
		// excerpt carries per-language blocks. The pipeline DOES split
		// wp:excerpt:encoded independently from title/content (see
		// qtxpm_process_wxr_content(), which splits and re-resolves title,
		// content, and excerpt separately) — so each language variant must
		// receive ITS OWN excerpt, while the (language-less) title/content
		// fall back identically to the original text for every variant.
		$title = 'Página com resumo multilíngue';
		$content = '<p>Conteúdo único, sem blocos de idioma.</p>';
		$excerpt = '[:pt]Resumo em português apenas.[:en]Summary in English only.[:]';

		$items_xml = $this->buildWxrItem(
			array(
				'post_id'   => 20,
				'guid'      => 'excerpt-item',
				'title'     => $title,
				'content'   => $content,
				'excerpt'   => $excerpt,
				'post_name' => 'pagina-resumo',
			)
		);

		list( $result, $inserted_posts ) = $this->processAndImport(
			$this->wrapWxrItems( $items_xml ),
			array( 'pt', 'en' ),
			'pt'
		);

		$this->assertTrue( $result['success'] );

		$pt_post = $this->findImportedPostByNameAndLanguage( $inserted_posts, 'pagina-resumo', 'pt' );
		$en_post = $this->findImportedPostByNameAndLanguage( $inserted_posts, 'pagina-resumo', 'en' );

		$this->assertNotNull( $pt_post, 'Expected a pt-language variant.' );
		$this->assertNotNull( $en_post, 'Expected an en-language variant.' );

		$this->assertSame( 'Resumo em português apenas.', $pt_post['post_excerpt'] );
		$this->assertSame( 'Summary in English only.', $en_post['post_excerpt'] );

		// Title/content have no language markers at all, so
		// qtxpm_split_multilingual_text() returns an empty split and
		// qtxpm_get_language_value() falls back to the original (identical)
		// text for both language variants — this is documented, correct
		// fallback behaviour, not a bug.
		$this->assertSame( $title, $pt_post['post_title'] );
		$this->assertSame( $title, $en_post['post_title'] );
		$this->assertSame( $content, $pt_post['post_content'] );
		$this->assertSame( $content, $en_post['post_content'] );

		// No leftover qTranslate markers in the split excerpts.
		$this->assertStringNotContainsString( '[:pt]', $pt_post['post_excerpt'] );
		$this->assertStringNotContainsString( '[:en]', $en_post['post_excerpt'] );
	}

	// -------------------------------------------------------------------
	// 4. HTML entities outside CDATA decode exactly once.
	// -------------------------------------------------------------------

	public function test_html_entities_outside_cdata_are_decoded_exactly_once(): void {
		// Title is intentionally NOT wrapped in CDATA, so the entities are
		// interpreted by the XML parser itself (both when qtxpm_process_wxr_content
		// loads the document, and again when qtxpm_direct_xml_import re-parses
		// the transformed output via simplexml_load_file()).
		$raw_xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title>[:pt]Caf&#xe9; &amp; P&#xe3;o[:en]Ideas &amp; &lt;em&gt;Progress&lt;/em&gt; &#x2019;quoted&#x2019;[:]</title>
		<guid isPermaLink="false">entities-item</guid>
		<content:encoded><![CDATA[[:pt]Sem conteudo especial[:en]No special content[:]]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<wp:post_id>30</wp:post_id>
		<wp:post_date><![CDATA[2026-01-01 10:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-01-01 13:00:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-01-01 10:00:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-01-01 13:00:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[entities-item]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[page]]></wp:post_type>
	</item>
</channel>
</rss>
XML;

		list( $result, $inserted_posts ) = $this->processAndImport( $raw_xml, array( 'pt', 'en' ), 'pt' );

		$this->assertTrue( $result['success'] );

		$pt_post = $this->findImportedPostByNameAndLanguage( $inserted_posts, 'entities-item', 'pt' );
		$en_post = $this->findImportedPostByNameAndLanguage( $inserted_posts, 'entities-item', 'en' );

		$this->assertNotNull( $pt_post );
		$this->assertNotNull( $en_post );

		// Entities must be decoded to their literal characters exactly once:
		// no residual "&amp;", "&#x2019;" etc, and no double-decoding
		// artefacts like "&amp;amp;" or mangled multi-byte sequences.
		$this->assertSame( 'Café & Pão', $pt_post['post_title'] );
		$this->assertSame( 'Ideas & <em>Progress</em> ’quoted’', $en_post['post_title'] );

		foreach ( array( $pt_post['post_title'], $en_post['post_title'] ) as $title ) {
			$this->assertStringNotContainsString( '&amp;', $title );
			$this->assertStringNotContainsString( '&#', $title );
			$this->assertStringNotContainsString( '&lt;', $title );
			$this->assertStringNotContainsString( '&gt;', $title );
		}
	}

	// -------------------------------------------------------------------
	// 5. UTF-8 fidelity across the full pipeline (accents + CJK + emoji).
	// -------------------------------------------------------------------

	public function test_utf8_accents_cjk_and_emoji_survive_the_full_pipeline_intact(): void {
		$title = '[:pt]Configuração e ação: café, mãe, avó 🎉[:en]Chinese sample: 中文测试 emoji: 🎉🚀[:]';
		$content = '[:pt]<p>Texto com acentuação:ção, ã, é, õ, ü.</p>[:en]<p>Ideographs: 日本語のテスト 中文测试, rocket: 🚀</p>[:]';

		$items_xml = $this->buildWxrItem(
			array(
				'post_id'   => 40,
				'guid'      => 'utf8-item',
				'title'     => $title,
				'content'   => $content,
				'post_name' => 'utf8-item',
			)
		);

		list( $result, $inserted_posts ) = $this->processAndImport(
			$this->wrapWxrItems( $items_xml ),
			array( 'pt', 'en' ),
			'pt'
		);

		$this->assertTrue( $result['success'] );

		$expected_titles = qtxpm_split_multilingual_text( $title, array( 'pt', 'en' ) );
		$expected_contents = qtxpm_split_multilingual_text( $content, array( 'pt', 'en' ) );

		$pt_post = $this->findImportedPostByNameAndLanguage( $inserted_posts, 'utf8-item', 'pt' );
		$en_post = $this->findImportedPostByNameAndLanguage( $inserted_posts, 'utf8-item', 'en' );

		$this->assertNotNull( $pt_post );
		$this->assertNotNull( $en_post );

		$this->assertSame( $expected_titles['pt'], $pt_post['post_title'] );
		$this->assertSame( $expected_titles['en'], $en_post['post_title'] );
		$this->assertSame( $expected_contents['pt'], $pt_post['post_content'] );
		$this->assertSame( $expected_contents['en'], $en_post['post_content'] );

		// Belt-and-braces: assert the exact literal strings too (not only
		// equality against the split helper's own output), so a bug shared
		// between the split helper and the pipeline cannot hide a fidelity
		// regression from this test.
		$this->assertSame( 'Configuração e ação: café, mãe, avó 🎉', $pt_post['post_title'] );
		$this->assertSame( 'Chinese sample: 中文测试 emoji: 🎉🚀', $en_post['post_title'] );
	}

	// -------------------------------------------------------------------
	// 5. Robustness against WXR documents missing a standard namespace
	//    declaration (e.g. `xmlns:excerpt`) on the `<rss>` root element.
	// -------------------------------------------------------------------

	/**
	 * Regression test: a real, well-formed WXR export that never uses
	 * `<excerpt:encoded>` anywhere (e.g. no post has an excerpt) commonly
	 * omits the `xmlns:excerpt` declaration entirely from its `<rss>` root,
	 * since the prefix is simply unused. Before the fix, `qtxpm_build_wxr_xpath()`
	 * (formerly inlined in `qtxpm_process_wxr_content()`) only registered
	 * namespaces actually declared on `<rss>`, so `DOMXPath::query( 'excerpt:encoded', ... )`
	 * returned `false` (unbound prefix) instead of an empty node list, and
	 * the immediately following `->item( 0 )` call fataled with "Call to a
	 * member function item() on bool".
	 */
	public function test_wxr_without_excerpt_namespace_declaration_does_not_fatal(): void {
		$raw_xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title><![CDATA[[:pt]Título[:en]Title[:]]]></title>
		<guid isPermaLink="false">item-1</guid>
		<content:encoded><![CDATA[[:pt]Conteúdo[:en]Content[:]]]></content:encoded>
		<wp:post_id>1</wp:post_id>
		<wp:post_date><![CDATA[2026-01-01 10:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-01-01 13:00:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-01-01 10:00:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-01-01 13:00:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[item-1]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[page]]></wp:post_type>
	</item>
</channel>
</rss>
XML;

		list( $result, $inserted_posts ) = $this->processAndImport( $raw_xml, array( 'pt', 'en' ), 'pt' );

		$this->assertTrue( $result['success'] );

		$pt_post = $this->findImportedPostByNameAndLanguage( $inserted_posts, 'item-1', 'pt' );
		$en_post = $this->findImportedPostByNameAndLanguage( $inserted_posts, 'item-1', 'en' );

		$this->assertNotNull( $pt_post );
		$this->assertNotNull( $en_post );
		$this->assertSame( 'Título', $pt_post['post_title'] );
		$this->assertSame( 'Title', $en_post['post_title'] );
		$this->assertSame( '', $pt_post['post_excerpt'] ?? '', 'Missing excerpt namespace/element must resolve to an empty excerpt, not a fatal error.' );
	}
}
