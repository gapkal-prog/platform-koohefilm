<?php
/**
 * Global helpers for ManaCore Sources.
 *
 * @package ManaCore\Sources
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'manacore_sources_get_option' ) ) {
	/**
	 * Read a Sources setting.
	 *
	 * @param string $key     Option key (without prefix).
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	function manacore_sources_get_option( $key, $default = '' ) {
		return manacore_get_option( 'src_' . $key, $default );
	}
}

if ( ! function_exists( 'manacore_sources_tmdb_key' ) ) {
	/**
	 * Get the stored TMDB API key.
	 *
	 * @return string
	 */
	function manacore_sources_tmdb_key() {
		$key = (string) manacore_sources_get_option( 'tmdb_key', '' );

		/**
		 * Filter the TMDB API key (useful for wp-config constants).
		 *
		 * @param string $key API key.
		 */
		$key = (string) apply_filters( 'manacore_tmdb_api_key', $key );

		if ( '' === $key && defined( 'MANACORE_TMDB_API_KEY' ) ) {
			$key = (string) MANACORE_TMDB_API_KEY;
		}

		return trim( $key );
	}
}

if ( ! function_exists( 'manacore_sources_has_key' ) ) {
	/**
	 * Whether a TMDB key is configured.
	 *
	 * @return bool
	 */
	function manacore_sources_has_key() {
		return '' !== manacore_sources_tmdb_key();
	}
}

if ( ! function_exists( 'manacore_sources_active_mode' ) ) {
	/**
	 * Resolve the active data mode.
	 *
	 * When a TMDB key exists it is preferred by default, per project spec.
	 * Admin can force "free" mode from the settings screen.
	 *
	 * @return string Either 'tmdb' or 'free'.
	 */
	function manacore_sources_active_mode() {
		$preference = manacore_sources_get_option( 'mode', 'auto' );

		if ( 'free' === $preference ) {
			return 'free';
		}

		if ( 'tmdb' === $preference ) {
			return manacore_sources_has_key() ? 'tmdb' : 'free';
		}

		// auto: key wins when present.
		return manacore_sources_has_key() ? 'tmdb' : 'free';
	}
}

if ( ! function_exists( 'manacore_sources_languages' ) ) {
	/**
	 * TMDB language options.
	 *
	 * @return array
	 */
	function manacore_sources_languages() {
		return apply_filters(
			'manacore_sources_languages',
			array(
				'fa-IR' => __( 'فارسی', 'manacore' ),
				'en-US' => __( 'انگلیسی', 'manacore' ),
				'ar-SA' => __( 'عربی', 'manacore' ),
				'tr-TR' => __( 'ترکی', 'manacore' ),
				'fr-FR' => __( 'فرانسوی', 'manacore' ),
				'de-DE' => __( 'آلمانی', 'manacore' ),
				'es-ES' => __( 'اسپانیایی', 'manacore' ),
				'ja-JP' => __( 'ژاپنی', 'manacore' ),
				'ko-KR' => __( 'کره‌ای', 'manacore' ),
			)
		);
	}
}

if ( ! function_exists( 'manacore_sources_kinds' ) ) {
	/**
	 * Searchable media kinds.
	 *
	 * @return array
	 */
	function manacore_sources_kinds() {
		return array(
			'movie'  => __( 'فیلم', 'manacore' ),
			'series' => __( 'سریال', 'manacore' ),
			'anime'  => __( 'انیمه', 'manacore' ),
		);
	}
}

if ( ! function_exists( 'manacore_sources_kind_for_post_type' ) ) {
	/**
	 * Map a post type to a search kind.
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	function manacore_sources_kind_for_post_type( $post_type ) {
		$map = array(
			'movie'   => 'movie',
			'series'  => 'series',
			'anime'   => 'anime',
			'episode' => 'series',
		);

		return isset( $map[ $post_type ] ) ? $map[ $post_type ] : 'movie';
	}
}
