<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import a processed XML file directly into WordPress.
 *
 * @param string $xml_file Path to processed XML file.
 * @param bool   $force_import Whether to ignore pre-existing posts.
 * @return array<string, mixed>
 */
function qtxpm_direct_xml_import( string $xml_file, bool $force_import = false ): array {
	global $wpdb;

	$result = array(
		'success'  => false,
		'message'  => '',
		'imported' => 0,
		'skipped'  => 0,
		'errors'   => array(),
	);

	try {
		if ( ! file_exists( $xml_file ) || ! is_readable( $xml_file ) ) {
			throw new Exception( __( 'Arquivo XML nao encontrado ou nao legivel.', 'qtx-polylang-migrator' ) );
		}

		libxml_use_internal_errors( true );
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

		$migration_run_id = qtxpm_generate_migration_run_id();
		$migration_source_key = qtxpm_get_wxr_source_key( $xml );
		qtxpm_set_current_migration_run( $migration_run_id, $migration_source_key );

		$namespaces = $xml->getNamespaces( true );
		$imported_count = 0;
		$skipped_count = 0;
		$error_count = 0;
		$imported_post_map = array();
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
				$wp_data = $item->children( $namespaces['wp'] );
				$wp_content = $item->children( $namespaces['content'] );
				$wp_excerpt = $item->children( $namespaces['excerpt'] );

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
					foreach ( $item->children( $namespaces['wp'] )->postmeta as $meta ) {
						$meta_key = (string) $meta->meta_key;
						$meta_value = (string) $meta->meta_value;

						if ( in_array( $meta_key, array( '_edit_last', '_edit_lock' ), true ) ) {
							continue;
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

					foreach ( $item->category as $category ) {
						$domain = (string) $category['domain'];
						$nicename = (string) $category['nicename'];

						if ( 'language' === $domain ) {
							qtxpm_assign_post_language( (int) $post_id, (string) $nicename );
							continue;
						}

						if ( ! $domain ) {
							$cat_name = (string) $category;
							$cat_id = get_cat_ID( $cat_name );
							if ( $cat_id ) {
								wp_set_post_categories( $post_id, array( $cat_id ) );
							}
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
	}

	return $result;
}
