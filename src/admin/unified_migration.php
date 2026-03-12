<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Import and Process Handler
add_action( 'admin_init', 'qtranslate_xt_handle_import_process' );
function qtranslate_xt_handle_import_process() {
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'qtranslate-xt-polylang-unified' ) {
		return;
	}
	
	// Handle file upload and processing
	if ( isset( $_POST['submit'] ) && isset( $_POST['qxt_unified_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['qxt_unified_nonce'] ), 'qtranslate_xt_unified_import' ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Acesso negado.' );
		}

		if ( empty( $_FILES['wxr_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['wxr_file']['tmp_name'] ) ) {
			wp_die( 'Por favor, envie um arquivo WXR válido.' );
		}

		$file = $_FILES['wxr_file']['tmp_name'];
		
		global $q_config;
		$languages    = qtranxf_getSortedLanguages();
		$default_lang = $q_config['default_language'];
		
		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		
		libxml_use_internal_errors(true);
		if ( ! @$doc->load( $file ) ) {
			wp_die( 'Erro ao ler o arquivo XML. Verifique se é uma exportação WXR do WordPress válida.' );
		}
		libxml_clear_errors();

		$processed_content = qtranslate_xt_process_wxr_content( $doc, $languages, $default_lang );
		
		// Store processed content in transient for import
		set_transient( 'qxt_processed_xml', $processed_content, 3600 );
		
		// Redirect to import step
		wp_safe_redirect( admin_url( 'tools.php?page=qtranslate-xt-polylang-unified&step=import' ) );
		exit;
	}
	
	// Handle WordPress import
	if ( isset( $_POST['wordpress_import'] ) && isset( $_POST['qxt_unified_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['qxt_unified_nonce'] ), 'qtranslate_xt_unified_import' ) ) {
		$processed_xml = get_transient( 'qxt_processed_xml' );
		
		if ( ! $processed_xml ) {
			wp_die( 'Nenhum XML processado encontrado. Por favor, processe um arquivo primeiro.' );
		}
		
		// Create temporary file for WordPress importer
		$temp_file = wp_tempnam();
		file_put_contents( $temp_file, $processed_xml );
		
		// Import using direct WordPress import functions
		if ( ! class_exists( 'WP_Importer' ) ) {
			// Try to load WordPress Importer
			require_once ABSPATH . 'wp-admin/includes/import.php';
		}
		
		// Get force import option
		$force_import = isset( $_POST['force_import'] ) && $_POST['force_import'] === '1';
		
		// Try direct import using WordPress core functions
		try {
			$import_result = qtranslate_xt_direct_xml_import( $temp_file, $force_import );
			
			if ( $import_result['success'] ) {
				set_transient( 'qxt_import_result', $import_result, 3600 );
			} else {
				set_transient( 'qxt_import_result', $import_result, 3600 );
			}
		} catch ( Exception $e ) {
			set_transient( 'qxt_import_result', [
				'success' => false, 
				'message' => 'Erro na importação: ' . $e->getMessage()
			], 3600 );
		}
		
		// Clean up
		unlink( $temp_file );
		
		// Redirect to final step
		wp_safe_redirect( admin_url( 'tools.php?page=qtranslate-xt-polylang-unified&step=complete' ) );
		exit;
	}
	
	// Handle hierarchy rebuild and connection
	if ( isset( $_POST['finalize_migration'] ) && isset( $_POST['qxt_unified_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['qxt_unified_nonce'] ), 'qtranslate_xt_unified_import' ) ) {
		$results = array();
		
		// Step 1: Rebuild hierarchy
		$hierarchy_result = qtranslate_xt_rebuild_hierarchy_process();
		$results['hierarchy'] = $hierarchy_result;
		
		// Step 2: Connect translations
		$connection_result = qtranslate_xt_connect_translations_process();
		$results['connection'] = $connection_result;
		
		// Store results
		set_transient( 'qxt_finalization_result', $results, 3600 );
		
		// Redirect to complete
		wp_safe_redirect( admin_url( 'tools.php?page=qtranslate-xt-polylang-unified&step=complete' ) );
		exit;
	}
}

// Register unified menu page
add_action( 'admin_menu', 'qtranslate_xt_unified_menu' );
function qtranslate_xt_unified_menu() {
	add_management_page(
		'Migração Unificada qTranslate-XT → Polylang',
		'Migração Unificada',
		'manage_options',
		'qtranslate-xt-polylang-unified',
		'qtranslate_xt_unified_page'
	);
}

// Hierarchy rebuild function (integrated from rebuild_hierarchy_admin.php)
function qtranslate_xt_rebuild_hierarchy_process() {
	global $wpdb;
	
	$result = array(
		'success' => false,
		'message' => '',
		'details' => array()
	);
	
	try {
		// Map old IDs to new IDs
		$old_to_new = array();
		$posts = $wpdb->get_results("
			SELECT p.ID, pm.meta_value as old_id 
			FROM {$wpdb->posts} p 
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
			WHERE pm.meta_key = '_pll_migration_group' 
			AND pm.meta_value != '0'
		");
		
		if ( empty( $posts ) ) {
			throw new Exception( 'Nenhum post com metadados de migração encontrado.' );
		}
		
		foreach ( $posts as $post ) {
			$old_to_new[$post->old_id] = $post->ID;
			$result['details'][] = "Mapeado: ID Antigo {$post->old_id} → ID Novo {$post->ID}";
		}
		
		// Rebuild hierarchy
		$hierarchy_posts = $wpdb->get_results("
			SELECT p.ID, pm.meta_value as old_parent_id 
			FROM {$wpdb->posts} p 
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
			WHERE pm.meta_key = '_pll_migration_parent' 
			AND pm.meta_value != '0'
			AND p.post_type = 'page'
		");
		
		$updated_count = 0;
		foreach ( $hierarchy_posts as $post ) {
			if ( isset( $old_to_new[$post->old_parent_id] ) ) {
				$new_parent_id = $old_to_new[$post->old_parent_id];
				
				if ( $new_parent_id != $post->ID ) {
					$wpdb->update(
						$wpdb->posts,
						array( 'post_parent' => $new_parent_id ),
						array( 'ID' => $post->ID ),
						array( '%d' ),
						array( '%d' )
					);
					
					$result['details'][] = "✓ Página {$post->ID} atualizada: pai {$post->old_parent_id} → {$new_parent_id}";
					$updated_count++;
				}
			}
		}
		
		$result['success'] = true;
		$result['message'] = "Hierarquia reconstruída com sucesso! {$updated_count} páginas atualizadas.";
		
	} catch ( Exception $e ) {
		$result['message'] = 'Erro: ' . $e->getMessage();
	}
	
	return $result;
}

// Connect translations function
function qtranslate_xt_connect_translations_process() {
	global $wpdb;
	
	$result = array(
		'success' => false,
		'message' => '',
		'details' => array()
	);
	
	if ( ! function_exists( 'pll_save_post_translations' ) ) {
		$result['message'] = 'Polylang não está ativo ou função indisponível.';
		return $result;
	}
	
	try {
		// Get posts with migration metadata
		$posts = $wpdb->get_results("
			SELECT p.ID, pm.meta_value as group_id, pm2.meta_value as lang
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
			WHERE pm.meta_key = '_pll_migration_group'
			AND pm2.meta_key = '_pll_migration_lang'
			AND p.post_status = 'publish'
		");
		
		$translations = array();
		foreach ( $posts as $post ) {
			$translations[$post->group_id][$post->lang] = $post->ID;
		}
		
		$connected_count = 0;
		foreach ( $translations as $group_id => $lang_posts ) {
			if ( count( $lang_posts ) > 1 ) {
				pll_save_post_translations( $lang_posts );
				$result['details'][] = "✓ Grupo {$group_id}: " . implode( ', ', array_keys( $lang_posts ) ) . " conectados";
				$connected_count++;
			}
		}
		
		$result['success'] = true;
		$result['message'] = "Traduções conectadas com sucesso! {$connected_count} grupos processados.";
		
	} catch ( Exception $e ) {
		$result['message'] = 'Erro: ' . $e->getMessage();
	}
	
	return $result;
}

// Direct XML import function
function qtranslate_xt_direct_xml_import( $xml_file, $force_import = false ) {
	global $wpdb;
	
	$result = array(
		'success' => false,
		'message' => '',
		'imported' => 0,
		'skipped' => 0,
		'errors' => array()
	);
	
	try {
		// Check if file exists and is readable
		if ( ! file_exists( $xml_file ) || ! is_readable( $xml_file ) ) {
			throw new Exception( 'Arquivo XML não encontrado ou não legível.' );
		}
		
		// Parse XML with better error handling
		libxml_use_internal_errors(true);
		$xml = simplexml_load_file( $xml_file );
		
		if ( ! $xml ) {
			$errors = libxml_get_errors();
			$error_msg = 'Não foi possível ler o arquivo XML.';
			if ( ! empty( $errors ) ) {
				$error_msg .= ' Erro: ' . $errors[0]->message;
			}
			throw new Exception( $error_msg );
		}
		
		libxml_clear_errors();
		
		// Validate XML structure
		if ( ! isset( $xml->channel ) || ! isset( $xml->channel->item ) ) {
			throw new Exception( 'Estrutura XML inválida. Faltando channel/item.' );
		}
		
		$namespaces = $xml->getNamespaces( true );
		$wp = $xml->children( $namespaces['wp'] );
		
		$imported_count = 0;
		$skipped_count = 0;
		$error_count = 0;
		
		$total_items = count( $xml->channel->item );
		$result['details'][] = "XML carregado: {$total_items} itens encontrados";
		
		foreach ( $xml->channel->item as $index => $item ) {
			try {
				$wp_data = $item->children( $namespaces['wp'] );
				$wp_content = $item->children( $namespaces['content'] );
				$wp_excerpt = $item->children( $namespaces['excerpt'] );
				
				// Validate required fields
				if ( ! isset( $item->title ) || ! isset( $wp_data->post_type ) ) {
					$result['errors'][] = "Item {$index}: Campos obrigatórios faltando";
					$error_count++;
					continue;
				}
				
				// Extract post data
				$post_data = array(
					'post_title' => (string) $item->title,
					'post_content' => (string) $wp_content->encoded,
					'post_excerpt' => (string) $wp_excerpt->encoded,
					'post_type' => (string) $wp_data->post_type,
					'post_status' => (string) $wp_data->status,
					'post_parent' => (int) $wp_data->post_parent,
					'menu_order' => (int) $wp_data->menu_order,
					'post_date' => (string) $wp_data->post_date,
					'post_date_gmt' => (string) $wp_data->post_date_gmt,
					'post_modified' => (string) $wp_data->post_modified,
					'post_modified_gmt' => (string) $wp_data->post_modified_gmt,
					'guid' => (string) $wp_data->guid,
				);
				
				// Check if post already exists (more flexible check)
				if ( ! $force_import ) {
					$existing = $wpdb->get_var( $wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE (post_title = %s OR guid = %s) AND post_type = %s LIMIT 1",
						$post_data['post_title'],
						$post_data['guid'],
						$post_data['post_type']
					));
					
					if ( $existing ) {
						$skipped_count++;
						continue;
					}
				}
				
				// Insert post
				$post_id = wp_insert_post( $post_data );
				
				if ( $post_id && ! is_wp_error( $post_id ) ) {
					$imported_count++;
					
					// Process postmeta
					$meta_count = 0;
					foreach ( $item->children( $namespaces['wp'] )->postmeta as $meta ) {
						$meta_key = (string) $meta->meta_key;
						$meta_value = (string) $meta->meta_value;
						
						// Skip WordPress internal meta
						if ( in_array( $meta_key, array( '_edit_last', '_edit_lock' ) ) ) {
							continue;
						}
						
						update_post_meta( $post_id, $meta_key, $meta_value );
						$meta_count++;
					}
					
					// Process categories
					foreach ( $item->category as $category ) {
						$domain = (string) $category['domain'];
						$nicename = (string) $category['nicename'];
						
						if ( $domain === 'language' ) {
							// Validate language exists in Polylang before processing
							if ( function_exists('PLL') && PLL()->model->get_language($nicename) ) {
								// Language exists, this is a valid Polylang language category
								// Store as postmeta instead of category to avoid Polylang warnings
								update_post_meta( $post_id, '_import_language_category', $nicename );
							}
							continue;
						}
						
						// Handle regular categories
						if ( ! $domain ) {
							$cat_name = (string) $category;
							$cat_id = get_cat_ID( $cat_name );
							
							if ( $cat_id ) {
								wp_set_post_categories( $post_id, array( $cat_id ) );
							}
						}
					}
					
					$result['details'][] = "Importado: {$post_data['post_title']} (ID: {$post_id}) - {$meta_count} metadados";
					
				} else {
					$error_msg = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'Erro desconhecido';
					$result['errors'][] = "Erro ao inserir post '{$post_data['post_title']}': {$error_msg}";
					$error_count++;
				}
				
			} catch ( Exception $item_error ) {
				$result['errors'][] = "Erro no item {$index}: " . $item_error->getMessage();
				$error_count++;
			}
		}
		
		$result['success'] = true;
		$result['message'] = "Importação concluída! {$imported_count} posts importados, {$skipped_count} ignorados";
		
		if ( $error_count > 0 ) {
			$result['message'] .= ", {$error_count} erros";
		}
		
		$result['imported'] = $imported_count;
		$result['skipped'] = $skipped_count;
		$result['errors_count'] = $error_count;
		
	} catch ( Exception $e ) {
		$result['message'] = 'Erro na importação: ' . $e->getMessage();
		$result['success'] = false;
	}
	
	// After successful import, restore language categories if Polylang is active
	if ( $result['success'] && function_exists('PLL') ) {
		qtranslate_xt_restore_language_categories();
	}
	
	return $result;
}

// Restore language categories after import
function qtranslate_xt_restore_language_categories() {
	global $wpdb;
	
	// Get all posts with imported language categories
	$posts_with_lang = $wpdb->get_results("
		SELECT post_id, meta_value as language_code 
		FROM {$wpdb->postmeta} 
		WHERE meta_key = '_import_language_category'
	");
	
	foreach ( $posts_with_lang as $post_lang ) {
		// Double-check language exists in Polylang
		if ( PLL()->model->get_language($post_lang->language_code) ) {
			// Create the language category properly
			$category = wp_insert_term( $post_lang->language_code, 'category' );
			
			if ( ! is_wp_error( $category ) ) {
				// Assign the category to the post
				wp_set_post_categories( $post_lang->post_id, array( $category['term_id'] ) );
				
				// Clean up the temporary meta
				delete_post_meta( $post_lang->post_id, '_import_language_category' );
			}
		}
	}
}

// Main page function
function qtranslate_xt_unified_page() {
	$current_step = isset( $_GET['step'] ) ? $_GET['step'] : 'upload';
	$import_result = get_transient( 'qxt_import_result' );
	$finalization_result = get_transient( 'qxt_finalization_result' );
	
	// Clear transients if showing complete page
	if ( $current_step === 'complete' ) {
		delete_transient( 'qxt_processed_xml' );
		delete_transient( 'qxt_import_result' );
		delete_transient( 'qxt_finalization_result' );
	}
	?>
	<div class="wrap">
		<h1>🚀 Migração Unificada qTranslate-XT → Polylang</h1>
		<p>Processo completo em um único lugar: exportação, importação, reconstrução e conexão.</p>
		
		<?php if ( $import_result ) : ?>
			<div class="notice notice-<?php echo $import_result['success'] ? 'success' : 'error'; ?> is-dismissible">
				<p><strong><?php echo esc_html( $import_result['message'] ); ?></strong></p>
			</div>
			
			<?php if ( ! empty( $import_result['details'] ) ) : ?>
				<div class="card" style="margin-top: 20px;">
					<h3>Detalhes da Importação</h3>
					<div style="background: #f9f9f9; padding: 15px; border-radius: 4px; max-height: 300px; overflow-y: auto;">
						<?php foreach ( $import_result['details'] as $detail ) : ?>
							<div style="margin-bottom: 5px; font-family: monospace; font-size: 13px;"><?php echo esc_html( $detail ); ?></div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
			
			<?php if ( ! empty( $import_result['errors'] ) ) : ?>
				<div class="card" style="margin-top: 20px;">
					<h3>Erros Encontrados</h3>
					<div style="background: #ffebeb; padding: 15px; border-radius: 4px; max-height: 300px; overflow-y: auto;">
						<?php foreach ( $import_result['errors'] as $error ) : ?>
							<div style="margin-bottom: 5px; font-family: monospace; font-size: 13px; color: #d63638;"><?php echo esc_html( $error ); ?></div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
		
		<?php if ( $finalization_result && $current_step === 'complete' ) : ?>
			<div class="notice notice-<?php echo $finalization_result['hierarchy']['success'] ? 'success' : 'error'; ?> is-dismissible">
				<p><strong>Hierarquia:</strong> <?php echo esc_html( $finalization_result['hierarchy']['message'] ); ?></p>
			</div>
			<div class="notice notice-<?php echo $finalization_result['connection']['success'] ? 'success' : 'error'; ?> is-dismissible">
				<p><strong>Traduções:</strong> <?php echo esc_html( $finalization_result['connection']['message'] ); ?></p>
			</div>
		<?php endif; ?>
		
		<!-- Progress Steps -->
		<div class="card" style="max-width: 800px; margin-top: 20px;">
			<h2>📋 Progresso da Migração</h2>
			<ol style="padding-left: 20px;">
				<li style="<?php echo $current_step === 'upload' ? 'color: #0073aa; font-weight: bold;' : 'color: #666;'; ?>">
					📤 Upload e Processamento do XML
					<?php if ( $current_step !== 'upload' ) echo '✅'; ?>
				</li>
				<li style="<?php echo $current_step === 'import' ? 'color: #0073aa; font-weight: bold;' : 'color: #666;'; ?>">
					📥 Importação para WordPress
					<?php if ( $current_step === 'complete' ) echo '✅'; ?>
				</li>
				<li style="<?php echo $current_step === 'finalize' ? 'color: #0073aa; font-weight: bold;' : 'color: #666;'; ?>">
					🔨 Reconstrução de Hierarquia
					<?php if ( $current_step === 'complete' ) echo '✅'; ?>
				</li>
				<li style="<?php echo $current_step === 'finalize' ? 'color: #0073aa; font-weight: bold;' : 'color: #666;'; ?>">
					🔗 Conexão de Traduções
					<?php if ( $current_step === 'complete' ) echo '✅'; ?>
				</li>
			</ol>
		</div>
		
		<?php if ( $current_step === 'upload' ) : ?>
			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2>📤 Passo 1: Upload do XML qTranslate-XT</h2>
				<p>Faça upload do arquivo XML exportado do site original com qTranslate-XT.</p>
				
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'qtranslate_xt_unified_import', 'qxt_unified_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="wxr_file">Arquivo XML:</label></th>
							<td>
								<input type="file" id="wxr_file" name="wxr_file" accept=".xml" required />
								<p class="description">Arquivo WXR exportado do WordPress com conteúdo qTranslate-XT</p>
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" name="submit" id="submit" class="button button-primary" value="Processar XML → Próximo Passo">
					</p>
				</form>
			</div>
		
		<?php elseif ( $current_step === 'import' ) : ?>
			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2>📥 Passo 2: Importar para WordPress</h2>
				<p>O XML foi processado. Clique abaixo para importar para o WordPress.</p>
				
				<div class="notice notice-info">
					<p><strong>Conteúdo processado:</strong> Posts e páginas separados por idioma com metadados para Polylang.</p>
				</div>
				
				<?php if ( $import_result && isset( $import_result['skipped'] ) && $import_result['skipped'] > 0 ) : ?>
					<div class="notice notice-warning">
						<p><strong>Atenção:</strong> <?php echo $import_result['skipped']; ?> posts foram ignorados por já existirem.</p>
						<p>Use a opção "Forçar Importação" para importar todos os posts (pode criar duplicatas).</p>
					</div>
				<?php endif; ?>
				
				<form method="post">
					<?php wp_nonce_field( 'qtranslate_xt_unified_import', 'qxt_unified_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row">Opções de Importação</th>
							<td>
								<label>
									<input type="checkbox" name="force_import" value="1" />
									Forçar Importação (ignorar posts existentes)
								</label>
								<p class="description">Marque esta opção para importar todos os posts, mesmo que já existam no banco de dados.</p>
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" name="wordpress_import" id="wordpress_import" class="button button-primary" value="Importar para WordPress → Próximo Passo">
					</p>
				</form>
			</div>
		
		<?php elseif ( $current_step === 'finalize' ) : ?>
			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2>🔨 Passo 3: Finalizar Migração</h2>
				<p>Execute a reconstrução de hierarquia e conexão de traduções automaticamente.</p>
				
				<div class="notice notice-warning">
					<p><strong>Atenção:</strong> Este processo irá reconstruir a estrutura de páginas e conectar todas as traduções.</p>
				</div>
				
				<form method="post">
					<?php wp_nonce_field( 'qtranslate_xt_unified_import', 'qxt_unified_nonce' ); ?>
					<p class="submit">
						<input type="submit" name="finalize_migration" id="finalize_migration" class="button button-primary" value="Executar Migração Completa">
					</p>
				</form>
			</div>
		
		<?php elseif ( $current_step === 'complete' ) : ?>
			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2>✅ Migração Concluída!</h2>
				<p>Parabéns! Sua migração do qTranslate-XT para Polylang foi concluída com sucesso.</p>
				
				<div class="notice notice-success">
					<p><strong>Próximos passos:</strong></p>
					<ul>
						<li>Verifique a estrutura de páginas no admin</li>
						<li>Teste a navegação multilíngue</li>
						<li>Configure os menus do Polylang</li>
						<li>Verifique os permalinks</li>
					</ul>
				</div>
				
				<p>
					<a href="<?php echo admin_url( 'edit.php?post_type=page' ); ?>" class="button button-primary">Ver Páginas</a>
					<a href="<?php echo admin_url( 'admin.php?page=mlang' ); ?>" class="button">Configurar Polylang</a>
				</p>
			</div>
		<?php endif; ?>
		
		<div class="card" style="max-width: 800px; margin-top: 20px;">
			<h2>ℹ️ Ajuda</h2>
			<p><strong>Guia Completo:</strong> <a href="<?php echo admin_url('tools.php?page=qtranslate-xt-polylang-guide'); ?>" class="button">Ver Guia Detalhado</a></p>
			<p><strong>Exportação Separada:</strong> <a href="<?php echo admin_url('tools.php?page=qtranslate-xt-polylang'); ?>" class="button">Exportação Avançada</a></p>
		</div>
	</div>
	<?php
}
?>
