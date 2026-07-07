<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the post meta key used to mark downgraded duplicates.
 *
 * @return string
 */
function qtxpm_get_duplicate_marker_meta_key(): string {
	return '_pll_migration_duplicate_of';
}

/**
 * Count how complete a migration candidate is.
 *
 * @param object $candidate Candidate row.
 * @return int
 */
function qtxpm_get_duplicate_candidate_completeness( object $candidate ): int {
	$completeness = 0;

	if ( ! empty( $candidate->original_id ) ) {
		$completeness++;
	}

	if ( ! empty( $candidate->migration_guid ) ) {
		$completeness++;
	}

	if ( isset( $candidate->migration_parent_id ) && '' !== $candidate->migration_parent_id ) {
		$completeness++;
	}

	return $completeness;
}

/**
 * Determine whether a slug looks stable enough to preserve.
 *
 * @param string $slug Post slug.
 * @return bool
 */
function qtxpm_is_stable_slug( string $slug ): bool {
	$slug = trim( $slug );

	return '' !== $slug && preg_match( '/-\d+$/', $slug ) !== 1;
}

/**
 * Check whether a candidate already has a valid Polylang language assignment.
 *
 * @param object $candidate Candidate row.
 * @param string $normalized_language Normalized Polylang language slug.
 * @return bool
 */
function qtxpm_candidate_has_assigned_language( object $candidate, string $normalized_language ): bool {
	if ( ! function_exists( 'pll_get_post_language' ) || empty( $candidate->ID ) ) {
		return false;
	}

	$current_language = (string) pll_get_post_language( (int) $candidate->ID, 'slug' );
	if ( '' === $current_language ) {
		return false;
	}

	return qtxpm_resolve_polylang_language_code( $current_language ) === $normalized_language;
}

/**
 * Compare two duplicate candidates and return the canonical one first.
 *
 * @param object $candidate_a Candidate A.
 * @param object $candidate_b Candidate B.
 * @param string $normalized_language Normalized Polylang language slug.
 * @return int
 */
function qtxpm_compare_duplicate_candidates( object $candidate_a, object $candidate_b, string $normalized_language ): int {
	$has_translation_group_a = empty( $candidate_a->translation_term_id ) ? 0 : 1;
	$has_translation_group_b = empty( $candidate_b->translation_term_id ) ? 0 : 1;
	if ( $has_translation_group_a !== $has_translation_group_b ) {
		return $has_translation_group_b <=> $has_translation_group_a;
	}

	$has_language_assignment_a = qtxpm_candidate_has_assigned_language( $candidate_a, $normalized_language ) ? 1 : 0;
	$has_language_assignment_b = qtxpm_candidate_has_assigned_language( $candidate_b, $normalized_language ) ? 1 : 0;
	if ( $has_language_assignment_a !== $has_language_assignment_b ) {
		return $has_language_assignment_b <=> $has_language_assignment_a;
	}

	$completeness_a = qtxpm_get_duplicate_candidate_completeness( $candidate_a );
	$completeness_b = qtxpm_get_duplicate_candidate_completeness( $candidate_b );
	if ( $completeness_a !== $completeness_b ) {
		return $completeness_b <=> $completeness_a;
	}

	$is_stable_slug_a = qtxpm_is_stable_slug( isset( $candidate_a->post_name ) ? (string) $candidate_a->post_name : '' ) ? 1 : 0;
	$is_stable_slug_b = qtxpm_is_stable_slug( isset( $candidate_b->post_name ) ? (string) $candidate_b->post_name : '' ) ? 1 : 0;
	if ( $is_stable_slug_a !== $is_stable_slug_b ) {
		return $is_stable_slug_b <=> $is_stable_slug_a;
	}

	$slug_length_a = strlen( (string) ( $candidate_a->post_name ?? '' ) );
	$slug_length_b = strlen( (string) ( $candidate_b->post_name ?? '' ) );
	if ( $slug_length_a !== $slug_length_b ) {
		return $slug_length_a <=> $slug_length_b;
	}

	return (int) ( $candidate_a->ID ?? 0 ) <=> (int) ( $candidate_b->ID ?? 0 );
}

/**
 * Downgrade duplicate migration candidates to drafts.
 *
 * @return array{
 *   success: bool,
 *   message: string,
 *   details: array<int, string>,
 *   groups_analyzed: int,
 *   duplicate_groups: int,
 *   kept_ids: array<int, int>,
 *   downgraded_ids: array<int, int>,
 *   excluded_post_ids: array<int, int>
 * }
 */
function qtxpm_deduplicate_translation_posts_process(): array {
	global $wpdb;

	$result = array(
		'success'           => false,
		'message'           => '',
		'details'           => array(),
		'groups_analyzed'   => 0,
		'duplicate_groups'  => 0,
		'kept_ids'          => array(),
		'downgraded_ids'    => array(),
		'excluded_post_ids' => array(),
	);

	$duplicate_marker_key = qtxpm_get_duplicate_marker_meta_key();
	$source_scope_join = qtxpm_get_migration_source_scope_join();
	$candidates = $wpdb->get_results(
		"
		SELECT p.ID, p.post_type, p.post_status, p.post_name,
			pm_original.meta_value AS original_id,
			pm_lang.meta_value AS lang,
			pm_guid.meta_value AS migration_guid,
			pm_parent.meta_value AS migration_parent_id,
			pm_duplicate.meta_value AS duplicate_of,
			translation_groups.term_id AS translation_term_id
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm_original
			ON p.ID = pm_original.post_id
			AND pm_original.meta_key = '_pll_migration_original_id'{$source_scope_join}
		INNER JOIN {$wpdb->postmeta} pm_lang
			ON p.ID = pm_lang.post_id
			AND pm_lang.meta_key = '_pll_migration_lang'
		LEFT JOIN {$wpdb->postmeta} pm_guid
			ON p.ID = pm_guid.post_id
			AND pm_guid.meta_key = '_pll_migration_guid'
		LEFT JOIN {$wpdb->postmeta} pm_parent
			ON p.ID = pm_parent.post_id
			AND pm_parent.meta_key = '_pll_migration_parent_id'
		LEFT JOIN {$wpdb->postmeta} pm_duplicate
			ON p.ID = pm_duplicate.post_id
			AND pm_duplicate.meta_key = '{$duplicate_marker_key}'
		LEFT JOIN (
			SELECT tr.object_id, MIN(tt.term_id) AS term_id
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt
				ON tr.term_taxonomy_id = tt.term_taxonomy_id
				AND tt.taxonomy = 'post_translations'
			GROUP BY tr.object_id
		) translation_groups
			ON p.ID = translation_groups.object_id
		WHERE p.post_status NOT IN ('trash', 'auto-draft')
		"
	);

	$grouped_candidates = array();

	foreach ( $candidates as $candidate ) {
		$post_id = isset( $candidate->ID ) ? (int) $candidate->ID : 0;
		if ( $post_id <= 0 ) {
			continue;
		}

		if ( ! empty( $candidate->duplicate_of ) ) {
			$result['excluded_post_ids'][] = $post_id;
			continue;
		}

		$post_type = isset( $candidate->post_type ) ? (string) $candidate->post_type : '';
		if ( ! qtxpm_is_translated_post_type( $post_type ) ) {
			continue;
		}

		$original_id = isset( $candidate->original_id ) ? (int) $candidate->original_id : 0;
		if ( $original_id <= 0 ) {
			continue;
		}

		$normalized_language = qtxpm_resolve_polylang_language_code( isset( $candidate->lang ) ? (string) $candidate->lang : '' );
		if ( '' === $normalized_language ) {
			continue;
		}

		$group_key = $post_type . '|' . $original_id . '|' . $normalized_language;
		if ( ! isset( $grouped_candidates[ $group_key ] ) ) {
			$grouped_candidates[ $group_key ] = array();
		}

		$candidate->normalized_language = $normalized_language;
		$grouped_candidates[ $group_key ][] = $candidate;
	}

	$result['groups_analyzed'] = count( $grouped_candidates );

	foreach ( $grouped_candidates as $group_candidates ) {
		if ( 1 === count( $group_candidates ) ) {
			$result['kept_ids'][] = (int) $group_candidates[0]->ID;
			continue;
		}

		$result['duplicate_groups']++;
		$normalized_language = isset( $group_candidates[0]->normalized_language ) ? (string) $group_candidates[0]->normalized_language : '';

		usort(
			$group_candidates,
			static function ( $candidate_a, $candidate_b ) use ( $normalized_language ) {
				return qtxpm_compare_duplicate_candidates( $candidate_a, $candidate_b, $normalized_language );
			}
		);

		$canonical = array_shift( $group_candidates );
		$canonical_id = (int) $canonical->ID;
		$result['kept_ids'][] = $canonical_id;
		delete_post_meta( $canonical_id, $duplicate_marker_key );

		foreach ( $group_candidates as $duplicate ) {
			$duplicate_id = (int) $duplicate->ID;
			$result['downgraded_ids'][] = $duplicate_id;
			$result['excluded_post_ids'][] = $duplicate_id;

			wp_update_post(
				array(
					'ID'          => $duplicate_id,
					'post_status' => 'draft',
				)
			);

			update_post_meta( $duplicate_id, $duplicate_marker_key, $canonical_id );

			if ( function_exists( 'wp_delete_object_term_relationships' ) ) {
				wp_delete_object_term_relationships( $duplicate_id, 'post_translations' );
			}

			$result['details'][] = sprintf(
				__( 'Manter %1$s #%2$d [%3$s] e mover duplicata para draft como #%4$d.', 'qtx-polylang-migrator' ),
				(string) $duplicate->post_type,
				$duplicate_id,
				$normalized_language,
				$canonical_id
			);
		}
	}

	$result['excluded_post_ids'] = array_values( array_unique( $result['excluded_post_ids'] ) );
	$result['success'] = true;
	$result['message'] = sprintf(
		__( 'Deduplicacao concluida: %1$d grupos analisados, %2$d grupos com duplicatas, %3$d posts movidos para rascunho.', 'qtx-polylang-migrator' ),
		$result['groups_analyzed'],
		$result['duplicate_groups'],
		count( $result['downgraded_ids'] )
	);

	return $result;
}

/**
 * Restore Polylang language assignments for imported posts.
 *
 * @param int[] $excluded_post_ids Post IDs that must be ignored during restore.
 * @return array{success: bool, assigned: int, skipped: int, message: string}
 */
function qtxpm_restore_post_languages( array $excluded_post_ids = array() ): array {
	global $wpdb;

	$result = array(
		'success'  => false,
		'assigned' => 0,
		'skipped'  => 0,
		'message'  => '',
	);

	if ( ! function_exists( 'pll_set_post_language' ) ) {
		$result['message'] = __( 'Polylang nao esta ativo ou nao expoe pll_set_post_language().', 'qtx-polylang-migrator' );
		return $result;
	}

	$current_run_id = qtxpm_get_current_migration_run()['run'];
	if ( '' !== $current_run_id ) {
		$posts_with_lang = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value as language_code
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->postmeta} pm_run ON pm.post_id = pm_run.post_id AND pm_run.meta_key = '_pll_migration_run' AND pm_run.meta_value = %s
				WHERE pm.meta_key = '_pll_migration_lang'",
				$current_run_id
			)
		);
	} else {
		$posts_with_lang = $wpdb->get_results(
			"SELECT post_id, meta_value as language_code
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_pll_migration_lang'"
		);
	}

	$excluded_post_ids = array_map( 'intval', $excluded_post_ids );
	$excluded_lookup = array_flip( array_values( array_unique( array_filter( $excluded_post_ids ) ) ) );

	foreach ( $posts_with_lang as $post_lang ) {
		$post_id = isset( $post_lang->post_id ) ? (int) $post_lang->post_id : 0;
		$language_code = isset( $post_lang->language_code ) ? (string) $post_lang->language_code : '';

		if ( isset( $excluded_lookup[ $post_id ] ) ) {
			$result['skipped']++;
			continue;
		}

		if ( qtxpm_assign_post_language( $post_id, $language_code ) ) {
			$result['assigned']++;
		} else {
			$result['skipped']++;
		}
	}

	$result['success'] = true;
	$result['message'] = sprintf(
		__( 'Idiomas restaurados: %1$d posts atribuidos, %2$d ignorados.', 'qtx-polylang-migrator' ),
		$result['assigned'],
		$result['skipped']
	);

	return $result;
}

/**
 * Connect translations using Polylang after deduplication and language restore.
 *
 * @return array{success: bool, message: string, details: array<int, string>}
 */
function qtxpm_connect_translations_process() {
	global $wpdb;

	$result = array(
		'success' => false,
		'message' => '',
		'details' => array(),
	);

	if ( ! function_exists( 'pll_save_post_translations' ) ) {
		$result['message'] = __( 'Polylang nao esta ativo ou a funcao necessaria esta indisponivel.', 'qtx-polylang-migrator' );
		return $result;
	}

	try {
		$deduplication_result = qtxpm_deduplicate_translation_posts_process();
		$result['deduplication'] = $deduplication_result;
		$result['details'][] = $deduplication_result['message'];
		foreach ( $deduplication_result['details'] as $detail ) {
			$result['details'][] = $detail;
		}

		$excluded_lookup = array_flip( array_map( 'intval', (array) ( $deduplication_result['excluded_post_ids'] ?? array() ) ) );
		$language_restore = qtxpm_restore_post_languages( (array) ( $deduplication_result['excluded_post_ids'] ?? array() ) );
		$result['details'][] = $language_restore['message'];

		$run_scope_join = qtxpm_get_migration_run_scope_join();
		$posts = $wpdb->get_results(
			"SELECT p.ID, p.post_type, pm.meta_value as group_id, pm2.meta_value as lang
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id{$run_scope_join}
			WHERE pm.meta_key = '_pll_migration_group'
			AND pm2.meta_key = '_pll_migration_lang'
			AND p.post_status NOT IN ('trash', 'auto-draft')"
		);

		$translations = array();
		foreach ( $posts as $post ) {
			$post_id = isset( $post->ID ) ? (int) $post->ID : 0;
			if ( $post_id <= 0 || isset( $excluded_lookup[ $post_id ] ) ) {
				continue;
			}

			if ( ! qtxpm_is_translated_post_type( isset( $post->post_type ) ? (string) $post->post_type : '' ) ) {
				continue;
			}

			$group_id = isset( $post->group_id ) ? (string) $post->group_id : '';
			$language = qtxpm_resolve_polylang_language_code( isset( $post->lang ) ? (string) $post->lang : '' );

			if ( '' === $group_id || '' === $language ) {
				continue;
			}

			if ( ! isset( $translations[ $group_id ][ $language ] ) ) {
				$translations[ $group_id ][ $language ] = $post_id;
			}
		}

		$connected_count = 0;
		foreach ( $translations as $group_id => $lang_posts ) {
			if ( count( $lang_posts ) > 1 ) {
				pll_save_post_translations( $lang_posts );
				$result['details'][] = sprintf(
					__( 'Grupo %1$s conectado com os idiomas: %2$s.', 'qtx-polylang-migrator' ),
					(string) $group_id,
					implode( ', ', array_keys( $lang_posts ) )
				);
				$connected_count++;
			}
		}

		$result['success'] = true;
		$result['message'] = sprintf(
			__( 'Traducoes conectadas com sucesso. %d grupos processados.', 'qtx-polylang-migrator' ),
			$connected_count
		);
	} catch ( Exception $e ) {
		$result['message'] = __( 'Erro:', 'qtx-polylang-migrator' ) . ' ' . $e->getMessage();
	}

	return $result;
}
