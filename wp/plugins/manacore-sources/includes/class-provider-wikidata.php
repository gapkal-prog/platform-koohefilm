<?php
/**
 * Wikidata provider (free, no API key).
 *
 * @package ManaCore\Sources
 */

namespace ManaCore\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Wikidata – open structured knowledge base. Provides movies (the gap that
 * TVMaze and Jikan do not cover) with IMDb ids, cast, crew, genres and posters
 * via Wikimedia Commons, without any API key.
 */
class Provider_Wikidata extends Provider {

	const SEARCH = 'https://www.wikidata.org/w/api.php';
	const SPARQL = 'https://query.wikidata.org/sparql';

	/**
	 * Wikipedia API template used to resolve poster artwork.
	 *
	 * Wikidata's P18 rarely holds a film poster (posters are non-free), so the
	 * linked Wikipedia article's lead image is used as the poster source.
	 */
	const WIKIPEDIA = 'https://%s.wikipedia.org/w/api.php';

	/**
	 * How many search hits are hydrated with claims (posters, dates, types).
	 *
	 * Each hydrated entity costs bandwidth, so the batch is deliberately small;
	 * the normalized result is cached so the cost is paid once per query.
	 */
	const HYDRATE_LIMIT = 12;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = 'wikidata';
		$this->label        = __( 'Wikidata (بدون کلید)', 'manacore' );
		$this->requires_key = false;
		$this->kinds        = array( 'movie', 'series', 'anime' );
	}

	/**
	 * Wikidata "instance of" (P31) classes that identify each media kind.
	 *
	 * Used both to narrow the search index query and to drop non-media noise
	 * (companies, dates, disambiguation pages) from the result list.
	 *
	 * @param string $kind Media kind.
	 * @return array Q-ids.
	 */
	protected function kind_types( $kind ) {
		$map = array(
			'movie'  => array(
				'Q11424',    // film.
				'Q24869',    // feature film.
				'Q202866',   // animated film.
				'Q20650540', // animated feature film.
				'Q506240',   // television film.
				'Q29168811', // animated feature film (alt).
				'Q1261214',  // short film.
				'Q18011172', // film project.
				'Q130232',   // drama film.
				'Q2431196',  // audiovisual work.
			),
			'series' => array(
				'Q5398426',   // television series.
				'Q581714',    // drama television series.
				'Q1259759',   // miniseries.
				'Q117467246', // television series (alt).
				'Q63952888',  // anime television series.
				'Q15416',     // television program.
				'Q3464665',   // television season.
			),
			'anime'  => array(
				'Q63952888',  // anime television series.
				'Q1107',      // anime.
				'Q20650540',  // animated feature film.
				'Q11086742',  // anime series.
				'Q100269041', // anime film.
				'Q202866',    // animated film.
				'Q1047299',   // OVA.
			),
		);

		$types = isset( $map[ $kind ] ) ? $map[ $kind ] : $map['movie'];

		/**
		 * Filter the Wikidata P31 classes used to identify a media kind.
		 *
		 * @param array  $types Q-ids.
		 * @param string $kind  Media kind.
		 */
		return (array) apply_filters( 'manacore_sources_wikidata_types', $types, $kind );
	}

	/**
	 * Search Wikidata entities.
	 *
	 * @param string $query Term.
	 * @param string $kind  Kind.
	 * @param array  $args  Extra args.
	 * @return array|\WP_Error
	 */
	public function search( $query, $kind = 'movie', $args = array() ) {
		$lang  = manacore_sources_get_option( 'wikidata_language', 'fa' );
		$query = trim( (string) $query );

		if ( '' === $query ) {
			return array();
		}

		/*
		 * Candidate collection runs in two stages, because the plain entity
		 * search (`wbsearchentities`) is untyped: searching "Inception" also
		 * returns companies, dates and disambiguation pages, and it never
		 * exposes posters or release dates.
		 *
		 * 1. The search index (`list=search` + `haswbstatement`) returns only
		 *    entities that really are a film / series / anime.
		 * 2. `wbsearchentities` runs as a safety net for exact-label matches
		 *    the index may rank poorly; its hits are type-checked in stage 3.
		 * 3. One batched `wbgetentities` call hydrates every candidate with
		 *    claims, so each hit ships a poster, year, original title and a
		 *    verified type.
		 */
		$ids = $this->search_typed( $query, $kind );

		foreach ( $this->search_entities( $query, $lang ) as $id ) {
			if ( ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		if ( ! $ids ) {
			return array();
		}

		$year = isset( $args['year'] ) ? absint( $args['year'] ) : 0;

		return $this->hydrate( $ids, $kind, $lang, $year );
	}

	/**
	 * Find entities of the right type through the Wikidata search index.
	 *
	 * `haswbstatement:P31=Q11424` restricts the result set to actual films (or
	 * series / anime), which removes practically all of the noise the plain
	 * entity search produces.
	 *
	 * @param string $query Term.
	 * @param string $kind  Media kind.
	 * @return array Q-ids in relevance order.
	 */
	protected function search_typed( $query, $kind ) {
		$statements = array();

		foreach ( $this->kind_types( $kind ) as $type ) {
			$statements[] = 'P31=' . $type;
		}

		if ( ! $statements ) {
			return array();
		}

		$url = self::SEARCH . '?' . http_build_query(
			array(
				'action'      => 'query',
				'list'        => 'search',
				'srsearch'    => $query . ' haswbstatement:' . implode( '|', $statements ),
				'srlimit'     => 20,
				'srnamespace' => 0,
				'srprop'      => '',
				'format'      => 'json',
				'origin'      => '*',
			)
		);

		$data = $this->get_json( $url, array(), 6 * HOUR_IN_SECONDS );

		if ( is_wp_error( $data ) || empty( $data['query']['search'] ) ) {
			return array();
		}

		$out = array();

		foreach ( $data['query']['search'] as $row ) {
			if ( ! empty( $row['title'] ) && preg_match( '/^Q\d+$/', (string) $row['title'] ) ) {
				$out[] = (string) $row['title'];
			}
		}

		return $out;
	}

	/**
	 * Plain entity search, used as a safety net for exact label matches.
	 *
	 * @param string $query Term.
	 * @param string $lang  Language code.
	 * @return array Q-ids.
	 */
	protected function search_entities( $query, $lang ) {
		$ids = $this->search_entities_lang( $query, $lang );

		// Persian labels are sparse on Wikidata, so retry in English.
		if ( ! $ids && 'en' !== $lang ) {
			$ids = $this->search_entities_lang( $query, 'en' );
		}

		return $ids;
	}

	/**
	 * Single-language entity search.
	 *
	 * @param string $query Term.
	 * @param string $lang  Language code.
	 * @return array Q-ids.
	 */
	protected function search_entities_lang( $query, $lang ) {
		$url = self::SEARCH . '?' . http_build_query(
			array(
				'action'   => 'wbsearchentities',
				'search'   => $query,
				'language' => $lang,
				'uselang'  => $lang,
				'type'     => 'item',
				'limit'    => 20,
				'format'   => 'json',
				'origin'   => '*',
			)
		);

		$data = $this->get_json( $url, array(), 6 * HOUR_IN_SECONDS );

		if ( is_wp_error( $data ) || empty( $data['search'] ) ) {
			return array();
		}

		$out = array();

		foreach ( $data['search'] as $item ) {
			if ( ! empty( $item['id'] ) && preg_match( '/^Q\d+$/', (string) $item['id'] ) ) {
				$out[] = (string) $item['id'];
			}
		}

		return $out;
	}

	/**
	 * Hydrate candidate ids into complete, type-verified search hits.
	 *
	 * One batched request returns labels, descriptions and claims for every
	 * candidate, which is what finally gives the result list its posters and
	 * release years.
	 *
	 * @param array  $ids  Candidate Q-ids.
	 * @param string $kind Media kind.
	 * @param string $lang Language code.
	 * @param int    $year Optional year filter.
	 * @return array Normalized search hits.
	 */
	protected function hydrate( $ids, $kind, $lang, $year = 0 ) {
		$ids = array_slice( array_values( array_unique( $ids ) ), 0, self::HYDRATE_LIMIT );

		if ( ! $ids ) {
			return array();
		}

		$url = self::SEARCH . '?' . http_build_query(
			array(
				'action'    => 'wbgetentities',
				'ids'       => implode( '|', $ids ),
				'props'     => 'claims|labels|descriptions|sitelinks',
				'languages' => $lang . '|en',
				'format'    => 'json',
				'origin'    => '*',
			)
		);

		$data = $this->get_json( $url, array(), 6 * HOUR_IN_SECONDS );

		if ( is_wp_error( $data ) || empty( $data['entities'] ) ) {
			return array();
		}

		$types  = $this->kind_types( $kind );
		$hits   = array();
		$others = array();
		$art    = $this->poster_map( $data['entities'], $ids, $lang );

		// Iterate the candidate order so search relevance is preserved.
		foreach ( $ids as $qid ) {
			if ( empty( $data['entities'][ $qid ] ) || ! is_array( $data['entities'][ $qid ] ) ) {
				continue;
			}

			$entity = $data['entities'][ $qid ];
			$claims = isset( $entity['claims'] ) ? $entity['claims'] : array();
			$title  = $this->label_in( $entity, $lang );

			if ( '' === $title ) {
				continue;
			}

			$instances = $this->entity_ids( $claims, 'P31', 20 );
			$matched   = (bool) array_intersect( $instances, $types );

			// Anything that is not an audiovisual work at all is dropped: this
			// is what removes "founding date", companies and templates.
			if ( ! $matched && ! $this->looks_audiovisual( $claims ) ) {
				continue;
			}

			$date   = $this->date( $claims, 'P577' );
			$poster = isset( $art[ $qid ] ) ? $art[ $qid ] : '';

			if ( '' === $poster ) {
				$poster = $this->commons_image( $claims, 'P18', 400 );
			}

			$hit = array(
				'provider'  => $this->id,
				'remote_id' => $qid,
				'kind'      => $kind,
				'title'     => $title,
				'original'  => $this->original_title( $entity, $claims ),
				'year'      => $this->year_from_date( $date ),
				'poster'    => $poster,
				'rating'    => '',
				'overview'  => $this->description_in( $entity, $lang ),
			);

			// A year filter must never hide records whose date is unknown.
			if ( $year && $hit['year'] && (int) $hit['year'] !== $year ) {
				continue;
			}

			if ( $matched ) {
				$hits[] = $hit;
			} else {
				$others[] = $hit;
			}
		}

		// Exact-type matches first, looser audiovisual matches after them.
		return array_merge( $hits, $others );
	}

	/**
	 * Resolve poster artwork for a batch of entities.
	 *
	 * Film posters are non-free, so they live on the language Wikipedias rather
	 * than on Commons — which is why Wikidata's own P18 claim is almost always
	 * empty for movies. Each entity's Wikipedia sitelink is therefore resolved
	 * to that article's lead image with a single batched request per wiki.
	 *
	 * @param array  $entities Hydrated entity payloads.
	 * @param array  $ids      Candidate Q-ids.
	 * @param string $lang     Preferred language code.
	 * @return array Map of Q-id => image URL.
	 */
	protected function poster_map( $entities, $ids, $lang ) {
		$wikis = array();

		// The preferred language first so localized posters win.
		foreach ( array_unique( array( $lang, 'en' ) ) as $code ) {
			$site = $code . 'wiki';

			foreach ( $ids as $qid ) {
				if ( empty( $entities[ $qid ]['sitelinks'][ $site ]['title'] ) ) {
					continue;
				}

				$wikis[ $code ][ (string) $entities[ $qid ]['sitelinks'][ $site ]['title'] ] = $qid;
			}
		}

		$out = array();

		foreach ( $wikis as $code => $titles ) {
			if ( ! $titles ) {
				continue;
			}

			foreach ( $this->page_images( $code, array_keys( $titles ) ) as $title => $image ) {
				if ( isset( $titles[ $title ] ) && ! isset( $out[ $titles[ $title ] ] ) ) {
					$out[ $titles[ $title ] ] = $image;
				}
			}
		}

		return $out;
	}

	/**
	 * Fetch lead images for a batch of Wikipedia article titles.
	 *
	 * `pilicense=any` is required: without it the API silently omits every
	 * non-free image, which is exactly what film posters are.
	 *
	 * @param string $code   Wikipedia language code.
	 * @param array  $titles Article titles.
	 * @return array Map of title => image URL.
	 */
	protected function page_images( $code, $titles ) {
		$titles = array_slice( array_values( array_filter( $titles ) ), 0, 40 );

		if ( ! $titles ) {
			return array();
		}

		$url = sprintf( self::WIKIPEDIA, rawurlencode( $code ) ) . '?' . http_build_query(
			array(
				'action'      => 'query',
				'prop'        => 'pageimages',
				'piprop'      => 'thumbnail',
				'pithumbsize' => 400,
				'pilicense'   => 'any',
				'titles'      => implode( '|', $titles ),
				'redirects'   => 1,
				'format'      => 'json',
				'origin'      => '*',
			)
		);

		$data = $this->get_json( $url, array(), DAY_IN_SECONDS );

		if ( is_wp_error( $data ) || empty( $data['query']['pages'] ) ) {
			return array();
		}

		$out = array();

		// Redirects are followed server side, so map the requested title back.
		$aliases = array();

		if ( ! empty( $data['query']['redirects'] ) ) {
			foreach ( $data['query']['redirects'] as $redirect ) {
				if ( ! empty( $redirect['from'] ) && ! empty( $redirect['to'] ) ) {
					$aliases[ (string) $redirect['to'] ][] = (string) $redirect['from'];
				}
			}
		}

		foreach ( $data['query']['pages'] as $page ) {
			if ( empty( $page['title'] ) || empty( $page['thumbnail']['source'] ) ) {
				continue;
			}

			$title = (string) $page['title'];
			$image = esc_url_raw( (string) $page['thumbnail']['source'] );

			if ( '' === $image ) {
				continue;
			}

			$out[ $title ] = $image;

			if ( ! empty( $aliases[ $title ] ) ) {
				foreach ( $aliases[ $title ] as $alias ) {
					$out[ $alias ] = $image;
				}
			}
		}

		return $out;
	}

	/**
	 * Whether an entity carries claims typical of an audiovisual work.
	 *
	 * Keeps legitimate records whose P31 uses a class not present in the map
	 * (Wikidata has hundreds of film subclasses) while still rejecting noise.
	 *
	 * @param array $claims Claims.
	 * @return bool
	 */
	protected function looks_audiovisual( $claims ) {
		foreach ( array( 'P345', 'P4947', 'P4086', 'P57', 'P161', 'P1476', 'P462' ) as $prop ) {
			if ( ! empty( $claims[ $prop ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Best available original title for an entity.
	 *
	 * @param array $entity Entity payload.
	 * @param array $claims Claims.
	 * @return string
	 */
	protected function original_title( $entity, $claims ) {
		$title = $this->value( $claims, 'P1476' );

		if ( '' !== $title ) {
			return $title;
		}

		return $this->label_in( $entity, 'en' );
	}

	/**
	 * Fetch a full Wikidata record.
	 *
	 * @param string $remote_id Q-id.
	 * @param string $kind      Kind.
	 * @param array  $args      Extra args.
	 * @return array|\WP_Error
	 */
	public function fetch( $remote_id, $kind = 'movie', $args = array() ) {
		$qid = strtoupper( preg_replace( '/[^QqDd0-9]/', '', (string) $remote_id ) );

		if ( ! preg_match( '/^Q\d+$/', $qid ) ) {
			return new \WP_Error( 'manacore_sources_bad_id', __( 'شناسه Wikidata نامعتبر است.', 'manacore' ) );
		}

		$lang = manacore_sources_get_option( 'wikidata_language', 'fa' );

		$url = 'https://www.wikidata.org/wiki/Special:EntityData/' . $qid . '.json';
		$doc = $this->get_json( $url, array(), 6 * HOUR_IN_SECONDS );

		if ( is_wp_error( $doc ) ) {
			return $doc;
		}

		$entity = isset( $doc['entities'][ $qid ] ) ? $doc['entities'][ $qid ] : null;

		if ( ! is_array( $entity ) ) {
			return new \WP_Error( 'manacore_sources_empty', __( 'موردی در Wikidata یافت نشد.', 'manacore' ) );
		}

		$claims = isset( $entity['claims'] ) ? $entity['claims'] : array();

		$rec = $this->blank_record();

		$rec['kind']      = $kind;
		$rec['remote_id'] = $qid;
		$rec['title']          = $this->label_in( $entity, $lang );
		$rec['original_title'] = $this->original_title( $entity, $claims );

		$rec['overview']     = $this->description_in( $entity, $lang );
		$rec['release_date'] = $this->date( $claims, 'P577' );
		$rec['year']         = $this->year_from_date( $rec['release_date'] );
		$rec['runtime']      = $this->number( $claims, 'P2047' );
		$rec['imdb_id']      = $this->value( $claims, 'P345' );
		$rec['tmdb_id']      = $this->value( $claims, 'P4947' );
		$rec['mal_id']       = $this->value( $claims, 'P4086' );
		$rec['source_url']   = 'https://www.wikidata.org/wiki/' . $qid;
		$rec['poster']       = $this->commons_image( $claims, 'P18', 780 );
		$rec['logo']         = $this->commons_image( $claims, 'P154' );

		// Prefer the Wikipedia lead image, which is the actual film poster.
		if ( '' === $rec['poster'] ) {
			$art = $this->poster_map( array( $qid => $entity ), array( $qid ), $lang );

			if ( ! empty( $art[ $qid ] ) ) {
				$rec['poster'] = $art[ $qid ];
			}
		}
		$rec['trailer']      = $this->value( $claims, 'P1651' );

		if ( $rec['trailer'] && false === strpos( $rec['trailer'], 'http' ) ) {
			$rec['trailer'] = 'https://www.youtube.com/watch?v=' . $rec['trailer'];
		}

		// Number of seasons / episodes.
		$rec['total_seasons']  = $this->number( $claims, 'P2437' );
		$rec['total_episodes'] = $this->number( $claims, 'P1113' );

		// Entity-reference properties resolved to labels in one batch.
		$refs = array(
			'genres'    => 'P136',
			'countries' => 'P495',
			'languages' => 'P364',
			'networks'  => 'P449',
			'studios'   => 'P272',
		);

		$crew = array(
			'director' => 'P57',
			'writer'   => 'P58',
			'producer' => 'P162',
			'composer' => 'P86',
		);

		$ids = array();

		foreach ( array_merge( $refs, $crew ) as $prop ) {
			$ids = array_merge( $ids, $this->entity_ids( $claims, $prop ) );
		}

		$cast_ids = $this->entity_ids( $claims, 'P161', 20 );
		$ids      = array_merge( $ids, $cast_ids );
		$labels   = $this->labels_for( array_unique( $ids ), $lang );

		foreach ( $refs as $field => $prop ) {
			foreach ( $this->entity_ids( $claims, $prop ) as $id ) {
				if ( isset( $labels[ $id ] ) && ! in_array( $labels[ $id ], $rec[ $field ], true ) ) {
					$rec[ $field ][] = $labels[ $id ];
				}
			}
		}

		foreach ( $crew as $field => $prop ) {
			$names = array();

			foreach ( $this->entity_ids( $claims, $prop ) as $id ) {
				if ( isset( $labels[ $id ] ) ) {
					$names[] = $labels[ $id ];
				}
			}

			$rec[ $field ] = implode( '، ', array_slice( array_unique( $names ), 0, 5 ) );
		}

		$limit = (int) manacore_sources_get_option( 'cast_limit', 15 );
		$limit = $limit > 0 ? $limit : 15;

		foreach ( array_slice( $cast_ids, 0, $limit ) as $id ) {
			if ( ! isset( $labels[ $id ] ) ) {
				continue;
			}

			$rec['cast'][] = array(
				'name'      => $labels[ $id ],
				'character' => '',
				'photo'     => '',
				'person_id' => '',
			);
		}

		return $rec;
	}

	/* ---------------------------------------------------------------------
	 * Claim readers
	 * ------------------------------------------------------------------ */

	/**
	 * Localized label with English fallback.
	 *
	 * @param array  $entity Entity payload.
	 * @param string $lang   Language code.
	 * @return string
	 */
	protected function label_in( $entity, $lang ) {
		if ( ! empty( $entity['labels'][ $lang ]['value'] ) ) {
			return (string) $entity['labels'][ $lang ]['value'];
		}

		if ( ! empty( $entity['labels']['en']['value'] ) ) {
			return (string) $entity['labels']['en']['value'];
		}

		return '';
	}

	/**
	 * Localized description with English fallback.
	 *
	 * @param array  $entity Entity payload.
	 * @param string $lang   Language code.
	 * @return string
	 */
	protected function description_in( $entity, $lang ) {
		if ( ! empty( $entity['descriptions'][ $lang ]['value'] ) ) {
			return (string) $entity['descriptions'][ $lang ]['value'];
		}

		if ( ! empty( $entity['descriptions']['en']['value'] ) ) {
			return (string) $entity['descriptions']['en']['value'];
		}

		return '';
	}

	/**
	 * Read the first string value of a property.
	 *
	 * @param array  $claims Claims.
	 * @param string $prop   Property id.
	 * @return string
	 */
	protected function value( $claims, $prop ) {
		if ( empty( $claims[ $prop ][0]['mainsnak']['datavalue']['value'] ) ) {
			return '';
		}

		$value = $claims[ $prop ][0]['mainsnak']['datavalue']['value'];

		if ( is_string( $value ) ) {
			return $value;
		}

		if ( is_array( $value ) && isset( $value['text'] ) ) {
			return (string) $value['text'];
		}

		return '';
	}

	/**
	 * Read a numeric quantity property.
	 *
	 * @param array  $claims Claims.
	 * @param string $prop   Property id.
	 * @return string
	 */
	protected function number( $claims, $prop ) {
		if ( empty( $claims[ $prop ][0]['mainsnak']['datavalue']['value']['amount'] ) ) {
			return '';
		}

		$amount = (string) $claims[ $prop ][0]['mainsnak']['datavalue']['value']['amount'];

		return (string) absint( ltrim( $amount, '+' ) );
	}

	/**
	 * Read a time property as Y-m-d.
	 *
	 * @param array  $claims Claims.
	 * @param string $prop   Property id.
	 * @return string
	 */
	protected function date( $claims, $prop ) {
		if ( empty( $claims[ $prop ] ) || ! is_array( $claims[ $prop ] ) ) {
			return '';
		}

		$best = '';

		foreach ( $claims[ $prop ] as $claim ) {
			if ( empty( $claim['mainsnak']['datavalue']['value']['time'] ) ) {
				continue;
			}

			$time = ltrim( (string) $claim['mainsnak']['datavalue']['value']['time'], '+' );
			$date = substr( $time, 0, 10 );

			if ( '' === $best || $date < $best ) {
				$best = $date;
			}
		}

		return str_replace( '-00', '-01', $best );
	}

	/**
	 * Collect referenced entity ids for a property.
	 *
	 * @param array  $claims Claims.
	 * @param string $prop   Property id.
	 * @param int    $limit  Max ids.
	 * @return array
	 */
	protected function entity_ids( $claims, $prop, $limit = 10 ) {
		if ( empty( $claims[ $prop ] ) || ! is_array( $claims[ $prop ] ) ) {
			return array();
		}

		$out = array();

		foreach ( $claims[ $prop ] as $claim ) {
			if ( empty( $claim['mainsnak']['datavalue']['value']['id'] ) ) {
				continue;
			}

			$out[] = (string) $claim['mainsnak']['datavalue']['value']['id'];

			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * Resolve entity ids to labels in one request.
	 *
	 * @param array  $ids  Q-ids.
	 * @param string $lang Language code.
	 * @return array Map of id => label.
	 */
	protected function labels_for( $ids, $lang ) {
		$ids = array_values( array_filter( array_unique( $ids ) ) );

		if ( ! $ids ) {
			return array();
		}

		$labels = array();

		// wbgetentities accepts up to 50 ids per call.
		foreach ( array_chunk( $ids, 50 ) as $chunk ) {
			$url = self::SEARCH . '?' . http_build_query(
				array(
					'action'    => 'wbgetentities',
					'ids'       => implode( '|', $chunk ),
					'props'     => 'labels',
					'languages' => $lang . '|en',
					'format'    => 'json',
					'origin'    => '*',
				)
			);

			$data = $this->get_json( $url, array(), DAY_IN_SECONDS );

			if ( is_wp_error( $data ) || empty( $data['entities'] ) ) {
				continue;
			}

			foreach ( $data['entities'] as $id => $entity ) {
				$label = $this->label_in( $entity, $lang );

				if ( '' !== $label ) {
					$labels[ $id ] = $label;
				}
			}
		}

		return $labels;
	}

	/**
	 * Build a Commons image URL from a file-name claim.
	 *
	 * @param array  $claims Claims.
	 * @param string $prop   Property id.
	 * @param int    $width  Requested render width in pixels.
	 * @return string
	 */
	protected function commons_image( $claims, $prop, $width = 780 ) {
		$file = $this->value( $claims, $prop );

		if ( '' === $file ) {
			return '';
		}

		return 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode( $file ) . '?width=' . absint( $width );
	}
}
