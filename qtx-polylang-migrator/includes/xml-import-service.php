<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import a processed XML file directly into WordPress.
 *
 * @param string $xml_file Path to processed XML file.
 * @param bool   $force_import Whether to ignore pre-existing posts.
 * @param bool   $allow_raw Whether to allow importing content that still
 *                          contains untransformed qTranslate language blocks
 *                          (`[:xx]`, `<!--:xx-->`, `{:xx}`). Defaults to
 *                          false so raw content is rejected up front instead
 *                          of being imported verbatim into post titles/content.
 * @return array<string, mixed>
 */
function qtxpm_direct_xml_import( string $xml_file, bool $force_import = false, bool $allow_raw = false ): array {
	global $wpdb;

	$result = array(
		'success'  => false,
		'message'  => '',
		'imported' => 0,
		'skipped'  => 0,
		'errors'   => array(),
		'warnings' => array(),
	);

	$previous_libxml_errors = libxml_use_internal_errors( true );

	try {
		if ( ! file_exists( $xml_file ) || ! is_readable( $xml_file ) ) {
			throw new Exception( __( 'Arquivo XML nao encontrado ou nao legivel.', 'qtx-polylang-migrator' ) );
		}

		$xml = simplexml_load_file( $xml_file, 'SimpleXMLElement', LIBXML_NONET );

		if ( ! $xml ) {
			$errors = libxml_get_errors();
			$error_message = __( 'Nao foi possivel ler o arquivo XML.', 'qtx-polylang-migrator' );
			if ( ! empty( $errors ) ) {
				$error_message .= ' ' . __( 'Erro:', 'qtx-polylang-migrator' ) . ' ' . $errors[0]->message;
			}
			throw new Exception( $error_message );
		}

		libxml_clear_errors();

		if ( ! isset( $xml->channel ) || ! isset( $xml->channel->item ) ) {
			throw new Exception( __( 'Estrutura XML invalida. Faltando channel/item.', 'qtx-polylang-migrator' ) );
		}

		if ( ! $allow_raw && qtxpm_wxr_has_raw_multilingual_blocks( $xml ) ) {
			throw new Exception(
				__( 'O XML contem blocos de idioma do qTranslate ainda nao transformados (ex.: [:xx], <!--:xx--> ou {:xx}). Processe o arquivo pela etapa de upload do migrador (que executa a transformacao) antes de importar, ou use a opcao de importacao avancada para forcar a importacao do conteudo bruto.', 'qtx-polylang-migrator' )
			);
		}

		$migration_run_id = qtxpm_generate_migration_run_id();
		$migration_source_key = qtxpm_get_wxr_source_key( $xml );
		qtxpm_set_current_migration_run( $migration_run_id, $migration_source_key );

		$namespaces = $xml->getNamespaces( true );
		$category_hierarchy = qtxpm_build_wxr_category_hierarchy_map( $xml );
		$imported_count = 0;
		$skipped_count = 0;
		$error_count = 0;
		$imported_post_map = array();
		// Tracks, per migrated post ID, whether a real `category`-taxonomy
		// term has already been assigned during this import. WordPress
		// auto-assigns the site's default category (usually "Uncategorized")
		// to every new `post`-type post that doesn't specify `post_category`
		// on `wp_insert_post()`; the first time a real category is imported
		// for a post we must replace (not append to) that default instead of
		// leaving both attached. See `qtxpm_import_wxr_post_category()`.
		$default_category_replaced = array();
		$existing_post_index = qtxpm_build_existing_post_index(
			$wpdb->get_results(
				"SELECT ID, post_title, guid, post_type
				FROM {$wpdb->posts}
				WHERE post_status NOT IN ('trash', 'auto-draft')"
			)
		);

		$result['details'][] = sprintf(
			__( 'XML carregado: %d itens encontrados.', 'qtx-polylang-migrator' ),
			count( $xml->channel->item )
		);

		foreach ( $xml->channel->item as $index => $item ) {
			try {
				// A well-formed WXR export may legitimately omit the
				// `xmlns:*` declaration for a prefix it never actually uses
				// (e.g. `xmlns:excerpt` when no item has an excerpt); guard
				// against the resulting missing array key instead of
				// letting it surface as a warning/notice.
				$wp_data = $item->children( $namespaces['wp'] ?? '' );
				$wp_content = $item->children( $namespaces['content'] ?? '' );
				$wp_excerpt = $item->children( $namespaces['excerpt'] ?? '' );

				if ( ! isset( $item->title ) || ! isset( $wp_data->post_type ) ) {
					$result['errors'][] = sprintf(
						__( 'Item %d: campos obrigatorios faltando.', 'qtx-polylang-migrator' ),
						$index
					);
					$error_count++;
					continue;
				}

				$original_post_parent = (int) qtxpm_get_wxr_meta_value( $item, '_pll_migration_parent_id' );
				if ( $original_post_parent <= 0 ) {
					$original_post_parent = (int) $wp_data->post_parent;
				}

				$original_post_id = (int) qtxpm_get_wxr_meta_value( $item, '_pll_migration_original_id' );
				if ( $original_post_id <= 0 ) {
					$original_post_id = isset( $wp_data->post_id ) ? (int) $wp_data->post_id : 0;
				}

				$original_menu_order = (int) qtxpm_get_wxr_meta_value( $item, '_pll_migration_menu_order' );
				if ( 0 === $original_menu_order && isset( $wp_data->menu_order ) ) {
					$original_menu_order = (int) $wp_data->menu_order;
				}

				$item_language = qtxpm_get_wxr_item_language( $item );
				$initial_parent_id = qtxpm_resolve_parent_post_id( $imported_post_map, $original_post_parent, $item_language );

				$creator_login = qtxpm_get_wxr_item_creator( $item );
				$author_resolution = '' !== $creator_login ? qtxpm_resolve_wxr_author_id( $creator_login ) : array(
					'id'    => 0,
					'field' => '',
				);
				$resolved_author_id = $author_resolution['id'];

				$post_data = array(
					'post_title'        => (string) $item->title,
					'post_content'      => (string) $wp_content->encoded,
					'post_excerpt'      => (string) $wp_excerpt->encoded,
					'post_name'         => (string) $wp_data->post_name,
					'post_type'         => (string) $wp_data->post_type,
					'post_status'       => (string) $wp_data->status,
					'post_parent'       => $initial_parent_id,
					'menu_order'        => $original_menu_order,
					'post_date'         => (string) $wp_data->post_date,
					'post_date_gmt'     => (string) $wp_data->post_date_gmt,
					'post_modified'     => (string) $wp_data->post_modified,
					'post_modified_gmt' => (string) $wp_data->post_modified_gmt,
					'guid'              => (string) $item->guid,
				);

				if ( $resolved_author_id > 0 ) {
					$post_data['post_author'] = $resolved_author_id;
				}

				if ( ! $force_import && qtxpm_post_exists_in_import_index( $existing_post_index, $post_data ) ) {
					$skipped_count++;
					continue;
				}

				$post_id = wp_insert_post( $post_data );
				$post_error = is_object( $post_id ) && method_exists( $post_id, 'get_error_message' ) ? $post_id : null;

				if ( is_int( $post_id ) && $post_id > 0 ) {
					$imported_count++;

					if ( $original_post_id > 0 ) {
						if ( ! isset( $imported_post_map[ $original_post_id ] ) ) {
							$imported_post_map[ $original_post_id ] = array();
						}

						if ( '' !== $item_language ) {
							$imported_post_map[ $original_post_id ][ $item_language ] = (int) $post_id;
						}

						$imported_post_map[ $original_post_id ]['*'] = (int) $post_id;
					}

					$meta_count = 0;
					foreach ( $item->children( $namespaces['wp'] ?? '' )->postmeta as $meta ) {
						$meta_key = (string) $meta->meta_key;
						$meta_value = (string) $meta->meta_value;

						if ( in_array( $meta_key, array( '_edit_last', '_edit_lock' ), true ) ) {
							continue;
						}

						if ( qtxpm_is_serialized_meta_value( $meta_value ) && qtxpm_is_multilingual_text( $meta_value ) ) {
							$result['warnings'][] = sprintf(
								__( 'Aviso: o metadado "%1$s" do post "%2$s" (ID %3$d) parece conter dados serializados com marcadores de idioma do qTranslate ainda nao processados; o valor foi importado sem alteracoes.', 'qtx-polylang-migrator' ),
								$meta_key,
								$post_data['post_title'],
								$post_id
							);
						}

						update_post_meta( $post_id, $meta_key, $meta_value );
						$meta_count++;
					}

					if ( $original_post_id > 0 ) {
						update_post_meta( $post_id, '_pll_migration_original_id', $original_post_id );
					}
					update_post_meta( $post_id, '_pll_migration_parent_id', $original_post_parent );
					update_post_meta( $post_id, '_pll_migration_menu_order', $original_menu_order );
					update_post_meta( $post_id, '_pll_migration_run', $migration_run_id );
					if ( '' !== $migration_source_key ) {
						update_post_meta( $post_id, '_pll_migration_source', $migration_source_key );
					}

					if ( '' !== $creator_login ) {
						if ( $resolved_author_id > 0 ) {
							$result['details'][] = sprintf(
								__( 'Autor "%1$s" casado com o usuario ID %2$d pelo campo "%3$s" (post "%4$s", ID %5$d).', 'qtx-polylang-migrator' ),
								$creator_login,
								$resolved_author_id,
								$author_resolution['field'],
								$post_data['post_title'],
								$post_id
							);
						} else {
							update_post_meta( $post_id, '_pll_migration_original_author', $creator_login );
							$result['warnings'][] = sprintf(
								__( 'Autor "%1$s" do post "%2$s" (ID %3$d) nao foi encontrado entre os usuarios existentes; o post ficara atribuido ao operador que executa a importacao. O login original foi preservado no metadado "_pll_migration_original_author".', 'qtx-polylang-migrator' ),
								$creator_login,
								$post_data['post_title'],
								$post_id
							);
						}
					}

					// Assign the post's Polylang language *before* importing any
					// content taxonomy category. Polylang hooks term assignment
					// (`wp_set_object_terms()`) and resolves which language a
					// term belongs to based on the post's *already assigned*
					// language; a post with no language set yet is treated as
					// the site's default language, so any category processed
					// beforehand would silently attach the default-language
					// term even to a secondary-language post. This must not
					// depend on where the `<category domain="language">`
					// element appears among the item's `<category>` elements:
					// `qtxpm_process_wxr_content()` always appends it last on
					// the per-language clones it produces, i.e. *after* any
					// content categories cloned from the original item, so
					// relying on loop order here would get it wrong on every
					// multilingual import. `$item_language` was already
					// resolved from the full item above (independent of
					// iteration order), so assign it upfront.
					if ( '' !== $item_language ) {
						qtxpm_assign_post_language( (int) $post_id, $item_language );
					}

					foreach ( $item->category as $category ) {
						$domain = strtolower( trim( (string) $category['domain'] ) );

						if ( 'language' === $domain ) {
							// Redundant with the upfront assignment above when
							// `$item_language` matched this category, but kept
							// as a defensive fallback (e.g. multiple/mismatched
							// `language` categories on the same item).
							qtxpm_assign_post_language( (int) $post_id, (string) $category['nicename'] );
							continue;
						}

						$category_warnings = qtxpm_import_wxr_post_category( (int) $post_id, $category, $item_language, $category_hierarchy, $post_data['post_title'], $default_category_replaced );
						if ( ! empty( $category_warnings ) ) {
							array_push( $result['warnings'], ...$category_warnings );
						}
					}

					$result['details'][] = sprintf(
						__( 'Importado: %1$s (ID: %2$d) - %3$d metadados.', 'qtx-polylang-migrator' ),
						$post_data['post_title'],
						$post_id,
						$meta_count
					);
				} else {
					$error_message = $post_error ? $post_error->get_error_message() : __( 'Erro desconhecido', 'qtx-polylang-migrator' );
					$result['errors'][] = sprintf(
						__( 'Erro ao inserir post "%1$s": %2$s', 'qtx-polylang-migrator' ),
						$post_data['post_title'],
						$error_message
					);
					$error_count++;
				}
			} catch ( Exception $item_error ) {
				$result['errors'][] = sprintf(
					__( 'Erro no item %1$d: %2$s', 'qtx-polylang-migrator' ),
					$index,
					$item_error->getMessage()
				);
				$error_count++;
			}
		}

		$result['success'] = true;
		$result['message'] = sprintf(
			__( 'Importacao concluida. %1$d posts importados, %2$d ignorados', 'qtx-polylang-migrator' ),
			$imported_count,
			$skipped_count
		);

		if ( $error_count > 0 ) {
			$result['message'] .= sprintf(
				__( ', %d erros', 'qtx-polylang-migrator' ),
				$error_count
			);
		}

		$result['imported'] = $imported_count;
		$result['skipped'] = $skipped_count;
		$result['errors_count'] = $error_count;
	} catch ( Exception $e ) {
		$result['message'] = __( 'Erro na importacao:', 'qtx-polylang-migrator' ) . ' ' . $e->getMessage();
		$result['success'] = false;
	} finally {
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_libxml_errors );
	}

	return $result;
}

/**
 * Detect whether a loaded WXR document still contains untransformed
 * qTranslate-XT language blocks (`[:xx]`, `<!--:xx-->`, `{:xx}`) in item
 * titles, content, excerpts, or non-serialized postmeta values.
 *
 * Processed WXR content (as produced by `qtxpm_process_wxr_content()`)
 * never contains these markers, since they are split into per-language
 * items before import. Their presence indicates the caller skipped the
 * transformation step and is about to import raw multilingual markup
 * verbatim into post titles/content/postmeta.
 *
 * Serialized postmeta values (PHP `a:`/`O:`/`s:` serialization) are
 * intentionally excluded: splitting them would corrupt the serialized
 * structure, so they are left untouched by the transformer and only
 * surfaced as an import warning (see `qtxpm_direct_xml_import()`), not
 * as a hard block here.
 *
 * @param SimpleXMLElement $xml Loaded WXR document.
 * @return bool
 */
function qtxpm_wxr_has_raw_multilingual_blocks( SimpleXMLElement $xml ): bool {
	if ( ! isset( $xml->channel->item ) ) {
		return false;
	}

	$namespaces = $xml->getNamespaces( true );

	foreach ( $xml->channel->item as $item ) {
		if ( qtxpm_is_multilingual_text( (string) $item->title ) ) {
			return true;
		}

		if ( isset( $namespaces['content'] ) ) {
			$wp_content = $item->children( $namespaces['content'] );
			if ( isset( $wp_content->encoded ) && qtxpm_is_multilingual_text( (string) $wp_content->encoded ) ) {
				return true;
			}
		}

		if ( isset( $namespaces['excerpt'] ) ) {
			$wp_excerpt = $item->children( $namespaces['excerpt'] );
			if ( isset( $wp_excerpt->encoded ) && qtxpm_is_multilingual_text( (string) $wp_excerpt->encoded ) ) {
				return true;
			}
		}

		if ( isset( $namespaces['wp'] ) ) {
			$wp_data = $item->children( $namespaces['wp'] );
			if ( isset( $wp_data->postmeta ) ) {
				foreach ( $wp_data->postmeta as $meta ) {
					$meta_value = (string) $meta->meta_value;

					if ( qtxpm_is_serialized_meta_value( $meta_value ) ) {
						continue;
					}

					if ( qtxpm_is_multilingual_text( $meta_value ) ) {
						return true;
					}
				}
			}
		}
	}

	return false;
}

/**
 * Read the `dc:creator` (WXR author login) declared for a WXR item.
 *
 * @param SimpleXMLElement $item WXR item.
 * @return string
 */
function qtxpm_get_wxr_item_creator( SimpleXMLElement $item ): string {
	$dc = $item->children( 'http://purl.org/dc/elements/1.1/' );

	if ( ! isset( $dc->creator ) ) {
		return '';
	}

	return trim( (string) $dc->creator );
}

/**
 * Resolve a WXR `dc:creator` value to an existing WordPress user ID.
 *
 * Tries, in order, matching by login, by nicename/slug, and by email
 * (in that order by default; filterable via `qtxpm_author_resolution_fields`),
 * since different qTranslate exports have been observed to populate
 * `dc:creator` with any of the three.
 *
 * When no user matches, this function does NOT prevent authorship
 * reassignment by itself: `wp_insert_post()` silently falls back to the
 * currently logged-in operator running the import when `post_author` is
 * omitted from the post data, which is WordPress core's own default
 * behavior. What this function (and its caller) do instead is make that
 * fallback traceable: the caller omits `post_author` when resolution
 * fails and records the original `dc:creator` login in the
 * `_pll_migration_original_author` post meta, and surfaces a warning so
 * the operator is aware the post was attributed to them rather than the
 * original author.
 *
 * @param string $creator_login `dc:creator` value from the WXR item.
 * @return array{id: int, field: string} Resolved user ID and the field that matched (`login`/`slug`/`email`), or `array( 'id' => 0, 'field' => '' )` when no user matches.
 */
function qtxpm_resolve_wxr_author_id( string $creator_login ): array {
	$no_match = array(
		'id'    => 0,
		'field' => '',
	);

	if ( '' === $creator_login || ! function_exists( 'get_user_by' ) ) {
		return $no_match;
	}

	/**
	 * Filter the ordered list of user fields tried when matching a WXR
	 * `dc:creator` value to an existing WordPress user.
	 *
	 * @param string[] $fields Fields passed to `get_user_by()`, tried in order.
	 */
	$fields = apply_filters( 'qtxpm_author_resolution_fields', array( 'login', 'slug', 'email' ) );

	foreach ( (array) $fields as $field ) {
		$field = (string) $field;
		$user = get_user_by( $field, $creator_login );

		if ( $user && isset( $user->ID ) ) {
			return array(
				'id'    => (int) $user->ID,
				'field' => $field,
			);
		}
	}

	return $no_match;
}
