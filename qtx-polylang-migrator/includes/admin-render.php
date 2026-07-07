<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the standalone migration admin page.
 *
 * @return void
 */
function qtxpm_render_migration_page(): void {
	$current_step = isset( $_GET['step'] ) ? $_GET['step'] : 'upload';
	$import_result = get_transient( qtxpm_get_migration_transient_key( 'import_report' ) );
	$finalization_result = get_transient( qtxpm_get_migration_transient_key( 'migration_results' ) );
	$labels = qtxpm_get_migration_labels();

	if ( 'results' === $current_step ) {
		delete_transient( qtxpm_get_migration_transient_key( 'staged_xml' ) );
		delete_transient( qtxpm_get_migration_transient_key( 'import_report' ) );
		delete_transient( qtxpm_get_migration_transient_key( 'migration_results' ) );
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( $labels['page_title'] ); ?></h1>
		<p><?php echo esc_html__( 'Processo completo em um unico lugar: exportacao, importacao, reconstrucao e conexao.', 'qtx-polylang-migrator' ); ?></p>
		<?php qtxpm_render_import_notices( $import_result ); ?>
		<?php qtxpm_render_finalization_notices( $finalization_result, $current_step ); ?>
		<?php qtxpm_render_progress_card( $current_step ); ?>
		<?php qtxpm_render_step_card( $current_step, $import_result ); ?>
		<?php qtxpm_render_help_card(); ?>
	</div>
	<?php
}

/**
 * Render notices for the direct XML import result.
 *
 * @param array<string, mixed>|false $import_result Import result or false when not available.
 * @return void
 */
function qtxpm_render_import_notices( array|false $import_result ): void {
	if ( ! $import_result ) {
		return;
	}
	?>
	<div class="notice notice-<?php echo $import_result['success'] ? 'success' : 'error'; ?> is-dismissible">
		<p><strong><?php echo esc_html( $import_result['message'] ); ?></strong></p>
	</div>
	<?php if ( ! empty( $import_result['details'] ) ) : ?>
		<div class="card" style="margin-top: 20px;">
			<h3><?php echo esc_html__( 'Detalhes da Importacao', 'qtx-polylang-migrator' ); ?></h3>
			<div style="background: #f9f9f9; padding: 15px; border-radius: 4px; max-height: 300px; overflow-y: auto;">
				<?php foreach ( $import_result['details'] as $detail ) : ?>
					<div style="margin-bottom: 5px; font-family: monospace; font-size: 13px;"><?php echo esc_html( $detail ); ?></div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
	<?php if ( ! empty( $import_result['errors'] ) ) : ?>
		<div class="card" style="margin-top: 20px;">
			<h3><?php echo esc_html__( 'Erros Encontrados', 'qtx-polylang-migrator' ); ?></h3>
			<div style="background: #ffebeb; padding: 15px; border-radius: 4px; max-height: 300px; overflow-y: auto;">
				<?php foreach ( $import_result['errors'] as $error ) : ?>
					<div style="margin-bottom: 5px; font-family: monospace; font-size: 13px; color: #d63638;"><?php echo esc_html( $error ); ?></div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
	<?php
}

/**
 * Render notices for the hierarchy/connection finalization results.
 *
 * @param array<string, mixed>|false $finalization_result Finalization result or false when not available.
 * @param string                     $current_step Current migration step.
 * @return void
 */
function qtxpm_render_finalization_notices( array|false $finalization_result, string $current_step ): void {
	if ( ! $finalization_result || 'results' !== $current_step ) {
		return;
	}
	?>
	<?php if ( isset( $finalization_result['hierarchy'] ) ) : ?>
		<div class="notice notice-<?php echo $finalization_result['hierarchy']['success'] ? 'success' : 'error'; ?> is-dismissible">
			<p><strong><?php echo esc_html__( 'Hierarquia:', 'qtx-polylang-migrator' ); ?></strong> <?php echo esc_html( $finalization_result['hierarchy']['message'] ); ?></p>
		</div>
	<?php endif; ?>
	<?php if ( isset( $finalization_result['connection']['deduplication'] ) ) : ?>
		<div class="notice notice-<?php echo $finalization_result['connection']['deduplication']['success'] ? 'success' : 'warning'; ?> is-dismissible">
			<p><strong><?php echo esc_html__( 'Deduplicacao:', 'qtx-polylang-migrator' ); ?></strong> <?php echo esc_html( $finalization_result['connection']['deduplication']['message'] ); ?></p>
		</div>
	<?php endif; ?>
	<?php if ( isset( $finalization_result['connection'] ) ) : ?>
		<div class="notice notice-<?php echo $finalization_result['connection']['success'] ? 'success' : 'error'; ?> is-dismissible">
			<p><strong><?php echo esc_html__( 'Traducoes:', 'qtx-polylang-migrator' ); ?></strong> <?php echo esc_html( $finalization_result['connection']['message'] ); ?></p>
		</div>
	<?php endif; ?>
	<?php if ( ! empty( $finalization_result['connection']['details'] ) ) : ?>
		<div class="card" style="margin-top: 20px;">
			<h3><?php echo esc_html__( 'Detalhes da Conexao e Reparo', 'qtx-polylang-migrator' ); ?></h3>
			<div style="background: #f9f9f9; padding: 15px; border-radius: 4px; max-height: 300px; overflow-y: auto;">
				<?php foreach ( $finalization_result['connection']['details'] as $detail ) : ?>
					<div style="margin-bottom: 5px; font-family: monospace; font-size: 13px;"><?php echo esc_html( $detail ); ?></div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
	<?php
}

function qtxpm_render_progress_card( string $current_step ): void {
	?>
	<div class="card" style="max-width: 800px; margin-top: 20px;">
		<h2><?php echo esc_html__( 'Progresso da Migracao', 'qtx-polylang-migrator' ); ?></h2>
		<ol style="padding-left: 20px;">
			<li style="<?php echo 'upload' === $current_step ? 'color: #0073aa; font-weight: bold;' : 'color: #666;'; ?>">
				<?php echo esc_html__( 'Upload e Processamento do XML', 'qtx-polylang-migrator' ); ?> <?php if ( 'upload' !== $current_step ) echo 'OK'; ?>
			</li>
			<li style="<?php echo 'import' === $current_step ? 'color: #0073aa; font-weight: bold;' : 'color: #666;'; ?>">
				<?php echo esc_html__( 'Importacao para WordPress', 'qtx-polylang-migrator' ); ?> <?php if ( 'results' === $current_step ) echo 'OK'; ?>
			</li>
			<li style="<?php echo 'finalize' === $current_step ? 'color: #0073aa; font-weight: bold;' : 'color: #666;'; ?>">
				<?php echo esc_html__( 'Reconstrucao de Hierarquia', 'qtx-polylang-migrator' ); ?> <?php if ( 'results' === $current_step ) echo 'OK'; ?>
			</li>
			<li style="<?php echo 'finalize' === $current_step ? 'color: #0073aa; font-weight: bold;' : 'color: #666;'; ?>">
				<?php echo esc_html__( 'Conexao de Traducoes', 'qtx-polylang-migrator' ); ?> <?php if ( 'results' === $current_step ) echo 'OK'; ?>
			</li>
		</ol>
	</div>
	<?php
}

/**
 * Render the card corresponding to the current migration step.
 *
 * @param string                      $current_step Current migration step.
 * @param array<string, mixed>|false $import_result Import result or false when not available.
 * @return void
 */
function qtxpm_render_step_card( string $current_step, array|false $import_result ): void {
	if ( 'upload' === $current_step ) {
		qtxpm_render_upload_card();
		return;
	}

	if ( 'import' === $current_step ) {
		qtxpm_render_wordpress_import_card( $import_result );
		return;
	}

	if ( 'finalize' === $current_step ) {
		qtxpm_render_finalize_card();
		return;
	}

	if ( 'results' === $current_step ) {
		qtxpm_render_results_card();
	}
}

function qtxpm_render_upload_card(): void {
	?>
	<div class="card" style="max-width: 800px; margin-top: 20px;">
		<h2><?php echo esc_html__( 'Passo 1: Upload do XML qTranslate-XT', 'qtx-polylang-migrator' ); ?></h2>
		<p><?php echo esc_html__( 'Faca upload do arquivo XML exportado do site original com qTranslate-XT.', 'qtx-polylang-migrator' ); ?></p>
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'qtxpm_migration_action', 'qtxpm_migrator_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wxr_file"><?php echo esc_html__( 'Arquivo XML:', 'qtx-polylang-migrator' ); ?></label></th>
					<td>
						<input type="file" id="wxr_file" name="wxr_file" accept=".xml" required />
						<p class="description"><?php echo esc_html__( 'Arquivo WXR exportado do WordPress com conteudo qTranslate-XT', 'qtx-polylang-migrator' ); ?></p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__( 'Processar XML -> Proximo Passo', 'qtx-polylang-migrator' ); ?>">
			</p>
		</form>
	</div>
	<?php
}

/**
 * Render the WordPress import step card.
 *
 * @param array<string, mixed>|false $import_result Import result or false when not available.
 * @return void
 */
function qtxpm_render_wordpress_import_card( array|false $import_result ): void {
	?>
	<div class="card" style="max-width: 800px; margin-top: 20px;">
		<h2><?php echo esc_html__( 'Passo 2: Importar para WordPress', 'qtx-polylang-migrator' ); ?></h2>
		<p><?php echo esc_html__( 'O XML foi processado. Clique abaixo para importar para o WordPress.', 'qtx-polylang-migrator' ); ?></p>
		<div class="notice notice-info">
			<p><strong><?php echo esc_html__( 'Conteudo processado:', 'qtx-polylang-migrator' ); ?></strong> <?php echo esc_html__( 'Posts e paginas separados por idioma com metadados para Polylang.', 'qtx-polylang-migrator' ); ?></p>
		</div>
		<?php if ( $import_result && isset( $import_result['skipped'] ) && $import_result['skipped'] > 0 ) : ?>
			<div class="notice notice-warning">
				<p><strong><?php echo esc_html__( 'Atencao:', 'qtx-polylang-migrator' ); ?></strong> <?php printf( esc_html__( '%d posts foram ignorados por ja existirem.', 'qtx-polylang-migrator' ), (int) $import_result['skipped'] ); ?></p>
				<p><?php echo esc_html__( 'Use a opcao "Forcar Importacao" para importar todos os posts (pode criar duplicatas).', 'qtx-polylang-migrator' ); ?></p>
			</div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'qtxpm_migration_action', 'qtxpm_migrator_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php echo esc_html__( 'Opcoes de Importacao', 'qtx-polylang-migrator' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="force_import" value="1" />
							<?php echo esc_html__( 'Forcar Importacao (ignorar posts existentes)', 'qtx-polylang-migrator' ); ?>
						</label>
						<p class="description"><?php echo esc_html__( 'Marque esta opcao para importar todos os posts, mesmo que ja existam no banco de dados.', 'qtx-polylang-migrator' ); ?></p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" name="wordpress_import" id="wordpress_import" class="button button-primary" value="<?php echo esc_attr__( 'Importar para WordPress -> Proximo Passo', 'qtx-polylang-migrator' ); ?>">
			</p>
		</form>
	</div>
	<?php
}

function qtxpm_render_finalize_card(): void {
	?>
	<div class="card" style="max-width: 800px; margin-top: 20px;">
		<h2><?php echo esc_html__( 'Passo 3: Finalizar Migracao', 'qtx-polylang-migrator' ); ?></h2>
		<p><?php echo esc_html__( 'Execute a reconstrucao de hierarquia e conexao de traducoes automaticamente.', 'qtx-polylang-migrator' ); ?></p>
		<div class="notice notice-warning">
			<p><strong><?php echo esc_html__( 'Atencao:', 'qtx-polylang-migrator' ); ?></strong> <?php echo esc_html__( 'Este processo ira reconstruir a estrutura de paginas e conectar todas as traducoes.', 'qtx-polylang-migrator' ); ?></p>
		</div>
		<form method="post">
			<?php wp_nonce_field( 'qtxpm_migration_action', 'qtxpm_migrator_nonce' ); ?>
			<p class="submit">
				<input type="submit" name="finalize_migration" id="finalize_migration" class="button button-primary" value="<?php echo esc_attr__( 'Executar Migracao Completa', 'qtx-polylang-migrator' ); ?>">
			</p>
		</form>
	</div>
	<?php
}

function qtxpm_render_results_card(): void {
	?>
	<div class="card" style="max-width: 800px; margin-top: 20px;">
		<h2><?php echo esc_html__( 'Migracao Concluida!', 'qtx-polylang-migrator' ); ?></h2>
		<p><?php echo esc_html__( 'Parabens! Sua migracao do qTranslate-XT para Polylang foi concluida com sucesso.', 'qtx-polylang-migrator' ); ?></p>
		<div class="notice notice-success">
			<p><strong><?php echo esc_html__( 'Proximos passos:', 'qtx-polylang-migrator' ); ?></strong></p>
			<ul>
				<li><?php echo esc_html__( 'Verifique a estrutura de paginas no admin', 'qtx-polylang-migrator' ); ?></li>
				<li><?php echo esc_html__( 'Teste a navegacao multilingue', 'qtx-polylang-migrator' ); ?></li>
				<li><?php echo esc_html__( 'Configure os menus do Polylang', 'qtx-polylang-migrator' ); ?></li>
				<li><?php echo esc_html__( 'Verifique os permalinks', 'qtx-polylang-migrator' ); ?></li>
			</ul>
		</div>
		<p>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>" class="button button-primary"><?php echo esc_html__( 'Ver Paginas', 'qtx-polylang-migrator' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mlang' ) ); ?>" class="button"><?php echo esc_html__( 'Configurar Polylang', 'qtx-polylang-migrator' ); ?></a>
		</p>
		<hr />
		<h3><?php echo esc_html__( 'Reparar Duplicatas Orfas', 'qtx-polylang-migrator' ); ?></h3>
		<p><?php echo esc_html__( 'Localize duplicatas seguras por idioma, mova orfaos para rascunho e reconecte os grupos no padrao do Polylang.', 'qtx-polylang-migrator' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'qtxpm_migration_action', 'qtxpm_migrator_nonce' ); ?>
			<p class="submit">
				<input type="submit" name="repair_translation_duplicates" id="repair_translation_duplicates" class="button" value="<?php echo esc_attr__( 'Reparar Duplicatas e Reconectar Traducoes', 'qtx-polylang-migrator' ); ?>">
			</p>
		</form>
	</div>
	<?php
}

function qtxpm_render_help_card(): void {
	?>
	<div class="card" style="max-width: 800px; margin-top: 20px;">
		<h2><?php echo esc_html__( 'Ajuda', 'qtx-polylang-migrator' ); ?></h2>
		<p><?php echo esc_html__( 'Use esta tela para processar o XML do qTranslate, importar o conteudo, reconstruir a hierarquia e conectar as traducoes no padrao do Polylang.', 'qtx-polylang-migrator' ); ?></p>
		<p>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>" class="button"><?php echo esc_html__( 'Ver Paginas', 'qtx-polylang-migrator' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mlang' ) ); ?>" class="button"><?php echo esc_html__( 'Configurar Polylang', 'qtx-polylang-migrator' ); ?></a>
		</p>
	</div>
	<?php
}
