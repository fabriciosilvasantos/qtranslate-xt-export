<?php

class PLL_Model {
    /**
     * @return array<mixed>
     */
    public function get_languages_list() {
        return array_values( $GLOBALS['qtx_polylang_languages'] ?? array() );
    }

    /**
     * Returns a language object from a slug or locale.
     *
     * @param string $value Language slug or locale.
     * @return object|false Language object, or false if not found.
     */
    public function get_language( string $value ) {
        $languages = $GLOBALS['qtx_polylang_languages'] ?? array();

        return $languages[ $value ] ?? false;
    }

    /**
     * Adds a language to the stubbed Polylang registry.
     *
     * @param array $args Language data.
     * @return object
     */
    public function add_language( array $args ) {
        $slug = strtolower( trim( (string) ( $args['slug'] ?? '' ) ) );
        if ( '' === $slug ) {
            $slug = strtolower( substr( (string) ( $args['locale'] ?? 'xx' ), 0, 2 ) );
        }

        $language = (object) array(
            'slug'   => $slug,
            'locale' => (string) ( $args['locale'] ?? $slug . '_' . strtoupper( $slug ) ),
            'name'   => (string) ( $args['name'] ?? strtoupper( $slug ) ),
            'flag'   => (string) ( $args['flag'] ?? $slug ),
            'rtl'    => ! empty( $args['rtl'] ),
        );

        $GLOBALS['qtx_polylang_languages'][ $slug ] = $language;
        $GLOBALS['qtx_polylang_added_languages'][] = $slug;

        if ( empty( $GLOBALS['qtx_polylang_default_language'] ) ) {
            $GLOBALS['qtx_polylang_default_language'] = $slug;
        }

        return $language;
    }
}

class PLL {
    /** @var PLL_Model */
    public $model;

    public function __construct() {
        $this->model = new PLL_Model();
    }
}

/**
 * @return PLL
 */
function PLL() {
    if ( isset( $GLOBALS['qtx_polylang_instance'] ) && is_object( $GLOBALS['qtx_polylang_instance'] ) ) {
        return $GLOBALS['qtx_polylang_instance'];
    }

    return new PLL();
}

if ( ! function_exists( 'pll_set_post_language' ) ) {
	function pll_set_post_language( int $post_id, string $language_code ) {
		$GLOBALS['qtx_polylang_post_languages'][ $post_id ] = $language_code;

		return true;
	}
}

if ( ! function_exists( 'pll_get_post_language' ) ) {
	function pll_get_post_language( int $post_id, $field = 'slug' ) {
		return $GLOBALS['qtx_polylang_post_languages'][ $post_id ] ?? '';
	}
}

if ( ! function_exists( 'pll_languages_list' ) ) {
	function pll_languages_list( array $args = array() ) {
		$field = $args['fields'] ?? '';
		$languages = array_values( $GLOBALS['qtx_polylang_languages'] ?? array() );
		$visible_slugs = $GLOBALS['qtx_polylang_visible_language_slugs'] ?? array();

		if ( ! empty( $visible_slugs ) ) {
			$languages = array_values(
				array_filter(
					$languages,
					static function ( $language ) use ( $visible_slugs ) {
						return isset( $language->slug ) && in_array( $language->slug, (array) $visible_slugs, true );
					}
				)
			);
		}

		if ( 'slug' === $field ) {
			return array_map(
				static function ( $language ) {
					return $language->slug;
				},
				$languages
			);
		}

		return $languages;
	}
}

if ( ! function_exists( 'pll_default_language' ) ) {
	function pll_default_language() {
		return $GLOBALS['qtx_polylang_default_language'] ?? '';
	}
}

if ( ! function_exists( 'pll_is_translated_post_type' ) ) {
	function pll_is_translated_post_type( string $post_type ) {
		$translated_post_types = $GLOBALS['qtx_polylang_translated_post_types'] ?? array( 'post', 'page' );

		return in_array( $post_type, $translated_post_types, true );
	}
}

if ( ! function_exists( 'pll_save_post_translations' ) ) {
	function pll_save_post_translations( array $translations ) {
		$GLOBALS['qtx_polylang_saved_translations'][] = $translations;

		return true;
	}
}

if ( ! function_exists( 'pll_set_term_language' ) ) {
	function pll_set_term_language( int $term_id, string $language_code ) {
		$GLOBALS['qtx_polylang_term_languages'][ $term_id ] = $language_code;

		return true;
	}
}

if ( ! function_exists( 'pll_get_term_language' ) ) {
	function pll_get_term_language( int $term_id, $field = 'slug' ) {
		return $GLOBALS['qtx_polylang_term_languages'][ $term_id ] ?? '';
	}
}

if ( ! function_exists( 'pll_save_term_translations' ) ) {
	function pll_save_term_translations( array $translations ) {
		$GLOBALS['qtx_polylang_saved_term_translations'][] = $translations;

		foreach ( $translations as $language => $term_id ) {
			$GLOBALS['qtx_polylang_term_translation_groups'][ (int) $term_id ] = $translations;
		}

		return true;
	}
}

if ( ! function_exists( 'pll_get_term_translations' ) ) {
	function pll_get_term_translations( int $term_id ) {
		if ( isset( $GLOBALS['qtx_polylang_term_translation_groups'][ $term_id ] ) ) {
			return $GLOBALS['qtx_polylang_term_translation_groups'][ $term_id ];
		}

		$language = $GLOBALS['qtx_polylang_term_languages'][ $term_id ] ?? '';

		return '' !== $language ? array( $language => $term_id ) : array();
	}
}

if ( ! function_exists( 'qtx_polylang_stub_reset' ) ) {
	function qtx_polylang_stub_reset(): void {
		$GLOBALS['qtx_polylang_languages'] = array(
			'en' => (object) array( 'slug' => 'en', 'locale' => 'en_US' ),
			'pt' => (object) array( 'slug' => 'pt', 'locale' => 'pt_BR' ),
			'es' => (object) array( 'slug' => 'es', 'locale' => 'es_ES' ),
		);
		$GLOBALS['qtx_polylang_translated_post_types'] = array( 'post', 'page' );
		$GLOBALS['qtx_polylang_default_language'] = 'en';
		$GLOBALS['qtx_polylang_added_languages'] = array();
		$GLOBALS['qtx_polylang_post_languages'] = array();
		$GLOBALS['qtx_polylang_saved_translations'] = array();
		$GLOBALS['qtx_polylang_visible_language_slugs'] = array();
		$GLOBALS['qtx_polylang_instance'] = null;
		$GLOBALS['qtxpm_runtime_polylang_languages'] = array();
		$GLOBALS['qtx_polylang_term_languages'] = array();
		$GLOBALS['qtx_polylang_saved_term_translations'] = array();
		$GLOBALS['qtx_polylang_term_translation_groups'] = array();
		$GLOBALS['qtxpm_runtime_term_groups'] = array();

		// qtxpm_get_polylang_languages() memoizes its result in a `static`
		// local for the lifetime of the PHP process; without invalidating it
		// here, tests running later in the same process would see a stale
		// language list cached by an earlier test. This only clears the
		// cache (lazy) rather than eagerly recomputing it, so tests that set
		// up their own scenario *after* this reset (e.g. simulating a stale
		// public Polylang language list) are not short-circuited by a
		// snapshot taken before their scenario existed.
		if ( function_exists( 'qtxpm_reset_polylang_languages_cache' ) ) {
			qtxpm_reset_polylang_languages_cache();
		}
	}
}

qtx_polylang_stub_reset();
