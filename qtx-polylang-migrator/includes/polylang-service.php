<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return available Polylang language slugs.
 *
 * @return string[]
 */
function qtxpm_get_polylang_languages(): array {
	$languages = array();

	if ( function_exists( 'pll_languages_list' ) ) {
		$language_list = pll_languages_list(
			array(
				'fields' => 'slug',
			)
		);

		if ( is_array( $language_list ) ) {
			$languages = $language_list;
		}
	}

	if ( empty( $languages ) && function_exists( 'PLL' ) ) {
		$polylang = PLL();
		if ( is_object( $polylang ) && isset( $polylang->model ) && method_exists( $polylang->model, 'get_languages_list' ) ) {
			foreach ( (array) $polylang->model->get_languages_list() as $language ) {
				if ( is_object( $language ) && isset( $language->slug ) ) {
					$languages[] = $language->slug;
				} elseif ( is_array( $language ) && isset( $language['slug'] ) ) {
					$languages[] = $language['slug'];
				}
			}
		}
	}

	if ( function_exists( 'taxonomy_exists' ) && taxonomy_exists( 'language' ) && function_exists( 'get_terms' ) ) {
		$term_slugs = get_terms(
			array(
				'taxonomy'   => 'language',
				'hide_empty' => false,
				'fields'     => 'slugs',
			)
		);

		if ( is_array( $term_slugs ) ) {
			$languages = array_merge( $languages, $term_slugs );
		}
	}

	$languages = array_merge( $languages, qtxpm_get_runtime_polylang_languages() );

	$languages = array_map(
		static function ( $language ): string {
			return strtolower( trim( (string) $language ) );
		},
		(array) $languages
	);

	return array_values( array_unique( array_filter( $languages ) ) );
}

/**
 * Return the per-request registry of languages provisioned by the migrator.
 *
 * @return string[]
 */
function qtxpm_get_runtime_polylang_languages(): array {
	$languages = $GLOBALS['qtxpm_runtime_polylang_languages'] ?? array();

	return array_values(
		array_unique(
			array_filter(
				array_map(
					static function ( $language ): string {
						return strtolower( trim( (string) $language ) );
					},
					(array) $languages
				)
			)
		)
	);
}

/**
 * Store a language slug in the per-request migrator registry.
 *
 * @param string $language_code Language slug.
 * @return void
 */
function qtxpm_remember_runtime_polylang_language( string $language_code ): void {
	$language_code = qtxpm_normalize_language_code( $language_code );
	if ( '' === $language_code ) {
		return;
	}

	$languages = qtxpm_get_runtime_polylang_languages();
	$languages[] = $language_code;
	$GLOBALS['qtxpm_runtime_polylang_languages'] = array_values( array_unique( $languages ) );
}

/**
 * Normalize a migration language code to its canonical Polylang slug.
 *
 * @param string $language_code Language slug from migration metadata.
 * @return string
 */
function qtxpm_normalize_language_code( string $language_code ): string {
	$language_code = strtolower( trim( $language_code ) );

	if ( 'pb' === $language_code ) {
		return 'pt';
	}

	return $language_code;
}

/**
 * Ensure a language exists in Polylang.
 *
 * @param string $language_code Language slug.
 * @param bool   $set_default Whether the language should become the default.
 * @return string
 */
function qtxpm_ensure_polylang_language( string $language_code, bool $set_default = false ): string {
	$language_code = qtxpm_normalize_language_code( $language_code );
	if ( '' === $language_code ) {
		return '';
	}

	$available_languages = qtxpm_get_polylang_languages();
	if ( in_array( $language_code, $available_languages, true ) ) {
		qtxpm_remember_runtime_polylang_language( $language_code );
		return $language_code;
	}

	if ( ! function_exists( 'PLL' ) ) {
		return '';
	}

	$catalog = qtxpm_get_polylang_language_catalog();
	if ( empty( $catalog[ $language_code ] ) ) {
		return '';
	}

	$polylang = PLL();
	if ( ! is_object( $polylang ) || ! isset( $polylang->model ) || ! is_callable( array( $polylang->model, 'add_language' ) ) ) {
		return '';
	}

	$language_data = $catalog[ $language_code ];
	$created_language = $polylang->model->add_language(
		array(
			'locale'         => $language_data['locale'],
			'slug'           => $language_data['slug'],
			'name'           => $language_data['name'],
			'flag'           => $language_data['flag'],
			'rtl'            => $language_data['rtl'],
			'no_default_cat' => true,
		)
	);

	if ( is_wp_error( $created_language ) ) {
		return '';
	}

	if ( $set_default ) {
		$options = get_option( 'polylang', array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$options['default_lang'] = $language_code;
		update_option( 'polylang', $options );
	}

	qtxpm_remember_runtime_polylang_language( $language_code );

	return $language_code;
}

/**
 * Ensure multiple languages exist in Polylang.
 *
 * @param string[] $languages Languages to ensure.
 * @param string   $default_language Default language slug.
 * @return string[]
 */
function qtxpm_ensure_polylang_languages( array $languages, string $default_language = '' ): array {
	$created_languages = array();
	$default_language = qtxpm_normalize_language_code( $default_language );

	foreach ( $languages as $language ) {
		$language = qtxpm_normalize_language_code( (string) $language );
		if ( '' === $language ) {
			continue;
		}

		$created_language = qtxpm_ensure_polylang_language( $language, $default_language === $language );
		if ( '' !== $created_language ) {
			$created_languages[] = $created_language;
		}
	}

	return array_values( array_unique( $created_languages ) );
}

/**
 * Resolve a migration language code to an existing Polylang language slug.
 *
 * @param string $language_code Language slug from migration metadata.
 * @return string
 */
function qtxpm_resolve_polylang_language_code( string $language_code ): string {
	$language_code = qtxpm_normalize_language_code( $language_code );
	if ( '' === $language_code ) {
		return '';
	}

	qtxpm_ensure_polylang_language( $language_code );

	$available_languages = qtxpm_get_polylang_languages();
	if ( empty( $available_languages ) ) {
		return '';
	}

	foreach ( qtxpm_get_language_aliases( $language_code ) as $candidate ) {
		if ( in_array( $candidate, $available_languages, true ) ) {
			return $candidate;
		}
	}

	return '';
}

/**
 * Check if a Polylang language exists.
 *
 * @param string $language_code Language slug.
 * @return bool
 */
function qtxpm_polylang_language_exists( string $language_code ): bool {
	return qtxpm_resolve_polylang_language_code( $language_code ) !== '';
}

/**
 * Assign a Polylang language to a post.
 *
 * @param int    $post_id Post ID.
 * @param string $language_code Language slug.
 * @return bool
 */
function qtxpm_assign_post_language( int $post_id, string $language_code ): bool {
	if ( $post_id <= 0 || ! function_exists( 'pll_set_post_language' ) ) {
		return false;
	}

	$resolved_language = qtxpm_resolve_polylang_language_code( $language_code );
	if ( '' === $resolved_language ) {
		return false;
	}

	if ( function_exists( 'get_post_type' ) ) {
		$post_type = (string) get_post_type( $post_id );
		if ( ! qtxpm_is_translated_post_type( $post_type ) ) {
			return false;
		}
	}

	if ( function_exists( 'pll_get_post_language' ) ) {
		$current_language = (string) pll_get_post_language( $post_id, 'slug' );
		if ( $current_language === $resolved_language ) {
			return true;
		}
	}

	if ( ! qtxpm_polylang_language_exists( $resolved_language ) ) {
		return false;
	}

	pll_set_post_language( $post_id, $resolved_language );

	return true;
}

/**
 * Check whether a post type should participate in Polylang translation groups.
 *
 * @param string $post_type Post type.
 * @return bool
 */
function qtxpm_is_translated_post_type( string $post_type ): bool {
	$post_type = trim( $post_type );
	if ( '' === $post_type || 'attachment' === $post_type ) {
		return false;
	}

	if ( function_exists( 'pll_is_translated_post_type' ) ) {
		return pll_is_translated_post_type( $post_type );
	}

	return in_array( $post_type, array( 'post', 'page' ), true );
}
