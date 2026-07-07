<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'qtxpm_is_multilingual_text' ) ) {
	/**
	 * Detect whether a text still uses a qTranslate multilingual marker format.
	 *
	 * @param string|null $text Text to inspect.
	 * @return bool
	 */
	function qtxpm_is_multilingual_text( ?string $text ): bool {
		return null !== $text && preg_match( '/<!--:[a-z]{2,3}-->|\\[:[a-z]{2,3}\\]|\\{:[a-z]{2,3}\\}/i', $text ) === 1;
	}
}

if ( ! function_exists( 'qtxpm_split_multilingual_text' ) ) {
	/**
	 * Split legacy qTranslate blocks using optional destination languages.
	 *
	 * @param string   $text qTranslate-formatted string.
	 * @param string[] $languages Known destination languages.
	 * @return array<string, string>
	 */
	function qtxpm_split_multilingual_text( string $text, array $languages = array() ): array {
		$split_regex = '#(<!--:[a-z]{2,3}-->|<!--:-->|\\[:[a-z]{2,3}\\]|\\[:\\]|\\{:[a-z]{2,3}\\}|\\{:\\})#ism';
		$blocks = preg_split( $split_regex, $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );

		if ( false === $blocks ) {
			$blocks = array( $text );
		}

		$known_languages = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( mixed $language ): string {
							return strtolower( trim( (string) $language ) );
						},
						$languages
					)
				)
			)
		);

		if ( empty( $known_languages ) ) {
			foreach ( $blocks as $block ) {
				if ( preg_match( '#^(?:<!--:|\\[:|\\{:)([a-z]{2,3})(?:-->|\\]|\\})$#i', $block, $matches ) === 1 ) {
					$known_languages[] = strtolower( $matches[1] );
				}
			}
			$known_languages = array_values( array_unique( $known_languages ) );
		}

		if ( empty( $known_languages ) ) {
			return array();
		}

		$result = array_fill_keys( $known_languages, '' );
		$current_language = '';

		foreach ( $blocks as $block ) {
			if ( preg_match( '#^<!--:([a-z]{2,3})-->$#i', $block, $matches ) === 1 ) {
				$current_language = strtolower( $matches[1] );
				if ( ! isset( $result[ $current_language ] ) ) {
					$result[ $current_language ] = '';
				}
				continue;
			}

			if ( preg_match( '#^\\[:([a-z]{2,3})\\]$#i', $block, $matches ) === 1 ) {
				$current_language = strtolower( $matches[1] );
				if ( ! isset( $result[ $current_language ] ) ) {
					$result[ $current_language ] = '';
				}
				continue;
			}

			if ( preg_match( '#^\\{:([a-z]{2,3})\\}$#i', $block, $matches ) === 1 ) {
				$current_language = strtolower( $matches[1] );
				if ( ! isset( $result[ $current_language ] ) ) {
					$result[ $current_language ] = '';
				}
				continue;
			}

			if ( in_array( $block, array( '<!--:-->', '[:]', '{:}' ), true ) ) {
				$current_language = '';
				continue;
			}

			if ( '' !== $current_language ) {
				$result[ $current_language ] .= $block;
				continue;
			}

			foreach ( array_keys( $result ) as $language ) {
				$result[ $language ] .= $block;
			}
		}

		foreach ( $result as $language => $value ) {
			$result[ $language ] = trim( $value );
		}

		return $result;
	}
}

if ( ! function_exists( 'qtxpm_get_sorted_languages' ) ) {
	/**
	 * Return the fallback language list for the migrator when Polylang has not
	 * been configured yet.
	 *
	 * @param bool $reverse Whether to reverse the language order.
	 * @return string[]
	 */
	function qtxpm_get_sorted_languages( bool $reverse = false ): array {
		$languages = array( 'pt', 'en' );

		if ( $reverse ) {
			return array_reverse( $languages );
		}

		return $languages;
	}
}

/**
 * Return the standalone migration page slug.
 *
 * @return string
 */
function qtxpm_get_migration_page_slug(): string {
	return defined( 'QTXPM_MIGRATION_PAGE_SLUG' ) ? (string) QTXPM_MIGRATION_PAGE_SLUG : 'qtx-polylang-migrator';
}

/**
 * Return the transient key for the standalone migration flow.
 *
 * @param string $suffix Transient suffix.
 * @return string
 */
function qtxpm_get_migration_transient_key( string $suffix ): string {
	return defined( 'QTXPM_MIGRATION_TRANSIENT_PREFIX' ) ? (string) QTXPM_MIGRATION_TRANSIENT_PREFIX . $suffix : 'qtxpm_' . $suffix;
}

/**
 * Return the option name that stores the current migration run context.
 *
 * @return string
 */
function qtxpm_get_migration_run_option_name(): string {
	return 'qtxpm_current_migration_run';
}

/**
 * Generate a unique identifier for a migration run.
 *
 * @return string
 */
function qtxpm_generate_migration_run_id(): string {
	return uniqid( 'qtxpm-', true );
}

/**
 * Persist the current migration run context (run ID and source key).
 *
 * The context scopes the hierarchy/connection queries to the latest import
 * and prevents `_pll_migration_*` metadata from previous migrations of other
 * sites from contaminating a new run.
 *
 * @param string $run_id Unique run identifier.
 * @param string $source_key Normalized identifier of the source site.
 * @return void
 */
function qtxpm_set_current_migration_run( string $run_id, string $source_key ): void {
	update_option(
		qtxpm_get_migration_run_option_name(),
		array(
			'run'    => $run_id,
			'source' => $source_key,
		),
		false
	);
}

/**
 * Return the current migration run context.
 *
 * @return array{run: string, source: string}
 */
function qtxpm_get_current_migration_run(): array {
	$value = get_option( qtxpm_get_migration_run_option_name(), array() );

	return array(
		'run'    => is_array( $value ) && isset( $value['run'] ) ? (string) $value['run'] : '',
		'source' => is_array( $value ) && isset( $value['source'] ) ? (string) $value['source'] : '',
	);
}

/**
 * Build an SQL INNER JOIN fragment scoping posts (aliased `p`) to the current run.
 *
 * Returns an empty string when no run is recorded, preserving the legacy
 * (unscoped) behavior for migrations imported before run tracking existed.
 *
 * @return string
 */
function qtxpm_get_migration_run_scope_join(): string {
	global $wpdb;

	$run_id = qtxpm_get_current_migration_run()['run'];
	if ( '' === $run_id || ! isset( $wpdb ) ) {
		return '';
	}

	return $wpdb->prepare(
		" INNER JOIN {$wpdb->postmeta} pm_run ON p.ID = pm_run.post_id AND pm_run.meta_key = '_pll_migration_run' AND pm_run.meta_value = %s",
		$run_id
	);
}

/**
 * Build an SQL INNER JOIN fragment scoping posts (aliased `p`) to the current source site.
 *
 * Used by deduplication: duplicates are only meaningful among posts that came
 * from the same source site (original IDs collide across different sites).
 * Returns an empty string when no source is recorded (legacy behavior).
 *
 * @return string
 */
function qtxpm_get_migration_source_scope_join(): string {
	global $wpdb;

	$source_key = qtxpm_get_current_migration_run()['source'];
	if ( '' === $source_key || ! isset( $wpdb ) ) {
		return '';
	}

	return $wpdb->prepare(
		" INNER JOIN {$wpdb->postmeta} pm_source ON p.ID = pm_source.post_id AND pm_source.meta_key = '_pll_migration_source' AND pm_source.meta_value = %s",
		$source_key
	);
}

/**
 * Extract a normalized source-site key from a loaded WXR document.
 *
 * @param SimpleXMLElement $xml Loaded WXR document.
 * @return string
 */
function qtxpm_get_wxr_source_key( SimpleXMLElement $xml ): string {
	$source = '';

	if ( isset( $xml->channel->link ) ) {
		$source = (string) $xml->channel->link;
	}

	if ( '' === trim( $source ) && isset( $xml->channel->title ) ) {
		$source = (string) $xml->channel->title;
	}

	return strtolower( untrailingslashit( trim( $source ) ) );
}

/**
 * Return the standalone migration menu/page labels.
 *
 * @return array{page_title: string, menu_title: string}
 */
function qtxpm_get_migration_labels(): array {
	if ( defined( 'QTXPM_MIGRATION_PAGE_TITLE' ) && defined( 'QTXPM_MIGRATION_MENU_TITLE' ) ) {
		return array(
			'page_title' => __( (string) QTXPM_MIGRATION_PAGE_TITLE, 'qtx-polylang-migrator' ),
			'menu_title' => __( (string) QTXPM_MIGRATION_MENU_TITLE, 'qtx-polylang-migrator' ),
		);
	}

	return array(
		'page_title' => __( 'qTranslate -> Polylang Migrator', 'qtx-polylang-migrator' ),
		'menu_title' => __( 'qTranslate Migrator', 'qtx-polylang-migrator' ),
	);
}

/**
 * Return a normalized language catalog from Polylang.
 *
 * @return array<string, array{slug: string, locale: string, name: string, flag: string, rtl: bool}>
 */
function qtxpm_get_polylang_language_catalog(): array {
	static $catalog = null;

	if ( null !== $catalog ) {
		return $catalog;
	}

	$catalog = array(
		'pt' => array(
			'slug'   => 'pt',
			'locale' => 'pt_BR',
			'name'   => 'Português',
			'flag'   => 'br',
			'rtl'    => false,
		),
		'en' => array(
			'slug'   => 'en',
			'locale' => 'en_US',
			'name'   => 'English',
			'flag'   => 'us',
			'rtl'    => false,
		),
		'de' => array(
			'slug'   => 'de',
			'locale' => 'de_DE',
			'name'   => 'Deutsch',
			'flag'   => 'de',
			'rtl'    => false,
		),
		'es' => array(
			'slug'   => 'es',
			'locale' => 'es_ES',
			'name'   => 'Español',
			'flag'   => 'es',
			'rtl'    => false,
		),
		'fr' => array(
			'slug'   => 'fr',
			'locale' => 'fr_FR',
			'name'   => 'Français',
			'flag'   => 'fr',
			'rtl'    => false,
		),
	);

	if ( defined( 'POLYLANG_DIR' ) ) {
		$languages_file = trailingslashit( POLYLANG_DIR ) . 'src/settings/languages.php';
		if ( file_exists( $languages_file ) ) {
			$polylang_languages = include $languages_file;
			if ( is_array( $polylang_languages ) ) {
				foreach ( $polylang_languages as $locale => $language_data ) {
					if ( empty( $language_data['code'] ) ) {
						continue;
					}

					$slug = strtolower( trim( (string) $language_data['code'] ) );
					if ( '' === $slug ) {
						continue;
					}

					$catalog[ $slug ] = array(
						'slug'   => $slug,
						'locale' => (string) $locale,
						'name'   => (string) ( $language_data['name'] ?? strtoupper( $slug ) ),
						'flag'   => (string) ( $language_data['flag'] ?? $slug ),
						'rtl'    => isset( $language_data['dir'] ) && 'rtl' === $language_data['dir'],
					);
				}
			}
		}
	}

	return $catalog;
}
