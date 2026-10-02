<?php
/**
 * Base class shared by every data provider.
 *
 * @package ManaCore\Sources
 */

namespace ManaCore\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract provider.
 *
 * A provider knows how to (a) search a remote catalogue and (b) return the full
 * normalized record for one remote id. Every provider returns data in the same
 * normalized shape so the Importer never needs provider-specific logic.
 */
abstract class Provider {

	/**
	 * Provider id (slug).
	 *
	 * @var string
	 */
	protected $id = '';

	/**
	 * Human readable label.
	 *
	 * @var string
	 */
	protected $label = '';

	/**
	 * Whether this provider needs an API key.
	 *
	 * @var bool
	 */
	protected $requires_key = false;

	/**
	 * Kinds this provider can handle.
	 *
	 * @var array
	 */
	protected $kinds = array( 'movie', 'series', 'anime' );

	/**
	 * Get provider id.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get provider label.
	 *
	 * @return string
	 */
	public function get_label() {
		return $this->label;
	}

	/**
	 * Whether the provider requires an API key.
	 *
	 * @return bool
	 */
	public function requires_key() {
		return (bool) $this->requires_key;
	}

	/**
	 * Whether the provider is usable right now.
	 *
	 * @return bool
	 */
	public function is_available() {
		return true;
	}

	/**
	 * Whether the provider supports a kind.
	 *
	 * @param string $kind Media kind.
	 * @return bool
	 */
	public function supports( $kind ) {
		return in_array( $kind, $this->kinds, true );
	}

	/**
	 * Search the remote catalogue.
	 *
	 * @param string $query Search term.
	 * @param string $kind  Media kind.
	 * @param array  $args  Extra args (year, language...).
	 * @return array|\WP_Error List of normalized search hits.
	 */
	abstract public function search( $query, $kind = 'movie', $args = array() );

	/**
	 * Fetch a full record.
	 *
	 * @param string $remote_id Remote identifier.
	 * @param string $kind      Media kind.
	 * @param array  $args      Extra args.
	 * @return array|\WP_Error Normalized record.
	 */
	abstract public function fetch( $remote_id, $kind = 'movie', $args = array() );

	/* ---------------------------------------------------------------------
	 * Shared helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Perform a cached GET request returning decoded JSON.
	 *
	 * @param string $url     Endpoint.
	 * @param array  $args    wp_remote_get args.
	 * @param int    $ttl     Cache lifetime in seconds. 0 disables caching.
	 * @return array|\WP_Error
	 */
	protected function get_json( $url, $args = array(), $ttl = HOUR_IN_SECONDS ) {
		$cache_key = 'mcsrc_' . md5( $this->id . '|' . $url . '|' . wp_json_encode( $args ) );

		if ( $ttl > 0 ) {
			$cached = get_transient( $cache_key );

			if ( false !== $cached ) {
				return $cached;
			}
		}

		$defaults = array(
			'timeout'    => (int) apply_filters( 'manacore_sources_timeout', 15 ),
			'user-agent' => 'ManaCore/' . MANACORE_SOURCES_VERSION . '; ' . home_url( '/' ),
			'headers'    => array(
				'Accept' => 'application/json',
			),
		);

		$response = wp_remote_get( $url, array_replace_recursive( $defaults, $args ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error(
				'manacore_sources_http',
				sprintf(
					/* translators: 1: provider label, 2: HTTP status code. */
					__( 'خطای ارتباط با %1$s (کد %2$d).', 'manacore' ),
					$this->label,
					$code
				),
				array( 'status' => $code, 'body' => $body )
			);
		}

		$data = json_decode( $body, true );

		if ( null === $data && JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error(
				'manacore_sources_json',
				sprintf(
					/* translators: %s: provider label. */
					__( 'پاسخ نامعتبر از %s دریافت شد.', 'manacore' ),
					$this->label
				)
			);
		}

		if ( $ttl > 0 ) {
			set_transient( $cache_key, $data, $ttl );
		}

		return $data;
	}

	/**
	 * Build an empty normalized record.
	 *
	 * @return array
	 */
	protected function blank_record() {
		return array(
			'provider'         => $this->id,
			'provider_label'   => $this->label,
			'remote_id'        => '',
			'kind'             => 'movie',
			'title'            => '',
			'original_title'   => '',
			'alt_titles'       => array(),
			'tagline'          => '',
			'overview'         => '',
			'release_date'     => '',
			'year'             => '',
			'runtime'          => '',
			'status'           => '',
			'poster'           => '',
			'backdrop'         => '',
			'logo'             => '',
			'trailer'          => '',
			'gallery'          => array(),
			'genres'           => array(),
			'countries'        => array(),
			'languages'        => array(),
			'networks'         => array(),
			'studios'          => array(),
			'imdb_id'          => '',
			'imdb_rating'      => '',
			'imdb_votes'       => '',
			'tmdb_id'          => '',
			'tmdb_rating'      => '',
			'mal_id'           => '',
			'mal_rating'       => '',
			'metascore'        => '',
			'rotten_score'     => '',
			'director'         => '',
			'writer'           => '',
			'producer'         => '',
			'composer'         => '',
			'cast'             => array(),
			'total_seasons'    => '',
			'total_episodes'   => '',
			'episode_runtime'  => '',
			'air_day'          => '',
			'next_episode'     => '',
			'seasons'          => array(),
			'source_url'       => '',
		);
	}

	/**
	 * Extract a 4 digit year from a date string.
	 *
	 * @param string $date Date string.
	 * @return string
	 */
	protected function year_from_date( $date ) {
		if ( ! $date ) {
			return '';
		}

		return preg_match( '/(\d{4})/', (string) $date, $m ) ? $m[1] : '';
	}

	/**
	 * Normalize a list of names into a clean array.
	 *
	 * @param mixed  $list Raw list.
	 * @param string $key  Key to read from each item when it is an array.
	 * @return array
	 */
	protected function pluck_names( $list, $key = 'name' ) {
		if ( ! is_array( $list ) ) {
			return array();
		}

		$out = array();

		foreach ( $list as $item ) {
			if ( is_string( $item ) ) {
				$name = $item;
			} elseif ( is_array( $item ) && isset( $item[ $key ] ) ) {
				$name = $item[ $key ];
			} else {
				continue;
			}

			$name = trim( wp_strip_all_tags( (string) $name ) );

			if ( '' !== $name && ! in_array( $name, $out, true ) ) {
				$out[] = $name;
			}
		}

		return $out;
	}
}
