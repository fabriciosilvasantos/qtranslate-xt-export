<?php
/**
 * Integration tests for the qtxpm_direct_xml_import() safety fixes:
 *
 *  - libxml_use_internal_errors() state must always be restored to what it
 *    was before the call, on both the success and the failure paths.
 *  - Untransformed qTranslate language blocks ([:xx], <!--:xx-->, {:xx})
 *    must be rejected up front unless $allow_raw is explicitly true, since
 *    importing them verbatim corrupts post titles/content.
 */

use PHPUnit\Framework\TestCase;

class QTX_Direct_Import_Raw_Guard_Test extends TestCase {

	private const FIXTURE_PATH = __DIR__ . '/../fixtures/sample-multilingual-wxr.xml';

	private static function loadMigrationEngine(): void {
		if ( ! function_exists( 'qtxpm_direct_xml_import' ) ) {
			require_once dirname( __DIR__, 2 ) . '/qtx-polylang-migrator/includes/migration-engine.php';
		}
	}

	public static function setUpBeforeClass(): void {
		self::loadMigrationEngine();
	}

	/**
	 * Snapshot of $q_config['language'] before this test mutates it, restored
	 * in tearDown() so this class does not leak language state into other
	 * integration test classes that run afterwards in the same process
	 * (the qTranslate translator singleton reads $q_config['language'] live).
	 *
	 * @var string|null
	 */
	private ?string $previousLanguage = null;

	protected function setUp(): void {
		global $q_config;

		$this->previousLanguage = $q_config['language'] ?? null;

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

		if ( null !== $this->previousLanguage ) {
			$q_config['language'] = $this->previousLanguage;
		}
	}

	private function writeTempXml( string $xml ): string {
		$temp_file = tempnam( sys_get_temp_dir(), 'qtx-raw-guard-' );
		file_put_contents( $temp_file, $xml );

		return $temp_file;
	}

	// ------------------------------------------------------------------
	// libxml_use_internal_errors() restoration.
	// ------------------------------------------------------------------

	public function test_libxml_internal_errors_state_is_restored_after_successful_import(): void {
		$temp_file = $this->writeTempXml( $this->buildValidTransformedXml() );

		$original_state = libxml_use_internal_errors( false );
		libxml_use_internal_errors( $original_state );

		try {
			$result = qtxpm_direct_xml_import( $temp_file, true );
			$this->assertTrue( $result['success'] );

			// libxml_use_internal_errors() returns the *previous* state and
			// sets the new one, so calling it again lets us read the state
			// without mutating it any further than this assertion requires.
			$state_after = libxml_use_internal_errors( $original_state );
			$this->assertSame( $original_state, $state_after, 'libxml internal-errors state must be restored after a successful import.' );
		} finally {
			@unlink( $temp_file );
		}
	}

	public function test_libxml_internal_errors_state_is_restored_after_parse_failure(): void {
		$temp_file = $this->writeTempXml( 'this is not valid xml <<<' );

		$original_state = libxml_use_internal_errors( false );
		libxml_use_internal_errors( $original_state );

		try {
			$result = qtxpm_direct_xml_import( $temp_file, true );
			$this->assertFalse( $result['success'] );

			$state_after = libxml_use_internal_errors( $original_state );
			$this->assertSame( $original_state, $state_after, 'libxml internal-errors state must be restored even when XML parsing fails.' );
		} finally {
			@unlink( $temp_file );
		}
	}

	public function test_libxml_internal_errors_state_is_restored_after_missing_file(): void {
		$original_state = libxml_use_internal_errors( false );
		libxml_use_internal_errors( $original_state );

		$result = qtxpm_direct_xml_import( '/tmp/qtx-does-not-exist-' . uniqid(), true );
		$this->assertFalse( $result['success'] );

		$state_after = libxml_use_internal_errors( $original_state );
		$this->assertSame( $original_state, $state_after, 'libxml internal-errors state must be restored even when the file is missing.' );
	}

	// ------------------------------------------------------------------
	// Raw (untransformed) multilingual block guard.
	// ------------------------------------------------------------------

	public function test_direct_import_rejects_raw_wxr_with_untransformed_blocks(): void {
		$this->assertFileExists( self::FIXTURE_PATH, 'Fixture WXR file is missing.' );

		// The fixture on disk has NOT been through qtxpm_process_wxr_content()
		// and still contains raw [:xx]/<!--:xx-->/{:xx} blocks.
		$temp_file = $this->writeTempXml( file_get_contents( self::FIXTURE_PATH ) );

		try {
			$result = qtxpm_direct_xml_import( $temp_file, true );

			$this->assertFalse( $result['success'], 'Raw WXR content with untransformed language blocks must be rejected by default.' );
			$this->assertStringContainsString( 'qTranslate', $result['message'] );
			$this->assertSame( 0, $result['imported'] );

			$inserted_posts = qtx_wordpress_stub_get_inserted_posts();
			$this->assertEmpty( $inserted_posts, 'No posts should be imported when the raw-block guard rejects the file.' );
		} finally {
			@unlink( $temp_file );
		}
	}

	public function test_direct_import_allows_raw_wxr_when_allow_raw_is_true(): void {
		$temp_file = $this->writeTempXml( file_get_contents( self::FIXTURE_PATH ) );

		try {
			$result = qtxpm_direct_xml_import( $temp_file, true, true );

			$this->assertTrue( $result['success'], 'Setting $allow_raw = true must bypass the raw-block guard.' );
			$this->assertGreaterThan( 0, $result['imported'] );
		} finally {
			@unlink( $temp_file );
		}
	}

	public function test_direct_import_accepts_transformed_wxr_without_raw_blocks(): void {
		self::loadMigrationEngine();

		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false;
		$loaded = $doc->load( self::FIXTURE_PATH );
		$this->assertTrue( $loaded );

		$processed = qtxpm_process_wxr_content( $doc, array( 'pt', 'en' ), 'pt' );
		$temp_file = $this->writeTempXml( $processed );

		try {
			$result = qtxpm_direct_xml_import( $temp_file, true );

			$this->assertTrue( $result['success'], 'Transformed WXR content (no raw blocks left) must import normally with the guard enabled.' );
			$this->assertGreaterThan( 0, $result['imported'] );
		} finally {
			@unlink( $temp_file );
		}
	}

	public function test_direct_import_does_not_false_positive_on_bracketed_title_without_language_code(): void {
		$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title>Announcement [Draft] Please Review</title>
		<guid isPermaLink="false">announcement-draft</guid>
		<content:encoded><![CDATA[Some plain content, no language blocks here.]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="pt">pt</category>
		<wp:post_id>1</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:00:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[announcement-draft]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[post]]></wp:post_type>
	</item>
</channel>
</rss>
XML;

		$temp_file = $this->writeTempXml( $xml );

		try {
			$result = qtxpm_direct_xml_import( $temp_file, true );

			$this->assertTrue( $result['success'], 'A title containing plain brackets (no ":xx" language marker) must not trigger the raw-block guard.' );
			$this->assertSame( 1, $result['imported'] );
		} finally {
			@unlink( $temp_file );
		}
	}

	private function buildValidTransformedXml(): string {
		return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<item>
		<title>Already Transformed Post</title>
		<guid isPermaLink="false">already-transformed</guid>
		<content:encoded><![CDATA[Plain content without any language blocks.]]></content:encoded>
		<excerpt:encoded><![CDATA[]]></excerpt:encoded>
		<category domain="language" nicename="pt">pt</category>
		<wp:post_id>1</wp:post_id>
		<wp:post_date><![CDATA[2026-03-10 10:00:00]]></wp:post_date>
		<wp:post_date_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_date_gmt>
		<wp:post_modified><![CDATA[2026-03-10 10:00:00]]></wp:post_modified>
		<wp:post_modified_gmt><![CDATA[2026-03-10 13:00:00]]></wp:post_modified_gmt>
		<wp:post_name><![CDATA[already-transformed]]></wp:post_name>
		<wp:status><![CDATA[publish]]></wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type><![CDATA[post]]></wp:post_type>
	</item>
</channel>
</rss>
XML;
	}
}
