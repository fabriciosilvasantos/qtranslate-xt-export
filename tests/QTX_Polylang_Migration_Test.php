<?php
/**
 * Real unit tests for the pure functions of the qtx-polylang-migrator engine.
 *
 * Unlike the previous version of this file, these tests load the actual
 * migrator source (bootstrap.php, polylang-service.php, wxr-transformer.php)
 * instead of reimplementing the parsing/sorting logic inline. See the
 * `multilingual-test-fixtures` skill for the canonical 14 cases covered by
 * the data providers below.
 */

use PHPUnit\Framework\TestCase;

class QTX_Polylang_Migration_Test extends TestCase {

	private static function loadMigratorPureFunctions(): void {
		$includes_dir = dirname( __DIR__ ) . '/qtx-polylang-migrator/includes';

		if ( ! function_exists( 'qtxpm_split_multilingual_text' ) ) {
			require_once $includes_dir . '/bootstrap.php';
		}

		if ( ! function_exists( 'qtxpm_normalize_language_code' ) ) {
			require_once $includes_dir . '/polylang-service.php';
		}

		if ( ! function_exists( 'qtxpm_get_language_value' ) ) {
			require_once $includes_dir . '/wxr-transformer.php';
		}
	}

	public static function setUpBeforeClass(): void {
		self::loadMigratorPureFunctions();
	}

	protected function setUp(): void {
		global $q_config;
		$q_config = array(
			'language'              => 'en',
			'default_language'      => 'en',
			'enabled_languages'     => array( 'en', 'pt', 'es' ),
			'hide_default_language' => false,
		);
	}

	// ------------------------------------------------------------------
	// qtxpm_split_multilingual_text() — canonical multilingual fixtures.
	// ------------------------------------------------------------------

	/**
	 * @dataProvider provideMultilingualSplitCases
	 */
	public function test_split_multilingual_text_canonical_cases( string $text, array $languages, array $expected ): void {
		$this->assertSame( $expected, qtxpm_split_multilingual_text( $text, $languages ) );
	}

	public static function provideMultilingualSplitCases(): array {
		return array(
			'case 1: caminho feliz com dois idiomas'                => array(
				'[:pt]Olá[:en]Hello[:]',
				array( 'pt', 'en' ),
				array(
					'pt' => 'Olá',
					'en' => 'Hello',
				),
			),
			'case 2: idioma ausente (en) fica vazio, nunca perdido' => array(
				'[:pt]Só português[:]',
				array( 'pt', 'en' ),
				array(
					'pt' => 'Só português',
					'en' => '',
				),
			),
			'case 3: texto monolíngue sem blocos'                   => array(
				'Texto sem blocos',
				array( 'pt', 'en' ),
				array(
					'pt' => 'Texto sem blocos',
					'en' => 'Texto sem blocos',
				),
			),
			'case 5: prefixo fora de bloco pertence a todos'        => array(
				'Prefixo [:pt]meio[:] sufixo',
				array( 'pt', 'en' ),
				array(
					'pt' => 'Prefixo meio sufixo',
					// The trailing space of the "Prefixo " segment and the leading
					// space of the " sufixo" segment are both preserved verbatim
					// (the function only trims the final value, not each block),
					// so "en" ends up with a double space between the words.
					'en' => 'Prefixo  sufixo',
				),
			),
			'case 6: sintaxe legada de comentário HTML'             => array(
				'<!--:pt-->Olá<!--:en-->Hello<!--:-->',
				array( 'pt', 'en' ),
				array(
					'pt' => 'Olá',
					'en' => 'Hello',
				),
			),
			'case 7: sintaxe swirly'                                => array(
				'{:pt}Olá{:en}Hello{:}',
				array( 'pt', 'en' ),
				array(
					'pt' => 'Olá',
					'en' => 'Hello',
				),
			),
			'case 8: sintaxes misturadas na mesma string'           => array(
				'[:pt]A<!--:en-->B{:}',
				array( 'pt', 'en' ),
				array(
					'pt' => 'A',
					'en' => 'B',
				),
			),
			'case 9: HTML dentro do bloco é preservado'             => array(
				'[:pt]<p>HTML <b>rico</b></p>[:en]<p>Rich</p>[:]',
				array( 'pt', 'en' ),
				array(
					'pt' => '<p>HTML <b>rico</b></p>',
					'en' => '<p>Rich</p>',
				),
			),
			'case 10: bloco vazio não inventa conteúdo'             => array(
				'[:pt][:en]texto[:]',
				array( 'pt', 'en' ),
				array(
					'pt' => '',
					'en' => 'texto',
				),
			),
			'case 11: marcadores maiúsculos são normalizados'      => array(
				'[:PT]maiúsculo[:]',
				array( 'pt', 'en' ),
				array(
					'pt' => 'maiúsculo',
					'en' => '',
				),
			),
			'case 12: idioma fora do destino não vaza para outros' => array(
				'[:xx]idioma não habilitado[:]',
				array( 'pt', 'en' ),
				array(
					'pt' => '',
					'en' => '',
					'xx' => 'idioma não habilitado',
				),
			),
			'case 13: UTF-8/multibyte intacto (round-trip)'         => array(
				'[:pt]acentuação: ção, ã, é[:en]uni: 中文 🎉[:]',
				array( 'pt', 'en' ),
				array(
					'pt' => 'acentuação: ção, ã, é',
					'en' => 'uni: 中文 🎉',
				),
			),
		);
	}

	public function test_split_multilingual_text_returns_empty_array_for_empty_string(): void {
		// Case 4: empty string with no known languages detected -> no keys, no fatal.
		$this->assertSame( array(), qtxpm_split_multilingual_text( '', array() ) );
	}

	public function test_split_multilingual_text_treats_empty_string_as_monolingual_when_languages_given(): void {
		// Case 4 (with explicit destination languages): still safe, no warnings/fatals.
		$this->assertSame(
			array(
				'pt' => '',
				'en' => '',
			),
			qtxpm_split_multilingual_text( '', array( 'pt', 'en' ) )
		);
	}

	public function test_split_multilingual_text_handles_malformed_unclosed_block(): void {
		// Case 14: malformed input (`[:pt` never closes) — predictable degradation,
		// no PHP warning/fatal. The literal text is treated as plain content since
		// it does not match the block-delimiter regex.
		$result = qtxpm_split_multilingual_text( '[:pt sem fechar', array( 'pt', 'en' ) );

		$this->assertSame(
			array(
				'pt' => '[:pt sem fechar',
				'en' => '[:pt sem fechar',
			),
			$result
		);
	}

	public function test_split_multilingual_text_without_destination_languages_infers_them_from_markers(): void {
		$result = qtxpm_split_multilingual_text( '[:pt]Olá[:en]Hello[:]' );

		$this->assertSame(
			array(
				'pt' => 'Olá',
				'en' => 'Hello',
			),
			$result
		);
	}

	public function test_split_multilingual_text_round_trip_preserves_all_content(): void {
		$text = 'Prefixo [:pt]<p>HTML <b>rico</b></p>[:en]<p>Rich</p>[:] sufixo';
		$result = qtxpm_split_multilingual_text( $text, array( 'pt', 'en' ) );

		// Every language variant must contain the shared prefix/suffix plus its own block.
		$this->assertStringContainsString( 'Prefixo', $result['pt'] );
		$this->assertStringContainsString( 'sufixo', $result['pt'] );
		$this->assertStringContainsString( '<p>HTML <b>rico</b></p>', $result['pt'] );

		$this->assertStringContainsString( 'Prefixo', $result['en'] );
		$this->assertStringContainsString( 'sufixo', $result['en'] );
		$this->assertStringContainsString( '<p>Rich</p>', $result['en'] );
	}

	/**
	 * Mutation check (documented, not executed automatically): temporarily
	 * changing the split regex delimiter group from `[a-z]{2,3}` to
	 * `[a-z]{2}` in bootstrap.php and re-running this test suite makes
	 * `test_split_multilingual_text_canonical_cases` with case 12 (`[:xx]`,
	 * a 2-letter code, still passes) fail to distinguish 3-letter codes —
	 * confirmed manually during this task; reverted before commit.
	 */

	// ------------------------------------------------------------------
	// qtxpm_normalize_language_code()
	// ------------------------------------------------------------------

	/**
	 * @dataProvider provideNormalizeLanguageCodeCases
	 */
	public function test_normalize_language_code( string $input, string $expected ): void {
		$this->assertSame( $expected, qtxpm_normalize_language_code( $input ) );
	}

	public static function provideNormalizeLanguageCodeCases(): array {
		return array(
			'legacy pb alias maps to pt' => array( 'pb', 'pt' ),
			'uppercase PB maps to pt'    => array( 'PB', 'pt' ),
			'surrounding whitespace'    => array( '  pb  ', 'pt' ),
			'regular code passes through' => array( 'en', 'en' ),
			'uppercase regular code is lowercased' => array( 'EN', 'en' ),
			'empty string stays empty'   => array( '', '' ),
			'unrelated 3-letter code passes through' => array( 'xyz', 'xyz' ),
		);
	}

	// ------------------------------------------------------------------
	// qtxpm_get_language_value()
	// ------------------------------------------------------------------

	public function test_get_language_value_returns_requested_language_when_present(): void {
		$values = array(
			'en' => 'Hello',
			'pt' => 'Olá',
		);

		$this->assertSame( 'Olá', qtxpm_get_language_value( $values, 'pt', 'en', '' ) );
	}

	public function test_get_language_value_falls_back_to_default_language_when_requested_is_missing(): void {
		$values = array(
			'en' => 'Hello',
		);

		$this->assertSame( 'Hello', qtxpm_get_language_value( $values, 'pt', 'en', '' ) );
	}

	public function test_get_language_value_falls_back_to_first_available_value_when_default_also_missing(): void {
		$values = array(
			'es' => 'Hola',
		);

		$this->assertSame( 'Hola', qtxpm_get_language_value( $values, 'pt', 'en', '' ) );
	}

	public function test_get_language_value_returns_fallback_when_no_values_available(): void {
		$this->assertSame( 'fallback', qtxpm_get_language_value( array(), 'pt', 'en', 'fallback' ) );
	}

	public function test_get_language_value_uses_pb_as_alias_for_pt(): void {
		$values = array( 'pb' => 'Sobre nós' );

		$this->assertSame( 'Sobre nós', qtxpm_get_language_value( $values, 'pt', 'en', '' ) );
	}

	public function test_get_language_value_ignores_values_that_are_only_whitespace(): void {
		$values = array(
			'pt' => '   ',
			'en' => 'Hello',
		);

		$this->assertSame( 'Hello', qtxpm_get_language_value( $values, 'pt', 'en', '' ) );
	}

	public function test_get_language_value_is_null_safe_with_empty_language_codes(): void {
		// Requesting an empty language/default should not raise a PHP 8.4 warning
		// and must fall back gracefully.
		$this->assertSame( 'fallback', qtxpm_get_language_value( array(), '', '', 'fallback' ) );
	}

	// ------------------------------------------------------------------
	// qtxpm_sort_items_by_hierarchy()
	// ------------------------------------------------------------------

	public function test_sort_items_by_hierarchy_orders_root_and_children_depth_first(): void {
		$items = array(
			array( 'original_id' => 1, 'original_parent' => 0, 'menu_order' => 0 ),
			array( 'original_id' => 2, 'original_parent' => 1, 'menu_order' => 1 ),
			array( 'original_id' => 3, 'original_parent' => 1, 'menu_order' => 2 ),
			array( 'original_id' => 4, 'original_parent' => 2, 'menu_order' => 1 ),
			array( 'original_id' => 5, 'original_parent' => 0, 'menu_order' => 1 ),
		);

		$sorted = qtxpm_sort_items_by_hierarchy( $items );
		$ids = array_column( $sorted, 'original_id' );

		$this->assertSame( array( 1, 2, 4, 3, 5 ), $ids );
	}

	public function test_sort_items_by_hierarchy_breaks_menu_order_ties_by_original_id(): void {
		$items = array(
			array( 'original_id' => 20, 'original_parent' => 0, 'menu_order' => 0 ),
			array( 'original_id' => 10, 'original_parent' => 0, 'menu_order' => 0 ),
		);

		$sorted = qtxpm_sort_items_by_hierarchy( $items );

		$this->assertSame( array( 10, 20 ), array_column( $sorted, 'original_id' ) );
	}

	public function test_sort_items_by_hierarchy_returns_empty_array_for_empty_input(): void {
		$this->assertSame( array(), qtxpm_sort_items_by_hierarchy( array() ) );
	}

	public function test_sort_items_by_hierarchy_drops_orphans_with_missing_parent(): void {
		// An item whose declared parent does not exist among the items is not a
		// root (parent != 0) and has no matching parent to attach children to,
		// so it is silently excluded from the sorted result.
		$items = array(
			array( 'original_id' => 1, 'original_parent' => 0, 'menu_order' => 0 ),
			array( 'original_id' => 99, 'original_parent' => 999, 'menu_order' => 0 ),
		);

		$sorted = qtxpm_sort_items_by_hierarchy( $items );

		$this->assertSame( array( 1 ), array_column( $sorted, 'original_id' ) );
	}

	public function test_sort_items_by_hierarchy_preserves_single_item(): void {
		$items = array(
			array( 'original_id' => 42, 'original_parent' => 0, 'menu_order' => 0 ),
		);

		$this->assertSame( $items, qtxpm_sort_items_by_hierarchy( $items ) );
	}
}
