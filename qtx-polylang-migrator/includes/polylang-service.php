<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Internal accessor for the per-request Polylang languages cache used by
 * `qtxpm_get_polylang_languages()`.
 *
 * Kept as a single `static`-backed accessor (rather than one static per
 * function) so the cache can be cleared from outside without forcing an
 * immediate recomputation — `qtxpm_reset_polylang_languages_cache()` clears
 * it lazily (the next real call recomputes from whatever state exists at
 * that time), while `qtxpm_get_polylang_languages( true )` recomputes and
 * repopulates it immediately, which is what `qtxpm_ensure_polylang_language()`
 * needs right after it actually provisions a new language.
 *
 * @param string      $mode  'get' returns the cached value (or null), 'set' replaces it, 'clear' invalidates it.
 * @param string[]|null $value New cached value when `$mode === 'set'`.
 * @return string[]|null
 */
function qtxpm_polylang_languages_cache( string $mode, ?array $value = null ): ?array {
	static $cache = null;

	if ( 'set' === $mode ) {
		$cache = $value;
	} elseif ( 'clear' === $mode ) {
		$cache = null;
	}

	return $cache;
}

/**
 * Invalidate the per-request `qtxpm_get_polylang_languages()` cache without
 * recomputing it. Intended for test/reset helpers so the next real call
 * recomputes from whatever state exists at that point, instead of eagerly
 * locking in a snapshot of the current (possibly not-yet-final) state.
 *
 * @return void
 */
function qtxpm_reset_polylang_languages_cache(): void {
	qtxpm_polylang_languages_cache( 'clear' );
}

/**
 * Return available Polylang language slugs.
 *
 * The result is memoized for the duration of the request (a single
 * migration run may call this dozens of times while resolving languages
 * for every WXR item). Pass `$force_refresh = true` to recompute and
 * replace the cached value — used by `qtxpm_ensure_polylang_language()`
 * right after provisioning a new language, so callers immediately see it
 * without waiting for the next uncached call.
 *
 * @param bool $force_refresh Whether to bypass and refresh the memoized value.
 * @return string[]
 */
function qtxpm_get_polylang_languages( bool $force_refresh = false ): array {
	if ( ! $force_refresh ) {
		$cached_languages = qtxpm_polylang_languages_cache( 'get' );
		if ( null !== $cached_languages ) {
			return $cached_languages;
		}
	}

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
		static function ( mixed $language ): string {
			return strtolower( trim( (string) $language ) );
		},
		(array) $languages
	);

	$resolved_languages = array_values( array_unique( array_filter( $languages ) ) );
	qtxpm_polylang_languages_cache( 'set', $resolved_languages );

	return $resolved_languages;
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
					static function ( mixed $language ): string {
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
	qtxpm_get_polylang_languages( true );

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

/**
 * Map a WXR `<category domain="...">` value to the corresponding WordPress taxonomy.
 *
 * Empty domains are treated as `category` for backward compatibility with
 * legacy exports that omit the attribute (mirrors historical migrator
 * behavior, which only ever assigned the `category` taxonomy in that case).
 *
 * @param string $domain WXR category domain attribute.
 * @return string Taxonomy slug, or empty string when the domain is not a migratable taxonomy.
 */
function qtxpm_map_wxr_category_domain_to_taxonomy( string $domain ): string {
	$domain = strtolower( trim( $domain ) );

	if ( '' === $domain ) {
		return 'category';
	}

	$map = array(
		'category' => 'category',
		'post_tag' => 'post_tag',
	);

	return $map[ $domain ] ?? '';
}

/**
 * Return the runtime cache of multilingual term groups created during this
 * request, keyed by group signature and then by language (or `_mono` for
 * language-less terms).
 *
 * @param string $group_key Term group signature.
 * @return array<string, int>
 */
function qtxpm_get_runtime_term_group( string $group_key ): array {
	$groups = $GLOBALS['qtxpm_runtime_term_groups'] ?? array();

	return is_array( $groups[ $group_key ] ?? null ) ? $groups[ $group_key ] : array();
}

/**
 * Remember a term created for a given group/language during this request, so
 * that subsequent posts referencing the same original taxonomy term reuse it
 * instead of creating duplicates.
 *
 * @param string $group_key Term group signature.
 * @param string $language_key Resolved language slug, or `_mono` for language-less terms.
 * @param int    $term_id Created/reused term ID.
 * @return void
 */
function qtxpm_remember_runtime_term_group( string $group_key, string $language_key, int $term_id ): void {
	if ( ! isset( $GLOBALS['qtxpm_runtime_term_groups'] ) || ! is_array( $GLOBALS['qtxpm_runtime_term_groups'] ) ) {
		$GLOBALS['qtxpm_runtime_term_groups'] = array();
	}

	$GLOBALS['qtxpm_runtime_term_groups'][ $group_key ][ $language_key ] = $term_id;
}

/**
 * Find an existing term for the given taxonomy/name/language, or create it.
 *
 * When a term with the same name already exists in the taxonomy, it is
 * reused as long as it has no assigned Polylang language yet (in which case
 * the requested language is assigned to it) or already matches the requested
 * language. This avoids creating duplicate terms across posts that share the
 * same category/tag.
 *
 * @param string $taxonomy Taxonomy slug (`category` or `post_tag`).
 * @param string $name Term display name for this language.
 * @param string $nicename Slug hint carried over from the WXR category element.
 * @param string $language Resolved Polylang language slug (empty for monolingual terms).
 * @return int Term ID, or 0 on failure.
 */
function qtxpm_find_or_create_migrated_term( string $taxonomy, string $name, string $nicename, string $language ): int {
	$name = trim( $name );
	if ( '' === $name || ! function_exists( 'wp_insert_term' ) ) {
		return 0;
	}

	$existing = function_exists( 'get_term_by' ) ? get_term_by( 'name', $name, $taxonomy ) : false;
	if ( is_object( $existing ) && isset( $existing->term_id ) ) {
		$existing_term_id = (int) $existing->term_id;
		$existing_language = function_exists( 'pll_get_term_language' )
			? (string) pll_get_term_language( $existing_term_id, 'slug' )
			: '';

		if ( '' === $existing_language || '' === $language || $existing_language === $language ) {
			if ( '' === $existing_language && '' !== $language && function_exists( 'pll_set_term_language' ) ) {
				pll_set_term_language( $existing_term_id, $language );
			}

			return $existing_term_id;
		}
	}

	$slug_hint = '' !== $nicename ? $nicename : $name;
	$slug = '' !== $language && function_exists( 'sanitize_title' )
		? sanitize_title( $slug_hint . '-' . $language )
		: ( function_exists( 'sanitize_title' ) ? sanitize_title( $slug_hint ) : $slug_hint );

	$inserted = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );

	if ( is_wp_error( $inserted ) ) {
		$existing_term_id = $inserted->get_error_data( 'term_exists' );
		if ( is_numeric( $existing_term_id ) ) {
			$existing_term_id = (int) $existing_term_id;
			if ( '' !== $language && function_exists( 'pll_set_term_language' ) ) {
				$current_language = function_exists( 'pll_get_term_language' )
					? (string) pll_get_term_language( $existing_term_id, 'slug' )
					: '';
				if ( '' === $current_language ) {
					pll_set_term_language( $existing_term_id, $language );
				}
			}

			return $existing_term_id;
		}

		return 0;
	}

	if ( ! is_array( $inserted ) || empty( $inserted['term_id'] ) ) {
		return 0;
	}

	$term_id = (int) $inserted['term_id'];

	if ( '' !== $language && function_exists( 'pll_set_term_language' ) ) {
		pll_set_term_language( $term_id, $language );
	}

	return $term_id;
}

/**
 * Import a single WXR `<category>` element (taxonomy domain, e.g. `category`
 * or `post_tag`) for a migrated post.
 *
 * Legacy qTranslate-XT WXR exports keep taxonomy term names inline in the
 * qTranslate block format (e.g. `[:pt]Noticias[:en]News[:]`) because the
 * `<category>` element is cloned unchanged into every per-language post item
 * produced by the transformer. This function splits that name per language,
 * creates/reuses one WordPress term per language, links the variants as
 * Polylang term translations, and assigns to the post only the term that
 * matches its own language. Monolingual category names create/reuse a single
 * term with no translation group.
 *
 * @param int                   $post_id Migrated post ID.
 * @param SimpleXMLElement      $category WXR `<category>` element.
 * @param string                $post_language Resolved language of the migrated post (qTranslate code, e.g. `pt`/`pb`).
 * @param array<string, array{term_id: int, parent_nicename: string, name: string}> $category_hierarchy Channel-level `<wp:category>` hierarchy map keyed by nicename.
 * @return void
 */
function qtxpm_import_wxr_post_category( int $post_id, SimpleXMLElement $category, string $post_language, array $category_hierarchy = array() ): void {
	if ( $post_id <= 0 ) {
		return;
	}

	$taxonomy = qtxpm_map_wxr_category_domain_to_taxonomy( (string) $category['domain'] );
	if ( '' === $taxonomy ) {
		return;
	}

	$nicename = trim( (string) $category['nicename'] );
	$raw_text = trim( (string) $category );
	if ( '' === $raw_text ) {
		return;
	}

	$resolved_post_language = qtxpm_resolve_polylang_language_code( $post_language );

	$hierarchy_entry = ( '' !== $nicename && isset( $category_hierarchy[ $nicename ] ) ) ? $category_hierarchy[ $nicename ] : array();
	$original_term_id = isset( $hierarchy_entry['term_id'] ) ? (int) $hierarchy_entry['term_id'] : 0;
	$parent_nicename = isset( $hierarchy_entry['parent_nicename'] ) ? (string) $hierarchy_entry['parent_nicename'] : '';
	$original_parent_term_id = ( '' !== $parent_nicename && isset( $category_hierarchy[ $parent_nicename ] ) )
		? (int) $category_hierarchy[ $parent_nicename ]['term_id']
		: 0;

	$group_key = $taxonomy . '|' . ( $original_term_id > 0
		? 'id:' . $original_term_id
		: 'nicename:' . $nicename . '|text:' . md5( $raw_text ) );

	$is_multilingual = qtxpm_is_multilingual_text( $raw_text );
	$names_by_language = $is_multilingual ? qtxpm_split_multilingual_text( $raw_text ) : array();

	if ( ! $is_multilingual || empty( $names_by_language ) ) {
		$names_by_language = array( '_default' => $raw_text );
	}

	$group_term_ids = qtxpm_get_runtime_term_group( $group_key );
	$run_id = qtxpm_get_current_migration_run()['run'];
	$new_language_added = false;

	foreach ( $names_by_language as $language => $name ) {
		$language = ( '_default' === $language ) ? $resolved_post_language : qtxpm_normalize_language_code( (string) $language );
		$name = trim( (string) $name );
		if ( '' === $name ) {
			continue;
		}

		$resolved_language = '' !== $language ? qtxpm_resolve_polylang_language_code( $language ) : '';
		$language_key = '' !== $resolved_language ? $resolved_language : '_mono';

		if ( isset( $group_term_ids[ $language_key ] ) ) {
			continue;
		}

		$term_id = qtxpm_find_or_create_migrated_term( $taxonomy, $name, $nicename, $resolved_language );
		if ( $term_id <= 0 ) {
			continue;
		}

		if ( $original_term_id > 0 && function_exists( 'update_term_meta' ) ) {
			update_term_meta( $term_id, '_pll_migration_original_id', $original_term_id );
			update_term_meta( $term_id, '_pll_migration_parent_id', $original_parent_term_id );
			if ( '' !== $resolved_language ) {
				update_term_meta( $term_id, '_pll_migration_lang', $resolved_language );
			}
			if ( '' !== $run_id ) {
				update_term_meta( $term_id, '_pll_migration_run', $run_id );
			}
		}

		$group_term_ids[ $language_key ] = $term_id;
		qtxpm_remember_runtime_term_group( $group_key, $language_key, $term_id );
		$new_language_added = true;
	}

	// Only (re-)link translations when this call actually contributed a new
	// language variant to the group; otherwise a later post referencing the
	// same already-fully-resolved category would re-save the same
	// translation group on every occurrence.
	$linkable_term_ids = array_diff_key( $group_term_ids, array( '_mono' => 0 ) );
	if ( $new_language_added && count( $linkable_term_ids ) > 1 && function_exists( 'pll_save_term_translations' ) ) {
		pll_save_term_translations( $linkable_term_ids );
	}

	$post_language_key = '' !== $resolved_post_language ? $resolved_post_language : '_mono';
	$term_id_for_post = $group_term_ids[ $post_language_key ] ?? ( reset( $group_term_ids ) ?: 0 );

	if ( $term_id_for_post > 0 && function_exists( 'wp_set_object_terms' ) ) {
		wp_set_object_terms( $post_id, array( $term_id_for_post ), $taxonomy, true );
	}
}
