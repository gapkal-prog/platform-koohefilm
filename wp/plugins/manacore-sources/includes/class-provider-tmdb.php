<?php
/**
 * TMDB provider (API key mode).
 *
 * @package ManaCore\Sources
 */

namespace ManaCore\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * The Movie Database provider.
 *
 * Supports both the classic v3 `api_key` query parameter and v4 bearer tokens
 * (auto-detected: a token containing dots is treated as a JWT bearer token).
 */
class Provider_Tmdb extends Provider {

	const API = 'https://api.themoviedb.org/3';
	const IMG = 'https://image.tmdb.org/t/p';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = 'tmdb';
		$this->label        = __( 'TMDB (با کلید)', 'manacore' );
		$this->requires_key = true;
		$this->kinds        = array( 'movie', 'series', 'anime' );
	}

	/**
	 * Provider availability.
	 *
	 * @return bool
	 */
	public function is_available() {
		return manacore_sources_has_key();
	}

	/**
	 * TMDB media type for a kind.
	 *
	 * @param string $kind Kind.
	 * @return string
	 */
	protected function media_type( $kind ) {
		return 'movie' === $kind ? 'movie' : 'tv';
	}

	/**
	 * Build a request URL + args.
	 *
	 * @param string $path  API path.
	 * @param array  $query Query args.
	 * @return array{0:string,1:array}
	 */
	protected function endpoint( $path, $query = array() ) {
		$key  = manacore_sources_tmdb_key();
		$args = array();

		$query = array_merge(
			array( 'language' => manacore_sources_get_option( 'tmdb_language', 'fa-IR' ) ),
			$query
		);

		if ( false !== strpos( $key, '.' ) ) {
			// v4 bearer token.
			$args['headers'] = array( 'Authorization' => 'Bearer ' . $key );
		} else {
			$query['api_key'] = $key;
		}

		$url = self::API . $path . '?' . http_build_query( $query );

		return array( $url, $args );
	}

	/**
	 * Absolute image URL.
	 *
	 * @param string $path TMDB relative path.
	 * @param string $size Size token.
	 * @return string
	 */
	protected function image( $path, $size = 'w500' ) {
		if ( ! $path ) {
			return '';
		}

		return self::IMG . '/' . $size . $path;
	}

	/**
	 * Search TMDB.
	 *
	 * @param string $query Term.
	 * @param string $kind  Kind.
	 * @param array  $args  Extra args.
	 * @return array|\WP_Error
	 */
	public function search( $query, $kind = 'movie', $args = array() ) {
		if ( ! $this->is_available() ) {
			return new \WP_Error( 'manacore_sources_no_key', __( 'کلید TMDB ثبت نشده است.', 'manacore' ) );
		}

		$type  = $this->media_type( $kind );
		$q     = array( 'query' => $query, 'include_adult' => 'false' );
		$year  = isset( $args['year'] ) ? absint( $args['year'] ) : 0;

		if ( $year ) {
			$q[ 'movie' === $type ? 'year' : 'first_air_date_year' ] = $year;
		}

		list( $url, $req ) = $this->endpoint( '/search/' . $type, $q );

		$data = $this->get_json( $url, $req, 30 * MINUTE_IN_SECONDS );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$results = isset( $data['results'] ) && is_array( $data['results'] ) ? $data['results'] : array();
		$out     = array();

		foreach ( array_slice( $results, 0, 20 ) as $item ) {
			$date = 'movie' === $type
				? ( isset( $item['release_date'] ) ? $item['release_date'] : '' )
				: ( isset( $item['first_air_date'] ) ? $item['first_air_date'] : '' );

			$out[] = array(
				'provider'  => $this->id,
				'remote_id' => isset( $item['id'] ) ? (string) $item['id'] : '',
				'kind'      => $kind,
				'title'     => isset( $item['title'] ) ? $item['title'] : ( isset( $item['name'] ) ? $item['name'] : '' ),
				'original'  => isset( $item['original_title'] ) ? $item['original_title'] : ( isset( $item['original_name'] ) ? $item['original_name'] : '' ),
				'year'      => $this->year_from_date( $date ),
				'poster'    => $this->image( isset( $item['poster_path'] ) ? $item['poster_path'] : '', 'w185' ),
				'rating'    => isset( $item['vote_average'] ) ? round( (float) $item['vote_average'], 1 ) : '',
				'overview'  => isset( $item['overview'] ) ? wp_trim_words( (string) $item['overview'], 28 ) : '',
			);
		}

		return $out;
	}

	/**
	 * Fetch a full TMDB record.
	 *
	 * @param string $remote_id TMDB id.
	 * @param string $kind      Kind.
	 * @param array  $args      Extra args.
	 * @return array|\WP_Error
	 */
	public function fetch( $remote_id, $kind = 'movie', $args = array() ) {
		if ( ! $this->is_available() ) {
			return new \WP_Error( 'manacore_sources_no_key', __( 'کلید TMDB ثبت نشده است.', 'manacore' ) );
		}

		$id   = absint( $remote_id );
		$type = $this->media_type( $kind );

		if ( ! $id ) {
			return new \WP_Error( 'manacore_sources_bad_id', __( 'شناسه TMDB نامعتبر است.', 'manacore' ) );
		}

		list( $url, $req ) = $this->endpoint(
			'/' . $type . '/' . $id,
			array(
				'append_to_response' => 'credits,images,videos,external_ids,alternative_titles,translations',
				'include_image_language' => 'fa,en,null',
			)
		);

		$data = $this->get_json( $url, $req, 6 * HOUR_IN_SECONDS );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return 'movie' === $type
			? $this->map_movie( $data, $kind )
			: $this->map_tv( $data, $kind );
	}

	/**
	 * Map a TMDB movie payload.
	 *
	 * @param array  $d    Payload.
	 * @param string $kind Kind.
	 * @return array
	 */
	protected function map_movie( $d, $kind ) {
		$rec = $this->blank_record();

		$rec['kind']           = $kind;
		$rec['remote_id']      = isset( $d['id'] ) ? (string) $d['id'] : '';
		$rec['tmdb_id']        = $rec['remote_id'];
		$rec['title']          = isset( $d['title'] ) ? $d['title'] : '';
		$rec['original_title'] = isset( $d['original_title'] ) ? $d['original_title'] : '';
		$rec['tagline']        = isset( $d['tagline'] ) ? $d['tagline'] : '';
		$rec['overview']       = isset( $d['overview'] ) ? $d['overview'] : '';
		$rec['release_date']   = isset( $d['release_date'] ) ? $d['release_date'] : '';
		$rec['year']           = $this->year_from_date( $rec['release_date'] );
		$rec['runtime']        = isset( $d['runtime'] ) ? (string) absint( $d['runtime'] ) : '';
		$rec['status']         = $this->map_status( isset( $d['status'] ) ? $d['status'] : '' );
		$rec['tmdb_rating']    = isset( $d['vote_average'] ) ? (string) round( (float) $d['vote_average'], 1 ) : '';
		$rec['imdb_id']        = isset( $d['imdb_id'] ) ? (string) $d['imdb_id'] : '';
		$rec['poster']         = $this->image( isset( $d['poster_path'] ) ? $d['poster_path'] : '', 'w780' );
		$rec['backdrop']       = $this->image( isset( $d['backdrop_path'] ) ? $d['backdrop_path'] : '', 'w1280' );
		$rec['genres']         = $this->pluck_names( isset( $d['genres'] ) ? $d['genres'] : array() );
		$rec['countries']      = $this->pluck_names( isset( $d['production_countries'] ) ? $d['production_countries'] : array() );
		$rec['languages']      = $this->pluck_names( isset( $d['spoken_languages'] ) ? $d['spoken_languages'] : array(), 'english_name' );
		$rec['studios']        = $this->pluck_names( isset( $d['production_companies'] ) ? $d['production_companies'] : array() );
		$rec['source_url']     = 'https://www.themoviedb.org/movie/' . $rec['remote_id'];

		$this->apply_shared( $rec, $d );

		return $rec;
	}

	/**
	 * Map a TMDB tv payload.
	 *
	 * @param array  $d    Payload.
	 * @param string $kind Kind.
	 * @return array
	 */
	protected function map_tv( $d, $kind ) {
		$rec = $this->blank_record();

		$rec['kind']           = $kind;
		$rec['remote_id']      = isset( $d['id'] ) ? (string) $d['id'] : '';
		$rec['tmdb_id']        = $rec['remote_id'];
		$rec['title']          = isset( $d['name'] ) ? $d['name'] : '';
		$rec['original_title'] = isset( $d['original_name'] ) ? $d['original_name'] : '';
		$rec['tagline']        = isset( $d['tagline'] ) ? $d['tagline'] : '';
		$rec['overview']       = isset( $d['overview'] ) ? $d['overview'] : '';
		$rec['release_date']   = isset( $d['first_air_date'] ) ? $d['first_air_date'] : '';
		$rec['year']           = $this->year_from_date( $rec['release_date'] );
		$rec['status']         = $this->map_status( isset( $d['status'] ) ? $d['status'] : '' );
		$rec['tmdb_rating']    = isset( $d['vote_average'] ) ? (string) round( (float) $d['vote_average'], 1 ) : '';
		$rec['poster']         = $this->image( isset( $d['poster_path'] ) ? $d['poster_path'] : '', 'w780' );
		$rec['backdrop']       = $this->image( isset( $d['backdrop_path'] ) ? $d['backdrop_path'] : '', 'w1280' );
		$rec['genres']         = $this->pluck_names( isset( $d['genres'] ) ? $d['genres'] : array() );
		$rec['countries']      = $this->pluck_names( isset( $d['production_countries'] ) ? $d['production_countries'] : array() );
		$rec['languages']      = $this->pluck_names( isset( $d['spoken_languages'] ) ? $d['spoken_languages'] : array(), 'english_name' );
		$rec['networks']       = $this->pluck_names( isset( $d['networks'] ) ? $d['networks'] : array() );
		$rec['studios']        = $this->pluck_names( isset( $d['production_companies'] ) ? $d['production_companies'] : array() );
		$rec['total_seasons']  = isset( $d['number_of_seasons'] ) ? (string) absint( $d['number_of_seasons'] ) : '';
		$rec['total_episodes'] = isset( $d['number_of_episodes'] ) ? (string) absint( $d['number_of_episodes'] ) : '';
		$rec['source_url']     = 'https://www.themoviedb.org/tv/' . $rec['remote_id'];

		if ( ! empty( $d['episode_run_time'][0] ) ) {
			$rec['episode_runtime'] = (string) absint( $d['episode_run_time'][0] );
			$rec['runtime']         = $rec['episode_runtime'];
		}

		if ( ! empty( $d['next_episode_to_air']['air_date'] ) ) {
			$rec['next_episode'] = (string) $d['next_episode_to_air']['air_date'];
		}

		if ( ! empty( $d['external_ids']['imdb_id'] ) ) {
			$rec['imdb_id'] = (string) $d['external_ids']['imdb_id'];
		}

		if ( ! empty( $d['seasons'] ) && is_array( $d['seasons'] ) ) {
			foreach ( $d['seasons'] as $season ) {
				$number = isset( $season['season_number'] ) ? absint( $season['season_number'] ) : 0;

				if ( ! $number && empty( $season['episode_count'] ) ) {
					continue;
				}

				$rec['seasons'][] = array(
					'number'   => (string) $number,
					'name'     => isset( $season['name'] ) ? (string) $season['name'] : '',
					'episodes' => isset( $season['episode_count'] ) ? (string) absint( $season['episode_count'] ) : '',
					'year'     => $this->year_from_date( isset( $season['air_date'] ) ? $season['air_date'] : '' ),
					'poster'   => $this->image( isset( $season['poster_path'] ) ? $season['poster_path'] : '', 'w342' ),
					'overview' => isset( $season['overview'] ) ? (string) $season['overview'] : '',
				);
			}
		}

		$this->apply_shared( $rec, $d );

		return $rec;
	}

	/**
	 * Apply credits, images and videos shared by both media types.
	 *
	 * @param array $rec Record (by reference).
	 * @param array $d   Payload.
	 */
	protected function apply_shared( &$rec, $d ) {
		// Alternative titles.
		if ( ! empty( $d['alternative_titles']['titles'] ) ) {
			$rec['alt_titles'] = $this->pluck_names( array_slice( $d['alternative_titles']['titles'], 0, 8 ), 'title' );
		} elseif ( ! empty( $d['alternative_titles']['results'] ) ) {
			$rec['alt_titles'] = $this->pluck_names( array_slice( $d['alternative_titles']['results'], 0, 8 ), 'title' );
		}

		// Crew.
		$crew = isset( $d['credits']['crew'] ) && is_array( $d['credits']['crew'] ) ? $d['credits']['crew'] : array();
		$jobs = array(
			'director' => array( 'Director' ),
			'writer'   => array( 'Writer', 'Screenplay', 'Story' ),
			'producer' => array( 'Producer', 'Executive Producer' ),
			'composer' => array( 'Original Music Composer', 'Music' ),
		);

		foreach ( $jobs as $field => $titles ) {
			$names = array();

			foreach ( $crew as $person ) {
				$job = isset( $person['job'] ) ? $person['job'] : '';

				if ( in_array( $job, $titles, true ) && ! empty( $person['name'] ) ) {
					$name = trim( (string) $person['name'] );

					if ( ! in_array( $name, $names, true ) ) {
						$names[] = $name;
					}
				}
			}

			$rec[ $field ] = implode( '، ', array_slice( $names, 0, 5 ) );
		}

		// Created by (tv fallback for writer).
		if ( '' === $rec['writer'] && ! empty( $d['created_by'] ) ) {
			$rec['writer'] = implode( '، ', array_slice( $this->pluck_names( $d['created_by'] ), 0, 5 ) );
		}

		// Cast.
		$cast  = isset( $d['credits']['cast'] ) && is_array( $d['credits']['cast'] ) ? $d['credits']['cast'] : array();
		$limit = (int) manacore_sources_get_option( 'cast_limit', 15 );
		$limit = $limit > 0 ? $limit : 15;

		foreach ( array_slice( $cast, 0, $limit ) as $actor ) {
			$rec['cast'][] = array(
				'name'      => isset( $actor['name'] ) ? (string) $actor['name'] : '',
				'character' => isset( $actor['character'] ) ? (string) $actor['character'] : '',
				'photo'     => $this->image( isset( $actor['profile_path'] ) ? $actor['profile_path'] : '', 'w185' ),
				'person_id' => '',
			);
		}

		// Logo.
		if ( ! empty( $d['images']['logos'] ) && is_array( $d['images']['logos'] ) ) {
			$logo = reset( $d['images']['logos'] );

			if ( ! empty( $logo['file_path'] ) ) {
				$rec['logo'] = $this->image( $logo['file_path'], 'w500' );
			}
		}

		// Gallery from backdrops.
		if ( ! empty( $d['images']['backdrops'] ) && is_array( $d['images']['backdrops'] ) ) {
			foreach ( array_slice( $d['images']['backdrops'], 0, 12 ) as $img ) {
				if ( ! empty( $img['file_path'] ) ) {
					$rec['gallery'][] = $this->image( $img['file_path'], 'w1280' );
				}
			}
		}

		// Trailer.
		if ( ! empty( $d['videos']['results'] ) && is_array( $d['videos']['results'] ) ) {
			foreach ( $d['videos']['results'] as $video ) {
				$site = isset( $video['site'] ) ? strtolower( $video['site'] ) : '';
				$type = isset( $video['type'] ) ? strtolower( $video['type'] ) : '';

				if ( 'youtube' === $site && in_array( $type, array( 'trailer', 'teaser' ), true ) && ! empty( $video['key'] ) ) {
					$rec['trailer'] = 'https://www.youtube.com/watch?v=' . $video['key'];
					break;
				}
			}
		}
	}

	/**
	 * Map a TMDB status string to the core meta vocabulary.
	 *
	 * @param string $status Raw status.
	 * @return string
	 */
	protected function map_status( $status ) {
		$map = array(
			'Released'        => 'released',
			'Post Production' => 'upcoming',
			'In Production'   => 'upcoming',
			'Planned'         => 'upcoming',
			'Rumored'         => 'upcoming',
			'Returning Series' => 'ongoing',
			'Ended'           => 'ended',
			'Canceled'        => 'canceled',
			'Pilot'           => 'upcoming',
		);

		return isset( $map[ $status ] ) ? $map[ $status ] : '';
	}
}
