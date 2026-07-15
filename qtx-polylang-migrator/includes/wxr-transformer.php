<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical namespace URIs for the WXR prefixes this transformer relies on.
 *
 * Used to pre-register these prefixes on every `DOMXPath` built for a WXR
 * document, regardless of whether the source XML actually declares them on
 * its `<rss>` root element. Real-world WXR exports commonly omit a prefix's
 * `xmlns:*` declaration entirely when the corresponding elements are simply
 * unused (e.g. no `<excerpt:encoded>` anywhere because no post has an
 * excerpt) - which is perfectly well-formed XML, but leaves `excerpt`
 * unbound as far as `DOMXPath` is concerned. Querying an expression with an
 * unbound prefix makes `DOMXPath::query()` return `false` (not an empty
 * node list), which crashes on the very common `->item( 0 )` chaining used
 * throughout this file. Pre-binding the canonical URIs sidesteps that: the
 * prefix always resolves to *some* URI, so queries against genuinely absent
 * elements correctly return an empty (but truthy) node list instead of
 * `false`.
 *
 * @return array<string, string> Prefix => canonical namespace URI.
 */
function qtxpm_wxr_default_namespaces(): array {
	return array(
		'wp'      => 'http://wordpress.org/export/1.2/',
		'content' => 'http://purl.org/rss/1.0/modules/content/',
		'excerpt' => 'http://wordpress.org/export/1.2/excerpt/',
		'dc'      => 'http://purl.org/dc/elements/1.1/',
	);
}

/**
 * Build a `DOMXPath` for a WXR document with the standard `wp:`/`content:`/
 * `excerpt:`/`dc:` prefixes always registered against their canonical
 * namespace URIs, then overridden by whatever the document itself actually
 * declares (in case of a non-standard mapping). See
 * `qtxpm_wxr_default_namespaces()` for why the canonical fallback is needed.
 *
 * @param DOMDocument $doc DOM document loaded with WXR content.
 * @return DOMXPath
 */
function qtxpm_build_wxr_xpath( DOMDocument $doc ): DOMXPath {
	$xpath = new DOMXPath( $doc );

	foreach ( qtxpm_wxr_default_namespaces() as $prefix => $uri ) {
		$xpath->registerNamespace( $prefix, $uri );
	}

	if ( $doc->documentElement instanceof DOMElement ) {
		foreach ( $doc->documentElement->attributes as $attr ) {
			if ( strpos( $attr->name, 'xmlns:' ) === 0 ) {
				$xpath->registerNamespace( substr( $attr->name, 6 ), $attr->value );
			}
		}
	}

	return $xpath;
}

/**
 * Run an XPath query and safely return its first matching node, or `null`
 * when there is no match *or* when the query itself fails (`query()`
 * returns `false`, e.g. for an unbound namespace prefix). Guards every
 * `->item( 0 )` call site in this file against fataling on `false`.
 *
 * @param DOMXPath      $xpath XPath helper.
 * @param string        $expression XPath expression.
 * @param DOMNode|null  $context_node Context node, or `null` to query the whole document.
 * @return DOMNode|null
 */
function qtxpm_xpath_query_first( DOMXPath $xpath, string $expression, ?DOMNode $context_node = null ): ?DOMNode {
	$result = null !== $context_node ? $xpath->query( $expression, $context_node ) : $xpath->query( $expression );

	if ( false === $result || 0 === $result->length ) {
		return null;
	}

	return $result->item( 0 );
}

/**
 * Run an XPath query and safely return an iterable node list, or an empty
 * array when the query itself fails (`query()` returns `false`). Guards
 * `foreach ( $xpath->query( ... ) as ... )` call sites against fataling.
 *
 * @param DOMXPath      $xpath XPath helper.
 * @param string        $expression XPath expression.
 * @param DOMNode|null  $context_node Context node, or `null` to query the whole document.
 * @return DOMNodeList<DOMNode>|array<int, DOMNode>
 */
function qtxpm_xpath_query_all( DOMXPath $xpath, string $expression, ?DOMNode $context_node = null ) {
	$result = null !== $context_node ? $xpath->query( $expression, $context_node ) : $xpath->query( $expression );

	return false === $result ? array() : $result;
}

/**
 * Process WXR content from qTranslate-XT to Polylang format.
 *
 * @param DOMDocument $doc DOM document loaded with WXR content.
 * @param array       $languages List of enabled languages.
 * @param string      $default_lang Default language code.
 * @return string
 */
function qtxpm_process_wxr_content( DOMDocument $doc, array $languages, string $default_lang ): string {
	$xpath = qtxpm_build_wxr_xpath( $doc );
	$items_data = array();

	foreach ( $doc->getElementsByTagName( 'item' ) as $item ) {
		$wp_post_id = qtxpm_xpath_query_first( $xpath, 'wp:post_id', $item );
		$wp_post_parent = qtxpm_xpath_query_first( $xpath, 'wp:post_parent', $item );
		$wp_post_type = qtxpm_xpath_query_first( $xpath, 'wp:post_type', $item );
		$wp_menu_order = qtxpm_xpath_query_first( $xpath, 'wp:menu_order', $item );
		$guid_node = qtxpm_xpath_query_first( $xpath, 'guid', $item );

		$items_data[] = array(
			'element'         => $item,
			'original_id'     => $wp_post_id ? (int) $wp_post_id->textContent : 0,
			'original_parent' => $wp_post_parent ? (int) $wp_post_parent->textContent : 0,
			'post_type'       => $wp_post_type ? $wp_post_type->textContent : 'post',
			'menu_order'      => $wp_menu_order ? (int) $wp_menu_order->textContent : 0,
			'guid'            => $guid_node ? $guid_node->textContent : '',
		);
	}

	$sorted_items = qtxpm_sort_items_by_hierarchy( $items_data );
	$processed_items = array();
	$group_counter = 1;

	foreach ( $sorted_items as $item_data ) {
		$item = $item_data['element'];
		$title_node = qtxpm_xpath_query_first( $xpath, 'title', $item );
		$content_node = qtxpm_xpath_query_first( $xpath, 'content:encoded', $item );
		$excerpt_node = qtxpm_xpath_query_first( $xpath, 'excerpt:encoded', $item );

		$title_text = $title_node ? $title_node->textContent : '';
		$content_text = $content_node ? $content_node->textContent : '';
		$excerpt_text = $excerpt_node ? $excerpt_node->textContent : '';

		$is_multilingual = qtxpm_is_multilingual_text( $content_text ) ||
			qtxpm_is_multilingual_text( $title_text ) ||
			qtxpm_is_multilingual_text( $excerpt_text );

		if ( $is_multilingual ) {
			$title_by_lang = qtxpm_split_multilingual_text( $title_text, $languages );
			$content_by_lang = qtxpm_split_multilingual_text( $content_text, $languages );
			$excerpt_by_lang = qtxpm_split_multilingual_text( $excerpt_text, $languages );
			$item_languages = array();

			foreach ( $languages as $lang ) {
				$has_language_variant = qtxpm_has_language_value( $title_by_lang, $lang, $languages ) ||
					qtxpm_has_language_value( $content_by_lang, $lang, $languages ) ||
					qtxpm_has_language_value( $excerpt_by_lang, $lang, $languages );

				if ( $has_language_variant ) {
					$item_languages[] = $lang;
				}
			}

			if ( empty( $item_languages ) ) {
				$item_languages[] = $default_lang;
			}

			foreach ( $item_languages as $lang ) {
				$new_item = $item->cloneNode( true );
				$new_title = qtxpm_xpath_query_first( $xpath, 'title', $new_item );
				$new_content = qtxpm_xpath_query_first( $xpath, 'content:encoded', $new_item );
				$new_excerpt = qtxpm_xpath_query_first( $xpath, 'excerpt:encoded', $new_item );
				$wp_post_parent_node = qtxpm_xpath_query_first( $xpath, 'wp:post_parent', $new_item );

				if ( $new_title ) {
					$new_title->textContent = qtxpm_get_language_value( $title_by_lang, $lang, $default_lang, $title_text, $languages );
				}

				if ( $new_content ) {
					$new_content->textContent = qtxpm_get_language_value( $content_by_lang, $lang, $default_lang, $content_text, $languages );
				}

				if ( $new_excerpt ) {
					$new_excerpt->textContent = qtxpm_get_language_value( $excerpt_by_lang, $lang, $default_lang, '', $languages );
				}

				qtxpm_split_postmeta_for_language( $xpath, $new_item, $lang, $default_lang, $languages );

				$cat_element = $doc->createElement( 'category' );
				$cat_element->setAttribute( 'domain', 'language' );
				$cat_element->setAttribute( 'nicename', $lang );
				$cat_element->textContent = $lang;
				$new_item->appendChild( $cat_element );

				if ( $wp_post_parent_node ) {
					$wp_post_parent_node->textContent = '0';
				}

				qtxpm_add_migration_meta(
					$doc,
					$new_item,
					array(
						'_pll_migration_group'       => $group_counter,
						'_pll_migration_lang'        => $lang,
						'_pll_migration_original_id' => $item_data['original_id'],
						'_pll_migration_parent_id'   => $item_data['original_parent'],
						'_pll_migration_menu_order'  => $item_data['menu_order'],
						'_pll_migration_guid'        => $item_data['guid'],
					)
				);

				$processed_items[] = array(
					'element'         => $new_item,
					'original_id'     => $item_data['original_id'],
					'original_parent' => $item_data['original_parent'],
					'lang'            => $lang,
					'menu_order'      => $item_data['menu_order'],
				);
			}

			$group_counter++;
			continue;
		}

		qtxpm_split_postmeta_for_language( $xpath, $item, $default_lang, $default_lang, $languages );

		$cat_element = $doc->createElement( 'category' );
		$cat_element->setAttribute( 'domain', 'language' );
		$cat_element->setAttribute( 'nicename', $default_lang );
		$cat_element->textContent = $default_lang;
		$item->appendChild( $cat_element );

		$wp_post_parent_node = qtxpm_xpath_query_first( $xpath, 'wp:post_parent', $item );
		if ( $wp_post_parent_node ) {
			$wp_post_parent_node->textContent = '0';
		}

		qtxpm_add_migration_meta(
			$doc,
			$item,
			array(
				'_pll_migration_lang'        => $default_lang,
				'_pll_migration_original_id' => $item_data['original_id'],
				'_pll_migration_parent_id'   => $item_data['original_parent'],
				'_pll_migration_menu_order'  => $item_data['menu_order'],
				'_pll_migration_guid'        => $item_data['guid'],
			)
		);

		$processed_items[] = array(
			'element'         => $item->cloneNode( true ),
			'original_id'     => $item_data['original_id'],
			'original_parent' => $item_data['original_parent'],
			'lang'            => $default_lang,
			'menu_order'      => $item_data['menu_order'],
		);
	}

	$channel = $doc->getElementsByTagName( 'channel' )->item( 0 );
	$items_to_remove = array();
	foreach ( $channel->childNodes as $node ) {
		if ( $node->nodeName === 'item' ) {
			$items_to_remove[] = $node;
		}
	}

	foreach ( $items_to_remove as $node ) {
		$channel->removeChild( $node );
	}

	foreach ( $processed_items as $item_data ) {
		$channel->appendChild( $doc->importNode( $item_data['element'], true ) );
	}

	return $doc->saveXML();
}

/**
 * Split multilingual `wp:postmeta` values of a (possibly cloned) item into
 * the value for a single target language.
 *
 * The transformer clones each multilingual item once per language, but
 * `wp:postmeta` nodes are cloned verbatim; without this pass, a meta value
 * such as `[:en]English[:pt]Portugues[:]` would be imported unmodified into
 * every per-language clone. PHP-serialized values (`a:`, `O:`, `s:` prefixes)
 * are left untouched, since splitting them on language markers would corrupt
 * the serialized structure; `qtxpm_direct_xml_import()` surfaces a warning
 * for those instead.
 *
 * @param DOMXPath $xpath XPath helper bound to the document (with wp:/content:/excerpt: namespaces registered).
 * @param DOMNode  $item Item node (already cloned for the target language) to mutate in place.
 * @param string   $lang Target language for this item/clone.
 * @param string   $default_lang Site/migration default language, used as fallback.
 * @param array    $languages Destination languages configured for the migration.
 * @return void
 */
function qtxpm_split_postmeta_for_language( DOMXPath $xpath, DOMNode $item, string $lang, string $default_lang, array $languages ): void {
	foreach ( qtxpm_xpath_query_all( $xpath, 'wp:postmeta', $item ) as $meta_node ) {
		$value_node = qtxpm_xpath_query_first( $xpath, 'wp:meta_value', $meta_node );

		if ( ! $value_node ) {
			continue;
		}

		$meta_text = $value_node->textContent;

		if ( ! qtxpm_is_multilingual_text( $meta_text ) ) {
			continue;
		}

		if ( qtxpm_is_serialized_meta_value( $meta_text ) ) {
			continue;
		}

		$meta_by_lang = qtxpm_split_multilingual_text( $meta_text, $languages );
		$value_node->textContent = qtxpm_get_language_value( $meta_by_lang, $lang, $default_lang, $meta_text, $languages );
	}
}

/**
 * Sort items by hierarchy (depth-first: each root/parent immediately
 * followed by its children, recursively, siblings ordered by menu_order
 * then original_id).
 *
 * Builds a parent_id => children index once (O(n)), so each item is
 * looked up in O(1) amortized instead of re-scanning the full item list
 * for every node as the previous O(n^2) implementation did.
 *
 * @param array $items Array of items with their data.
 * @return array
 */
function qtxpm_sort_items_by_hierarchy( array $items ): array {
	$children_by_parent = qtxpm_index_items_by_parent( $items );

	$sorted = array();
	foreach ( $children_by_parent[0] ?? array() as $root_item ) {
		$sorted[] = $root_item;
		qtxpm_add_children_to_sorted( $children_by_parent, (int) $root_item['original_id'], $sorted );
	}

	return $sorted;
}

/**
 * Group items by their `original_parent`, with each group pre-sorted by
 * menu_order (ties broken by original_id) so both the root pass and every
 * recursive child pass in `qtxpm_add_children_to_sorted()` can consume the
 * bucket directly without re-sorting or re-scanning all items.
 *
 * @param array $items Array of items with their data.
 * @return array<int, array>
 */
function qtxpm_index_items_by_parent( array $items ): array {
	$index = array();

	foreach ( $items as $item ) {
		$parent_id = (int) $item['original_parent'];
		$index[ $parent_id ][] = $item;
	}

	$comparator = static function ( array $item_a, array $item_b ): int {
		if ( $item_a['menu_order'] === $item_b['menu_order'] ) {
			return $item_a['original_id'] - $item_b['original_id'];
		}

		return $item_a['menu_order'] - $item_b['menu_order'];
	};

	foreach ( $index as $parent_id => $children ) {
		usort( $children, $comparator );
		$index[ $parent_id ] = $children;
	}

	return $index;
}

/**
 * Add children of a parent item to sorted array recursively.
 *
 * @param array<int, array> $children_by_parent Parent_id => pre-sorted children index built by `qtxpm_index_items_by_parent()`.
 * @param int                $parent_id Parent ID to find children for.
 * @param array              $sorted Reference to sorted array to append children to.
 * @return void
 */
function qtxpm_add_children_to_sorted( array $children_by_parent, int $parent_id, array &$sorted ): void {
	foreach ( $children_by_parent[ $parent_id ] ?? array() as $child ) {
		$sorted[] = $child;
		qtxpm_add_children_to_sorted( $children_by_parent, (int) $child['original_id'], $sorted );
	}
}

/**
 * Add migration metadata to an item.
 *
 * @param DOMDocument $doc DOM document.
 * @param DOMNode     $item Item element to add meta to.
 * @param array       $meta_data Associative array of meta_key => meta_value.
 * @return void
 */
function qtxpm_add_migration_meta( DOMDocument $doc, DOMNode $item, array $meta_data ): void {
	foreach ( $meta_data as $meta_key => $meta_value ) {
		$meta_element = $doc->createElement( 'wp:postmeta' );
		$meta_key_element = $doc->createElement( 'wp:meta_key' );
		$meta_value_element = $doc->createElement( 'wp:meta_value' );

		$meta_key_element->appendChild( $doc->createTextNode( (string) $meta_key ) );
		$meta_value_element->appendChild( $doc->createTextNode( (string) $meta_value ) );
		$meta_element->appendChild( $meta_key_element );
		$meta_element->appendChild( $meta_value_element );
		$item->appendChild( $meta_element );
	}
}

/**
 * Return equivalent language aliases used by legacy qTranslate content.
 *
 * @param string $language Language code from destination or WXR.
 * @param array  $target_languages Destination languages configured for the migration.
 * @return string[]
 */
function qtxpm_get_language_aliases( string $language, array $target_languages = array() ): array {
	$language = strtolower( trim( $language ) );
	$target_languages = array_values(
		array_unique(
			array_filter(
				array_map(
					static function ( mixed $target_language ): string {
						return strtolower( trim( (string) $target_language ) );
					},
					$target_languages
				)
			)
		)
	);

	if ( $language === '' ) {
		return array();
	}

	$aliases = array( $language );
	$legacy_aliases = array(
		'pt' => array( 'pb' ),
		'pb' => array( 'pt' ),
	);

	if ( isset( $legacy_aliases[ $language ] ) ) {
		$aliases = array_merge( $aliases, $legacy_aliases[ $language ] );
	}

	return array_values( array_unique( $aliases ) );
}

/**
 * Check whether a multilingual split contains content for a language or alias.
 *
 * @param array<string, string> $values Split multilingual values keyed by language code.
 * @param string                $language Destination language code.
 * @param array                 $target_languages Destination languages configured for the migration.
 * @return bool
 */
function qtxpm_has_language_value( array $values, string $language, array $target_languages = array() ): bool {
	foreach ( qtxpm_get_language_aliases( $language, $target_languages ) as $candidate ) {
		if ( isset( $values[ $candidate ] ) && trim( (string) $values[ $candidate ] ) !== '' ) {
			return true;
		}
	}

	return false;
}

/**
 * Resolve multilingual content for a destination language using legacy aliases when needed.
 *
 * @param array<string, string> $values Split multilingual values keyed by language code.
 * @param string                $language Destination language code.
 * @param string                $default_language Default language code.
 * @param string                $fallback Fallback plain text when no language-specific content exists.
 * @param array                 $target_languages Destination languages configured for the migration.
 * @return string
 */
function qtxpm_get_language_value( array $values, string $language, string $default_language, string $fallback = '', array $target_languages = array() ): string {
	foreach ( qtxpm_get_language_aliases( $language, $target_languages ) as $candidate ) {
		if ( isset( $values[ $candidate ] ) && trim( (string) $values[ $candidate ] ) !== '' ) {
			return (string) $values[ $candidate ];
		}
	}

	foreach ( qtxpm_get_language_aliases( $default_language, $target_languages ) as $candidate ) {
		if ( isset( $values[ $candidate ] ) && trim( (string) $values[ $candidate ] ) !== '' ) {
			return (string) $values[ $candidate ];
		}
	}

	foreach ( $values as $value ) {
		if ( trim( (string) $value ) !== '' ) {
			return (string) $value;
		}
	}

	return $fallback;
}

/**
 * Detect multilingual language codes present in a WXR document.
 *
 * @param DOMDocument $doc WXR DOM document.
 * @param array       $fallback_languages Site-configured fallback languages.
 * @param string      $default_lang Site default language.
 * @return string[]
 */
function qtxpm_detect_wxr_languages( DOMDocument $doc, array $fallback_languages, string $default_lang ): array {
	$detected_languages = array();

	foreach ( $doc->getElementsByTagName( 'item' ) as $item ) {
		foreach ( array( 'title', 'encoded' ) as $tag_name ) {
			foreach ( $item->getElementsByTagName( $tag_name ) as $node ) {
				$values = qtxpm_split_multilingual_text( $node->textContent );
				foreach ( $values as $language => $value ) {
					$language = strtolower( trim( (string) $language ) );
					if ( $language === '' || trim( (string) $value ) === '' ) {
						continue;
					}

					$detected_languages[ $language ] = true;
				}
			}
		}
	}

	if ( empty( $detected_languages ) ) {
		$languages = array_values( array_filter( array_map( 'strtolower', $fallback_languages ) ) );
		if ( $default_lang !== '' && ! in_array( $default_lang, $languages, true ) ) {
			$languages[] = $default_lang;
		}

		return array_values( array_unique( $languages ) );
	}

	$resolved_languages = array();
	foreach ( $fallback_languages as $fallback_language ) {
		$fallback_language = strtolower( trim( (string) $fallback_language ) );
		if ( $fallback_language === '' ) {
			continue;
		}

		$matched_language = false;
		foreach ( qtxpm_get_language_aliases( $fallback_language, $fallback_languages ) as $candidate ) {
			if ( empty( $detected_languages[ $candidate ] ) ) {
				continue;
			}

			$matched_language = true;
			unset( $detected_languages[ $candidate ] );
		}

		if ( $matched_language ) {
			$resolved_languages[] = $fallback_language;
		}
	}

	foreach ( array_keys( $detected_languages ) as $language ) {
		$resolved_languages[] = $language;
	}

	if ( $default_lang !== '' && ! in_array( $default_lang, $resolved_languages, true ) ) {
		$resolved_languages[] = $default_lang;
	}

	return array_values( array_unique( $resolved_languages ) );
}

/**
 * Extract item language from a processed WXR item.
 *
 * @param SimpleXMLElement $item WXR item.
 * @return string
 */
function qtxpm_get_wxr_item_language( SimpleXMLElement $item ): string {
	foreach ( $item->category as $category ) {
		$domain = strtolower( (string) $category['domain'] );
		if ( 'language' !== $domain ) {
			continue;
		}

		$language = strtolower( (string) $category['nicename'] );
		if ( '' === $language ) {
			$language = strtolower( trim( (string) $category ) );
		}

		return $language;
	}

	return '';
}

/**
 * Read a migration meta value from a WXR item.
 *
 * @param SimpleXMLElement $item WXR item.
 * @param string           $meta_key Requested migration meta key.
 * @return string
 */
function qtxpm_get_wxr_meta_value( SimpleXMLElement $item, string $meta_key ): string {
	$namespaces = $item->getNamespaces( true );

	if ( empty( $namespaces['wp'] ) ) {
		return '';
	}

	$wp_item = $item->children( $namespaces['wp'] );
	if ( ! isset( $wp_item->postmeta ) ) {
		return '';
	}

	foreach ( $wp_item->postmeta as $meta ) {
		if ( (string) $meta->meta_key === $meta_key ) {
			return (string) $meta->meta_value;
		}
	}

	return '';
}

/**
 * Build a duplicate-detection index from posts that already existed before import.
 *
 * @param array $posts Existing posts from the database.
 * @return array<string, array<string, bool>>
 */
function qtxpm_build_existing_post_index( array $posts ): array {
	$index = array(
		'title' => array(),
		'guid'  => array(),
	);

	foreach ( $posts as $post ) {
		$post_type = isset( $post->post_type ) ? (string) $post->post_type : '';
		if ( '' === $post_type ) {
			continue;
		}

		$title = isset( $post->post_title ) ? trim( (string) $post->post_title ) : '';
		$guid = isset( $post->guid ) ? trim( (string) $post->guid ) : '';

		if ( '' !== $title ) {
			$index['title'][ $post_type . '|' . $title ] = true;
		}

		if ( '' !== $guid ) {
			$index['guid'][ $post_type . '|' . $guid ] = true;
		}
	}

	return $index;
}

/**
 * Check whether a post matches a pre-import duplicate index.
 *
 * @param array<string, array<string, bool>> $existing_post_index Pre-import duplicate index.
 * @param array<string, string>              $post_data Current post data to test.
 * @return bool
 */
function qtxpm_post_exists_in_import_index( array $existing_post_index, array $post_data ): bool {
	$post_type = isset( $post_data['post_type'] ) ? (string) $post_data['post_type'] : '';
	$post_title = isset( $post_data['post_title'] ) ? trim( (string) $post_data['post_title'] ) : '';
	$guid = isset( $post_data['guid'] ) ? trim( (string) $post_data['guid'] ) : '';

	if ( '' === $post_type ) {
		return false;
	}

	if ( '' !== $post_title && ! empty( $existing_post_index['title'][ $post_type . '|' . $post_title ] ) ) {
		return true;
	}

	if ( '' !== $guid && ! empty( $existing_post_index['guid'][ $post_type . '|' . $guid ] ) ) {
		return true;
	}

	return false;
}

/**
 * Build a post mapping keyed by original ID and language.
 *
 * @param array $posts List of imported posts with migration metadata.
 * @return array<int, array<string, int>>
 */
function qtxpm_build_original_post_language_map( array $posts ): array {
	$map = array();

	foreach ( $posts as $post ) {
		$original_id = isset( $post->original_id ) ? (int) $post->original_id : 0;
		$post_id = isset( $post->ID ) ? (int) $post->ID : 0;
		$lang = isset( $post->lang ) && is_string( $post->lang ) ? $post->lang : '';

		if ( $original_id <= 0 || $post_id <= 0 ) {
			continue;
		}

		if ( ! isset( $map[ $original_id ] ) ) {
			$map[ $original_id ] = array();
		}

		if ( '' !== $lang ) {
			$map[ $original_id ][ $lang ] = $post_id;
		}

		$map[ $original_id ]['*'] = $post_id;
	}

	return $map;
}

/**
 * Resolve the best parent post ID for a given original parent and language.
 *
 * @param array<int, array<string, int>> $post_map Post mapping keyed by original ID.
 * @param int                            $original_parent_id Original parent ID from WXR.
 * @param string                         $language Language of the child post.
 * @return int
 */
function qtxpm_resolve_parent_post_id( array $post_map, int $original_parent_id, string $language = '' ): int {
	if ( $original_parent_id <= 0 || ! isset( $post_map[ $original_parent_id ] ) ) {
		return 0;
	}

	if ( '' !== $language && isset( $post_map[ $original_parent_id ][ $language ] ) ) {
		return (int) $post_map[ $original_parent_id ][ $language ];
	}

	if ( isset( $post_map[ $original_parent_id ]['*'] ) ) {
		return (int) $post_map[ $original_parent_id ]['*'];
	}

	$first_parent = reset( $post_map[ $original_parent_id ] );

	return $first_parent ? (int) $first_parent : 0;
}
