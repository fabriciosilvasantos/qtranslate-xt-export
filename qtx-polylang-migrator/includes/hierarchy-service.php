<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rebuild hierarchy for imported content.
 *
 * @return array{success: bool, message: string, details: array<int, string>}
 */
function qtxpm_rebuild_hierarchy_process(): array {
	global $wpdb;

	$result = array(
		'success' => false,
		'message' => '',
		'details' => array(),
	);

	try {
		$run_scope_join = qtxpm_get_migration_run_scope_join();

		$posts = $wpdb->get_results(
			"SELECT p.ID, p.post_type, p.menu_order,
				pm_original.meta_value as original_id,
				pm_parent.meta_value as original_parent_id,
				pm_lang.meta_value as lang
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_original ON p.ID = pm_original.post_id AND pm_original.meta_key = '_pll_migration_original_id'{$run_scope_join}
			LEFT JOIN {$wpdb->postmeta} pm_parent ON p.ID = pm_parent.post_id AND pm_parent.meta_key = '_pll_migration_parent_id'
			LEFT JOIN {$wpdb->postmeta} pm_lang ON p.ID = pm_lang.post_id AND pm_lang.meta_key = '_pll_migration_lang'
			WHERE pm_original.meta_value IS NOT NULL
			AND pm_original.meta_value != '0'
			ORDER BY p.post_type, p.menu_order, p.ID"
		);

		if ( empty( $posts ) ) {
			throw new Exception( 'Nenhum post com metadados de migração encontrado. Verifique se a importação foi realizada corretamente.' );
		}

		$original_to_new = qtxpm_build_original_post_language_map( $posts );
		foreach ( $posts as $post ) {
			$lang_suffix = ! empty( $post->lang ) ? ' [' . (string) $post->lang . ']' : '';
			$result['details'][] = "Mapeado: ID Original {$post->original_id}{$lang_suffix} → Novo ID {$post->ID} ({$post->post_type})";
		}

		$hierarchy_posts = $wpdb->get_results(
			"SELECT p.ID, p.post_type,
				pm_original.meta_value as original_id,
				pm_parent.meta_value as original_parent_id,
				pm_lang.meta_value as lang
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_original ON p.ID = pm_original.post_id AND pm_original.meta_key = '_pll_migration_original_id'{$run_scope_join}
			INNER JOIN {$wpdb->postmeta} pm_parent ON p.ID = pm_parent.post_id AND pm_parent.meta_key = '_pll_migration_parent_id'
			LEFT JOIN {$wpdb->postmeta} pm_lang ON p.ID = pm_lang.post_id AND pm_lang.meta_key = '_pll_migration_lang'
			WHERE pm_parent.meta_value IS NOT NULL
			AND pm_parent.meta_value != '0'
			AND p.post_type IN ('page', 'attachment')
			ORDER BY p.post_type"
		);

		$updated_count = 0;
		$hierarchy_errors = array();

		foreach ( $hierarchy_posts as $post ) {
			$original_parent_id = (int) $post->original_parent_id;
			$lang = isset( $post->lang ) ? (string) $post->lang : '';
			$new_parent_id = qtxpm_resolve_parent_post_id( $original_to_new, $original_parent_id, $lang );

			if ( $new_parent_id > 0 ) {
				if ( $new_parent_id != $post->ID ) {
					$wpdb->update(
						$wpdb->posts,
						array( 'post_parent' => $new_parent_id ),
						array( 'ID' => $post->ID ),
						array( '%d' ),
						array( '%d' )
					);

					$lang_suffix = '' !== $lang ? " [{$lang}]" : '';
					$result['details'][] = "✓ {$post->post_type} #{$post->ID}{$lang_suffix}: pai atualizado de {$original_parent_id} → {$new_parent_id}";
					$updated_count++;
				}
			} else {
				$lang_suffix = '' !== $lang ? " [{$lang}]" : '';
				$hierarchy_errors[] = "⚠ {$post->post_type} #{$post->ID}{$lang_suffix}: pai original {$original_parent_id} não encontrado";
			}
		}

		$term_hierarchy_result = qtxpm_rebuild_term_hierarchy();
		if ( $term_hierarchy_result['updated'] > 0 ) {
			$result['details'][] = "✓ Hierarquia de termos atualizada: {$term_hierarchy_result['updated']} termos";
			$updated_count += $term_hierarchy_result['updated'];
		}

		$result['success'] = true;
		$message = "Hierarquia reconstruída com sucesso! {$updated_count} itens atualizados.";
		if ( ! empty( $hierarchy_errors ) ) {
			$message .= ' ' . count( $hierarchy_errors ) . ' avisos (itens sem pai correspondente).';
			$result['warnings'] = $hierarchy_errors;
		}

		$result['message'] = $message;
	} catch ( Exception $e ) {
		$result['message'] = 'Erro: ' . $e->getMessage();
		$result['details'][] = 'Erro detalhado: ' . $e->getTraceAsString();
	}

	return $result;
}

/**
 * Rebuild hierarchy for taxonomy terms.
 *
 * Categories migrated via `qtxpm_import_wxr_post_category()` record
 * `_pll_migration_original_id` (the original WXR `<wp:category>` term_id),
 * `_pll_migration_parent_id` (the original parent's term_id, 0 for root
 * categories) and `_pll_migration_lang` (the term's resolved Polylang
 * language) as termmeta. This mirrors the post hierarchy rebuild: a broad
 * query maps every migrated term (by original ID + language) to its new
 * term_id, then a second query re-parents every term that declared a
 * non-zero original parent, preferring the parent variant in the same
 * language and falling back to any language when unavailable (see
 * `qtxpm_resolve_parent_post_id()`, which is generic despite its name).
 *
 * @return array{updated: int, details: array<int, string>}
 */
function qtxpm_rebuild_term_hierarchy(): array {
	global $wpdb;

	$result = array(
		'updated' => 0,
		'details' => array(),
	);

	$run_scope_join = qtxpm_get_migration_run_scope_join_for_terms( 't' );

	$all_terms = $wpdb->get_results(
		"SELECT t.term_id,
			tm_original.meta_value as original_id,
			tm_lang.meta_value as lang
		FROM {$wpdb->terms} t
		INNER JOIN {$wpdb->termmeta} tm_original ON t.term_id = tm_original.term_id AND tm_original.meta_key = '_pll_migration_original_id'{$run_scope_join}
		LEFT JOIN {$wpdb->termmeta} tm_lang ON t.term_id = tm_lang.term_id AND tm_lang.meta_key = '_pll_migration_lang'
		WHERE tm_original.meta_value IS NOT NULL
		AND tm_original.meta_value != '0'"
	);

	if ( empty( $all_terms ) ) {
		return $result;
	}

	$original_to_new = qtxpm_build_original_term_language_map( $all_terms );

	$child_terms = $wpdb->get_results(
		"SELECT t.term_id,
			tm_parent.meta_value as original_parent_id,
			tm_lang.meta_value as lang
		FROM {$wpdb->terms} t
		INNER JOIN {$wpdb->termmeta} tm_parent ON t.term_id = tm_parent.term_id AND tm_parent.meta_key = '_pll_migration_parent_id'{$run_scope_join}
		LEFT JOIN {$wpdb->termmeta} tm_lang ON t.term_id = tm_lang.term_id AND tm_lang.meta_key = '_pll_migration_lang'
		WHERE tm_parent.meta_value IS NOT NULL
		AND tm_parent.meta_value != '0'"
	);

	foreach ( $child_terms as $term ) {
		$original_parent_id = (int) $term->original_parent_id;
		$lang = isset( $term->lang ) ? (string) $term->lang : '';
		$new_parent_id = qtxpm_resolve_parent_post_id( $original_to_new, $original_parent_id, $lang );

		if ( $new_parent_id > 0 && $new_parent_id !== (int) $term->term_id ) {
			$wpdb->update(
				$wpdb->term_taxonomy,
				array( 'parent' => $new_parent_id ),
				array( 'term_id' => $term->term_id ),
				array( '%d' ),
				array( '%d' )
			);

			$lang_suffix = '' !== $lang ? " [{$lang}]" : '';
			$result['details'][] = "Termo #{$term->term_id}{$lang_suffix}: pai atualizado de {$original_parent_id} para {$new_parent_id}";
			$result['updated']++;
		}
	}

	return $result;
}
