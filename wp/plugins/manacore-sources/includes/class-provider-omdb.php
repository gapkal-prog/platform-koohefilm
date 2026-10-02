<?php
/**
 * OMDb provider (optional free-tier key, used to enrich IMDb ratings).
 *
 * @package ManaCore\Sources
 */

namespace ManaCore\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * OMDb – lightweight IMDb mirror. Used mainly as an enrichment pass to fill in
 * IMDb rating / votes / Metascore / Rotten Tomatoes when an IMDb id is known.
 */
class Provider_Omdb extends Provider {

	const API = 'https://www.omdbapi.com/';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = 'omdb';
		$this->label        = __( 'OMDb (اختیاری)', 'manacore' );
		$this->requires_key = true;
		$this->kinds        = array( 'movie', 'series', 'anime' );
	}

	/**
	 * Get the stored OMDb key.
	 *
	 * @return string
	 */
	protected function key() {
		$key = (string) manacore_sources_get_option( 'omdb_key', '' );

		if ( '' === $key && defined( 'MANACORE_OMDB_API_KEY' ) ) {
			$key = (string) MANACORE_OMDB_API_KEY;
		}

		return trim( (string) apply_filters( 'manacore_omdb_api_key', $key ) );
	}

	/**
	 * Availability.
	 *
	 * @return bool
	 */
	public function is_available() {
		return '' !== $this->key();
	}

	/**
	 * Search OMDb by title.
	 *
	 * @param string $query Term.
	 * @param string $kind  Kind.
	 * @param array  $args  Extra args.
	 * @return array|\WP_Error
	 */
	public function search( $query, $kind = 'movie', $args = array() ) {
		if ( ! $this->is_available() ) {
			return new \WP_Error( 'manacore_sources_no_key', __( 'کلید OMDb ثبت نشده است.', 'manacore' ) );
		}

		$params = array(
			'apikey' => $this->key(),
			's'      => $query,
			'type'   => 'movie' === $kind ? 'movie' : 'series',
		);

		if ( ! empty( $args['year'] ) ) {
			$params['y'] = absint( $args['year'] );
		}

		$data = $this->get_json( self::API . '?' . http_build_query( $params ), array(), 30 * MINUTE_IN_SECONDS );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( empty( $data['Search'] ) || ! is_array( $data['Search'] ) ) {
			return array();
		}

		$out = array();

		foreach ( $data['Search'] as $item ) {
			$poster = isset( $item['Poster'] ) ? (string) $item['Poster'] : '';

			$out[] = array(
				'provider'  => $this->id,
				'remote_id' => isset( $item['imdbID'] ) ? (string) $item['imdbID'] : '',
				'kind'      => $kind,
				'title'     => isset( $item['Title'] ) ? (string) $item['Title'] : '',
				'original'  => '',
				'year'      => isset( $item['Year'] ) ? $this->year_from_date( $item['Year'] ) : '',
				'poster'    => ( $poster && 'N/A' !== $poster ) ? $poster : '',
				'rating'    => '',
				'overview'  => '',
			);
		}

		return $out;
	}

	/**
	 * Fetch a full OMDb record.
	 *
	 * @param string $remote_id IMDb id.
	 * @param string $kind      Kind.
	 * @param array  $args      Extra args.
	 * @return array|\WP_Error
	 */
	public function fetch( $remote_id, $kind = 'movie', $args = array() ) {
		if ( ! $this->is_available() ) {
			return new \WP_Error( 'manacore_sources_no_key', __( 'کلید OMDb ثبت نشده است.', 'manacore' ) );
		}

		$imdb = preg_replace( '/[^a-z0-9]/i', '', (string) $remote_id );

		if ( ! $imdb ) {
			return new \WP_Error( 'manacore_sources_bad_id', __( 'شناسه IMDb نامعتبر است.', 'manacore' ) );
		}

		$params = array(
			'apikey' => $this->key(),
			'i'      => $imdb,
			'plot'   => 'full',
		);

		$d = $this->get_json( self::API . '?' . http_build_query( $params ), array(), 6 * HOUR_IN_SECONDS );

		if ( is_wp_error( $d ) ) {
			return $d;
		}

		if ( empty( $d['Response'] ) || 'True' !== $d['Response'] ) {
			return new \WP_Error(
				'manacore_sources_empty',
				isset( $d['Error'] ) ? (string) $d['Error'] : __( 'موردی در OMDb یافت نشد.', 'manacore' )
			);
		}

		$rec = $this->blank_record();

		$rec['kind']           = $kind;
		$rec['remote_id']      = $imdb;
		$rec['imdb_id']        = $imdb;
		$rec['title']          = $this->clean( isset( $d['Title'] ) ? $d['Title'] : '' );
		$rec['original_title'] = $rec['title'];
		$rec['overview']       = $this->clean( isset( $d['Plot'] ) ? $d['Plot'] : '' );
		$rec['release_date']   = $this->parse_date( isset( $d['Released'] ) ? $d['Released'] : '' );
		$rec['year']           = $this->year_from_date( isset( $d['Year'] ) ? $d['Year'] : '' );
		$rec['director']       = $this->clean( isset( $d['Director'] ) ? $d['Director'] : '' );
		$rec['writer']         = $this->clean( isset( $d['Writer'] ) ? $d['Writer'] : '' );
		$rec['poster']         = $this->clean( isset( $d['Poster'] ) ? $d['Poster'] : '' );
		$rec['imdb_rating']    = $this->clean( isset( $d['imdbRating'] ) ? $d['imdbRating'] : '' );
		$rec['metascore']      = $this->clean( isset( $d['Metascore'] ) ? $d['Metascore'] : '' );
		$rec['total_seasons']  = $this->clean( isset( $d['totalSeasons'] ) ? $d['totalSeasons'] : '' );
		$rec['source_url']     = 'https://www.imdb.com/title/' . $imdb . '/';

		if ( ! empty( $d['imdbVotes'] ) && 'N/A' !== $d['imdbVotes'] ) {
			$rec['imdb_votes'] = (string) absint( str_replace( ',', '', $d['imdbVotes'] ) );
		}

		if ( ! empty( $d['Runtime'] ) && preg_match( '/(\d+)/', (string) $d['Runtime'], $m ) ) {
			$rec['runtime'] = (string) absint( $m[1] );
		}

		$rec['genres']    = $this->split( isset( $d['Genre'] ) ? $d['Genre'] : '' );
		$rec['countries'] = $this->split( isset( $d['Country'] ) ? $d['Country'] : '' );
		$rec['languages'] = $this->split( isset( $d['Language'] ) ? $d['Language'] : '' );

		foreach ( $this->split( isset( $d['Actors'] ) ? $d['Actors'] : '' ) as $name ) {
			$rec['cast'][] = array(
				'name'      => $name,
				'character' => '',
				'photo'     => '',
				'person_id' => '',
			);
		}

		// Rotten Tomatoes score.
		if ( ! empty( $d['Ratings'] ) && is_array( $d['Ratings'] ) ) {
			foreach ( $d['Ratings'] as $rating ) {
				if ( isset( $rating['Source'] ) && 'Rotten Tomatoes' === $rating['Source'] && ! empty( $rating['Value'] ) ) {
					$rec['rotten_score'] = (string) absint( rtrim( (string) $rating['Value'], '%' ) );
					break;
				}
			}
		}

		return $rec;
	}

	/**
	 * OMDb uses the literal string "N/A" for missing values.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function clean( $value ) {
		$value = trim( (string) $value );

		return ( '' === $value || 'N/A' === $value ) ? '' : $value;
	}

	/**
	 * Split a comma separated list.
	 *
	 * @param string $value Raw list.
	 * @return array
	 */
	protected function split( $value ) {
		$value = $this->clean( $value );

		if ( '' === $value ) {
			return array();
		}

		return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
	}

	/**
	 * Parse an OMDb date (e.g. "12 Jul 2019") into Y-m-d.
	 *
	 * @param string $value Raw date.
	 * @return string
	 */
	protected function parse_date( $value ) {
		$value = $this->clean( $value );

		if ( '' === $value ) {
			return '';
		}

		$time = strtotime( $value );

		return $time ? gmdate( 'Y-m-d', $time ) : '';
	}
}
