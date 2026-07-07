<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', 'qtxpm_handle_import_process' );
add_action( 'admin_menu', 'qtxpm_register_admin_menu' );

function qtxpm_handle_import_process() {
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== qtxpm_get_migration_page_slug() ) {
		return;
	}

	if ( qtxpm_is_upload_request() ) {
		qtxpm_process_uploaded_xml();
	}

	if ( qtxpm_is_import_request() ) {
		qtxpm_process_wordpress_import();
	}

	if ( qtxpm_is_finalize_request() ) {
		qtxpm_finalize_migration();
	}

	if ( qtxpm_is_repair_request() ) {
		qtxpm_repair_translation_duplicates();
	}
}

function qtxpm_register_admin_menu() {
	$labels = qtxpm_get_migration_labels();

	add_management_page(
		$labels['page_title'],
		$labels['menu_title'],
		'manage_options',
		qtxpm_get_migration_page_slug(),
		'qtxpm_render_migration_page'
	);
}

function qtxpm_is_upload_request(): bool {
	return isset( $_POST['submit'], $_POST['qtxpm_migrator_nonce'] ) &&
		wp_verify_nonce( wp_unslash( $_POST['qtxpm_migrator_nonce'] ), 'qtxpm_migration_action' );
}

function qtxpm_is_import_request(): bool {
	return isset( $_POST['wordpress_import'], $_POST['qtxpm_migrator_nonce'] ) &&
		wp_verify_nonce( wp_unslash( $_POST['qtxpm_migrator_nonce'] ), 'qtxpm_migration_action' );
}

function qtxpm_is_finalize_request(): bool {
	return isset( $_POST['finalize_migration'], $_POST['qtxpm_migrator_nonce'] ) &&
		wp_verify_nonce( wp_unslash( $_POST['qtxpm_migrator_nonce'] ), 'qtxpm_migration_action' );
}

function qtxpm_is_repair_request(): bool {
	return isset( $_POST['repair_translation_duplicates'], $_POST['qtxpm_migrator_nonce'] ) &&
		wp_verify_nonce( wp_unslash( $_POST['qtxpm_migrator_nonce'] ), 'qtxpm_migration_action' );
}

function qtxpm_process_uploaded_xml(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Acesso negado.', 'qtx-polylang-migrator' ) );
	}

	if ( empty( $_FILES['wxr_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['wxr_file']['tmp_name'] ) ) {
		wp_die( esc_html__( 'Por favor, envie um arquivo WXR valido.', 'qtx-polylang-migrator' ) );
	}

	$file = $_FILES['wxr_file']['tmp_name'];
	$site_languages = qtxpm_get_polylang_languages();
	if ( empty( $site_languages ) ) {
		$site_languages = qtxpm_get_sorted_languages();
	}

	$default_lang = function_exists( 'pll_default_language' ) ? (string) pll_default_language() : '';
	$doc = new DOMDocument();
	$doc->preserveWhiteSpace = false;
	$doc->formatOutput = true;

	libxml_use_internal_errors( true );
	if ( ! @$doc->load( $file ) ) {
		wp_die( esc_html__( 'Erro ao ler o arquivo XML. Verifique se e uma exportacao WXR do WordPress valida.', 'qtx-polylang-migrator' ) );
	}
	libxml_clear_errors();

	$languages = qtxpm_detect_wxr_languages( $doc, $site_languages, $default_lang );
	if ( '' === $default_lang ) {
		$default_lang = qtxpm_normalize_language_code( in_array( 'pt', $languages, true ) ? 'pt' : ( $languages[0] ?? 'en' ) );
	}

	qtxpm_ensure_polylang_languages( $languages, $default_lang );
	$processed_content = qtxpm_process_wxr_content( $doc, $languages, $default_lang );
	set_transient( qtxpm_get_migration_transient_key( 'staged_xml' ), $processed_content, 3600 );
	qtxpm_redirect_to_step( 'import' );
}

function qtxpm_process_wordpress_import(): void {
	$processed_xml = get_transient( qtxpm_get_migration_transient_key( 'staged_xml' ) );
	if ( ! $processed_xml ) {
		wp_die( esc_html__( 'Nenhum XML processado encontrado. Por favor, processe um arquivo primeiro.', 'qtx-polylang-migrator' ) );
	}

	$temp_file = wp_tempnam();
	file_put_contents( $temp_file, $processed_xml );

	if ( ! class_exists( 'WP_Importer' ) ) {
		require_once ABSPATH . 'wp-admin/includes/import.php';
	}

	$force_import = isset( $_POST['force_import'] ) && '1' === $_POST['force_import'];
	$import_result = array(
		'success' => false,
		'message' => '',
	);

	try {
		$import_result = qtxpm_direct_xml_import( $temp_file, $force_import );
		set_transient( qtxpm_get_migration_transient_key( 'import_report' ), $import_result, 3600 );
	} catch ( Exception $e ) {
		$import_result = array(
			'success' => false,
			'message' => esc_html__( 'Erro na importacao:', 'qtx-polylang-migrator' ) . ' ' . $e->getMessage(),
		);
		set_transient( qtxpm_get_migration_transient_key( 'import_report' ), $import_result, 3600 );
	}

	unlink( $temp_file );

	if ( $import_result['success'] ) {
		$hierarchy_result = qtxpm_rebuild_hierarchy_process();
		$import_result['hierarchy'] = $hierarchy_result;
		set_transient( qtxpm_get_migration_transient_key( 'import_report' ), $import_result, 3600 );

		set_transient(
			qtxpm_get_migration_transient_key( 'migration_results' ),
			array(
				'hierarchy'  => $hierarchy_result,
				'connection' => qtxpm_connect_translations_process(),
			),
			3600
		);
	} else {
		delete_transient( qtxpm_get_migration_transient_key( 'migration_results' ) );
	}

	qtxpm_redirect_to_step( 'results' );
}

function qtxpm_finalize_migration(): void {
	$results = array(
		'hierarchy'  => qtxpm_rebuild_hierarchy_process(),
		'connection' => qtxpm_connect_translations_process(),
	);

	set_transient( qtxpm_get_migration_transient_key( 'migration_results' ), $results, 3600 );
	qtxpm_redirect_to_step( 'results' );
}

function qtxpm_repair_translation_duplicates(): void {
	$existing_results = get_transient( qtxpm_get_migration_transient_key( 'migration_results' ) );
	$results = is_array( $existing_results ) ? $existing_results : array();
	$results['repair'] = qtxpm_connect_translations_process();

	if ( ! isset( $results['hierarchy'] ) ) {
		$results['hierarchy'] = array(
			'success' => true,
			'message' => esc_html__( 'Sem alteracoes na hierarquia nesta execucao de reparo.', 'qtx-polylang-migrator' ),
		);
	}

	$results['connection'] = $results['repair'];
	set_transient( qtxpm_get_migration_transient_key( 'migration_results' ), $results, 3600 );
	qtxpm_redirect_to_step( 'results' );
}

function qtxpm_redirect_to_step( string $step ): void {
	wp_safe_redirect( admin_url( 'tools.php?page=' . qtxpm_get_migration_page_slug() . '&step=' . $step ) );
	exit;
}
