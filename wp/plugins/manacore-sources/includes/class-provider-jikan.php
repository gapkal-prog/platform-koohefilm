<?php
/**
 * Jikan / MyAnimeList provider (free, no API key).
 *
 * @package ManaCore\Sources
 */

namespace ManaCore\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Jikan – unofficial MyAnimeList REST API. Best source for anime, no key needed.
 */
class Provider_Jikan extends Provider {

	const API = 'https://api.jikan.moe/v4';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = 'jikan';
		$this->label        = __( 'MyAnimeList / Jikan (بدون کلید)', 'manacore' );
		$this->requires_key = false;
		$this->kinds        = array( 'anime' );
	}

	/**
	 * Search anime.
	 *
	 * @param string $query Term.
	 * @param string $kind  Kind.
	 * @param array  $args  Extra args.
	 * @return array|\WP_Error
	 */
	public function search( $query, $kind = 'anime', $args = array() ) {
		$params = array(
			'q'     => $query,
			'limit' => 20,
			'sfw'   => 'true',
		);

		$url  = self::API . '/anime?' . http_build_query( $params );
		$data = $this->get_json( $url, array(), 30 * MINUTE_IN_SECONDS );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$rows = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();
		$out  = array();

		foreach ( $rows as $item ) {
			$out[] = array(
				'provider'  => $this->id,
				'remote_id' => isset( $item['mal_id'] ) ? (string) $item['mal_id'] : '',
				'kind'      => 'anime',
				'title'     => isset( $item['title'] ) ? (string) $item['title'] : '',
				'original'  => isset( $item['title_japanese'] ) ? (string) $item['title_japanese'] : '',
				'year'      => isset( $item['year'] ) && $item['year']
					? (string) $item['year']
					: $this->year_from_date( isset( $item['aired']['from'] ) ? $item['aired']['from'] : '' ),
				'poster'    => isset( $item['images']['jpg']['image_url'] ) ? (string) $item['images']['jpg']['image_url'] : '',
				'rating'    => isset( $item['score'] ) && $item['score'] ? (string) $item['score'] : '',
				'overview'  => isset( $item['synopsis'] ) ? wp_trim_words( (string) $item['synopsis'], 28 ) : '',
			);
		}

		return $out;
	}

	/**
	 * Fetch a full anime record.
	 *
	 * @param string $remote_id MAL id.
	 * @param string $kind      Kind.
	 * @param array  $args      Extra args.
	 * @return array|\WP_Error
	 */
	public function fetch( $remote_id, $kind = 'anime', $args = array() ) {
		$id = absint( $remote_id );

		if ( ! $id ) {
			return new \WP_Error( 'manacore_sources_bad_id', __( 'شناسه MyAnimeList نامعتبر است.', 'manacore' ) );
		}

		$data = $this->get_json( self::API . '/anime/' . $id . '/full', array(), 6 * HOUR_IN_SECONDS );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$d = isset( $data['data'] ) ? $data['data'] : array();

		if ( ! is_array( $d ) || empty( $d ) ) {
			return new \WP_Error( 'manacore_sources_empty', __( 'اطلاعاتی برای این انیمه یافت نشد.', 'manacore' ) );
		}

		$rec = $this->blank_record();

		$rec['kind']           = 'anime';
		$rec['remote_id']      = (string) $id;
		$rec['mal_id']         = (string) $id;
		$rec['title']          = isset( $d['title'] ) ? (string) $d['title'] : '';
		$rec['original_title'] = isset( $d['title_japanese'] ) ? (string) $d['title_japanese'] : '';
		$rec['overview']       = isset( $d['synopsis'] ) ? trim( (string) $d['synopsis'] ) : '';
		$rec['release_date']   = isset( $d['aired']['from'] ) ? substr( (string) $d['aired']['from'], 0, 10 ) : '';
		$rec['year']           = isset( $d['year'] ) && $d['year'] ? (string) $d['year'] : $this->year_from_date( $rec['release_date'] );
		$rec['status']         = $this->map_status( isset( $d['status'] ) ? $d['status'] : '' );
		$rec['total_episodes'] = isset( $d['episodes'] ) && $d['episodes'] ? (string) absint( $d['episodes'] ) : '';
		$rec['source_url']     = isset( $d['url'] ) ? (string) $d['url'] : '';

		if ( isset( $d['score'] ) && $d['score'] ) {
			$rec['mal_rating'] = (string) round( (float) $d['score'], 2 );
		}

		if ( ! empty( $d['duration'] ) && preg_match( '/(\d+)\s*min/i', (string) $d['duration'], $m ) ) {
			$rec['runtime']         = (string) absint( $m[1] );
			$rec['episode_runtime'] = $rec['runtime'];
		}

		if ( ! empty( $d['images']['jpg']['large_image_url'] ) ) {
			$rec['poster'] = (string) $d['images']['jpg']['large_image_url'];
		}

		if ( ! empty( $d['trailer']['url'] ) ) {
			$rec['trailer'] = (string) $d['trailer']['url'];
		}

		if ( ! empty( $d['trailer']['images']['maximum_image_url'] ) ) {
			$rec['backdrop'] = (string) $d['trailer']['images']['maximum_image_url'];
		}

		$rec['genres'] = array_merge(
			$this->pluck_names( isset( $d['genres'] ) ? $d['genres'] : array() ),
			$this->pluck_names( isset( $d['themes'] ) ? $d['themes'] : array() ),
			$this->pluck_names( isset( $d['demographics'] ) ? $d['demographics'] : array() )
		);
		$rec['genres'] = array_values( array_unique( $rec['genres'] ) );

		$rec['studios']   = $this->pluck_names( isset( $d['studios'] ) ? $d['studios'] : array() );
		$rec['networks']  = $this->pluck_names( isset( $d['licensors'] ) ? $d['licensors'] : array() );
		$rec['countries'] = array( __( 'ژاپن', 'manacore' ) );
		$rec['languages'] = array( __( 'ژاپنی', 'manacore' ) );

		// Alternative titles.
		if ( ! empty( $d['titles'] ) && is_array( $d['titles'] ) ) {
			$rec['alt_titles'] = array_slice( $this->pluck_names( $d['titles'], 'title' ), 0, 8 );
		}

		// Season label.
		if ( ! empty( $d['season'] ) ) {
			$rec['air_day'] = '';
		}

		if ( ! empty( $d['broadcast']['day'] ) ) {
			$rec['air_day'] = (string) $d['broadcast']['day'];
		}

		// Producers → producer field.
		$producers = $this->pluck_names( isset( $d['producers'] ) ? $d['producers'] : array() );

		if ( $producers ) {
			$rec['producer'] = implode( '، ', array_slice( $producers, 0, 5 ) );
		}

		// Characters / voice actors as cast.
		$rec['cast'] = $this->fetch_characters( $id );

		return $rec;
	}

	/**
	 * Fetch anime characters as cast entries.
	 *
	 * @param int $id MAL id.
	 * @return array
	 */
	protected function fetch_characters( $id ) {
		$data = $this->get_json( self::API . '/anime/' . $id . '/characters', array(), 6 * HOUR_IN_SECONDS );

		if ( is_wp_error( $data ) || empty( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return array();
		}

		$limit = (int) manacore_sources_get_option( 'cast_limit', 15 );
		$limit = $limit > 0 ? $limit : 15;
		$out   = array();

		foreach ( array_slice( $data['data'], 0, $limit ) as $entry ) {
			$character = isset( $entry['character'] ) ? $entry['character'] : array();
			$actor     = '';

			if ( ! empty( $entry['voice_actors'] ) && is_array( $entry['voice_actors'] ) ) {
				foreach ( $entry['voice_actors'] as $va ) {
					if ( isset( $va['language'] ) && 'Japanese' === $va['language'] && ! empty( $va['person']['name'] ) ) {
						$actor = (string) $va['person']['name'];
						break;
					}
				}

				if ( '' === $actor && ! empty( $entry['voice_actors'][0]['person']['name'] ) ) {
					$actor = (string) $entry['voice_actors'][0]['person']['name'];
				}
			}

			$out[] = array(
				'name'      => $actor ? $actor : ( isset( $character['name'] ) ? (string) $character['name'] : '' ),
				'character' => isset( $character['name'] ) ? (string) $character['name'] : '',
				'photo'     => isset( $character['images']['jpg']['image_url'] ) ? (string) $character['images']['jpg']['image_url'] : '',
				'person_id' => '',
			);
		}

		return $out;
	}

	/**
	 * Map a Jikan status.
	 *
	 * @param string $status Raw status.
	 * @return string
	 */
	protected function map_status( $status ) {
		$map = array(
			'Finished Airing'  => 'ended',
			'Currently Airing' => 'ongoing',
			'Not yet aired'    => 'upcoming',
		);

		return isset( $map[ $status ] ) ? $map[ $status ] : '';
	}
}
