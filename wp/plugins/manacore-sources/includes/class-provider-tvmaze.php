<?php
/**
 * TVMaze provider (free, no API key).
 *
 * @package ManaCore\Sources
 */

namespace ManaCore\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * TVMaze – open TV catalogue with full season/episode data and no key required.
 */
class Provider_Tvmaze extends Provider {

	const API = 'https://api.tvmaze.com';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = 'tvmaze';
		$this->label        = __( 'TVMaze (بدون کلید)', 'manacore' );
		$this->requires_key = false;
		$this->kinds        = array( 'series', 'anime' );
	}

	/**
	 * Search TVMaze.
	 *
	 * @param string $query Term.
	 * @param string $kind  Kind.
	 * @param array  $args  Extra args.
	 * @return array|\WP_Error
	 */
	public function search( $query, $kind = 'series', $args = array() ) {
		$url  = self::API . '/search/shows?' . http_build_query( array( 'q' => $query ) );
		$data = $this->get_json( $url, array(), 30 * MINUTE_IN_SECONDS );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$out = array();

		foreach ( (array) $data as $row ) {
			$show = isset( $row['show'] ) ? $row['show'] : null;

			if ( ! is_array( $show ) ) {
				continue;
			}

			$out[] = array(
				'provider'  => $this->id,
				'remote_id' => isset( $show['id'] ) ? (string) $show['id'] : '',
				'kind'      => $kind,
				'title'     => isset( $show['name'] ) ? (string) $show['name'] : '',
				'original'  => '',
				'year'      => $this->year_from_date( isset( $show['premiered'] ) ? $show['premiered'] : '' ),
				'poster'    => isset( $show['image']['medium'] ) ? (string) $show['image']['medium'] : '',
				'rating'    => isset( $show['rating']['average'] ) ? (string) $show['rating']['average'] : '',
				'overview'  => isset( $show['summary'] ) ? wp_trim_words( wp_strip_all_tags( (string) $show['summary'] ), 28 ) : '',
			);
		}

		return array_slice( $out, 0, 20 );
	}

	/**
	 * Fetch a full TVMaze show.
	 *
	 * @param string $remote_id Show id.
	 * @param string $kind      Kind.
	 * @param array  $args      Extra args.
	 * @return array|\WP_Error
	 */
	public function fetch( $remote_id, $kind = 'series', $args = array() ) {
		$id = absint( $remote_id );

		if ( ! $id ) {
			return new \WP_Error( 'manacore_sources_bad_id', __( 'شناسه TVMaze نامعتبر است.', 'manacore' ) );
		}

		$url  = self::API . '/shows/' . $id . '?embed[]=cast&embed[]=seasons&embed[]=images';
		$d    = $this->get_json( $url, array(), 6 * HOUR_IN_SECONDS );

		if ( is_wp_error( $d ) ) {
			return $d;
		}

		$rec = $this->blank_record();

		$rec['kind']           = $kind;
		$rec['remote_id']      = (string) $id;
		$rec['title']          = isset( $d['name'] ) ? (string) $d['name'] : '';
		$rec['original_title'] = $rec['title'];
		$rec['overview']       = isset( $d['summary'] ) ? trim( wp_strip_all_tags( (string) $d['summary'] ) ) : '';
		$rec['release_date']   = isset( $d['premiered'] ) ? (string) $d['premiered'] : '';
		$rec['year']           = $this->year_from_date( $rec['release_date'] );
		$rec['status']         = $this->map_status( isset( $d['status'] ) ? $d['status'] : '' );
		$rec['genres']         = isset( $d['genres'] ) ? $this->pluck_names( $d['genres'] ) : array();
		$rec['source_url']     = isset( $d['url'] ) ? (string) $d['url'] : '';

		if ( ! empty( $d['runtime'] ) ) {
			$rec['runtime']         = (string) absint( $d['runtime'] );
			$rec['episode_runtime'] = $rec['runtime'];
		} elseif ( ! empty( $d['averageRuntime'] ) ) {
			$rec['runtime']         = (string) absint( $d['averageRuntime'] );
			$rec['episode_runtime'] = $rec['runtime'];
		}

		if ( isset( $d['rating']['average'] ) && $d['rating']['average'] ) {
			$rec['imdb_rating'] = (string) round( (float) $d['rating']['average'], 1 );
		}

		if ( ! empty( $d['externals']['imdb'] ) ) {
			$rec['imdb_id'] = (string) $d['externals']['imdb'];
		}

		if ( ! empty( $d['image']['original'] ) ) {
			$rec['poster'] = (string) $d['image']['original'];
		}

		if ( ! empty( $d['network']['name'] ) ) {
			$rec['networks'][] = (string) $d['network']['name'];

			if ( ! empty( $d['network']['country']['name'] ) ) {
				$rec['countries'][] = (string) $d['network']['country']['name'];
			}
		} elseif ( ! empty( $d['webChannel']['name'] ) ) {
			$rec['networks'][] = (string) $d['webChannel']['name'];
		}

		if ( ! empty( $d['language'] ) ) {
			$rec['languages'][] = (string) $d['language'];
		}

		if ( ! empty( $d['schedule']['days'] ) && is_array( $d['schedule']['days'] ) ) {
			$rec['air_day'] = $this->map_day( reset( $d['schedule']['days'] ) );
		}

		// Cast.
		if ( ! empty( $d['_embedded']['cast'] ) && is_array( $d['_embedded']['cast'] ) ) {
			$limit = (int) manacore_sources_get_option( 'cast_limit', 15 );
			$limit = $limit > 0 ? $limit : 15;

			foreach ( array_slice( $d['_embedded']['cast'], 0, $limit ) as $entry ) {
				$rec['cast'][] = array(
					'name'      => isset( $entry['person']['name'] ) ? (string) $entry['person']['name'] : '',
					'character' => isset( $entry['character']['name'] ) ? (string) $entry['character']['name'] : '',
					'photo'     => isset( $entry['person']['image']['medium'] ) ? (string) $entry['person']['image']['medium'] : '',
					'person_id' => '',
				);
			}
		}

		// Seasons.
		if ( ! empty( $d['_embedded']['seasons'] ) && is_array( $d['_embedded']['seasons'] ) ) {
			$total_eps = 0;

			foreach ( $d['_embedded']['seasons'] as $season ) {
				$episodes = isset( $season['episodeOrder'] ) ? absint( $season['episodeOrder'] ) : 0;
				$total_eps += $episodes;

				$rec['seasons'][] = array(
					'number'   => isset( $season['number'] ) ? (string) absint( $season['number'] ) : '',
					'name'     => isset( $season['name'] ) ? (string) $season['name'] : '',
					'episodes' => $episodes ? (string) $episodes : '',
					'year'     => $this->year_from_date( isset( $season['premiereDate'] ) ? $season['premiereDate'] : '' ),
					'poster'   => isset( $season['image']['medium'] ) ? (string) $season['image']['medium'] : '',
					'overview' => isset( $season['summary'] ) ? trim( wp_strip_all_tags( (string) $season['summary'] ) ) : '',
				);
			}

			$rec['total_seasons']  = (string) count( $rec['seasons'] );
			$rec['total_episodes'] = $total_eps ? (string) $total_eps : '';
		}

		// Backdrop from embedded images.
		if ( ! empty( $d['_embedded']['images'] ) && is_array( $d['_embedded']['images'] ) ) {
			foreach ( $d['_embedded']['images'] as $img ) {
				$type = isset( $img['type'] ) ? $img['type'] : '';
				$src  = isset( $img['resolutions']['original']['url'] ) ? (string) $img['resolutions']['original']['url'] : '';

				if ( ! $src ) {
					continue;
				}

				if ( 'background' === $type && '' === $rec['backdrop'] ) {
					$rec['backdrop'] = $src;
				} elseif ( 'banner' === $type && '' === $rec['logo'] ) {
					$rec['logo'] = $src;
				} else {
					$rec['gallery'][] = $src;
				}
			}

			$rec['gallery'] = array_slice( array_unique( $rec['gallery'] ), 0, 12 );
		}

		return $rec;
	}

	/**
	 * Map a TVMaze status.
	 *
	 * @param string $status Raw status.
	 * @return string
	 */
	protected function map_status( $status ) {
		$map = array(
			'Running'      => 'ongoing',
			'To Be Determined' => 'ongoing',
			'In Development' => 'upcoming',
			'Ended'        => 'ended',
		);

		return isset( $map[ $status ] ) ? $map[ $status ] : '';
	}

	/**
	 * Map an English weekday to Persian.
	 *
	 * @param string $day Weekday.
	 * @return string
	 */
	protected function map_day( $day ) {
		$map = array(
			'Saturday'  => __( 'شنبه', 'manacore' ),
			'Sunday'    => __( 'یکشنبه', 'manacore' ),
			'Monday'    => __( 'دوشنبه', 'manacore' ),
			'Tuesday'   => __( 'سه‌شنبه', 'manacore' ),
			'Wednesday' => __( 'چهارشنبه', 'manacore' ),
			'Thursday'  => __( 'پنجشنبه', 'manacore' ),
			'Friday'    => __( 'جمعه', 'manacore' ),
		);

		return isset( $map[ $day ] ) ? $map[ $day ] : (string) $day;
	}
}
