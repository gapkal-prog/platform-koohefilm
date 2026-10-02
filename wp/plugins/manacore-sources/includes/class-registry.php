<?php
/**
 * Provider registry and orchestration.
 *
 * @package ManaCore\Sources
 */

namespace ManaCore\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Registry.
 *
 * Owns the provider list and decides which provider answers a given request,
 * based on the active mode (TMDB key vs. free sources) and the media kind.
 */
class Registry {

	use Singleton;

	/**
	 * Registered providers keyed by id.
	 *
	 * @var Provider[]
	 */
	protected $providers = array();

	/**
	 * Whether providers have been built.
	 *
	 * @var bool
	 */
	protected $booted = false;

	/**
	 * Register hooks.
	 */
	public function hooks() {
		// Providers are built lazily; nothing to hook yet.
		add_action( 'init', array( $this, 'boot' ), 5 );
	}

	/**
	 * Build the provider list.
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$providers = array(
			new Provider_Tmdb(),
			new Provider_Wikidata(),
			new Provider_Tvmaze(),
			new Provider_Jikan(),
			new Provider_Omdb(),
		);

		/**
		 * Filter the registered providers.
		 *
		 * @param Provider[] $providers Provider instances.
		 */
		$providers = apply_filters( 'manacore_sources_providers', $providers );

		foreach ( $providers as $provider ) {
			if ( $provider instanceof Provider ) {
				$this->providers[ $provider->get_id() ] = $provider;
			}
		}
	}

	/**
	 * Get all providers.
	 *
	 * @return Provider[]
	 */
	public function all() {
		$this->boot();

		return $this->providers;
	}

	/**
	 * Get one provider.
	 *
	 * @param string $id Provider id.
	 * @return Provider|null
	 */
	public function get( $id ) {
		$this->boot();

		return isset( $this->providers[ $id ] ) ? $this->providers[ $id ] : null;
	}

	/**
	 * Providers usable for a kind right now, in priority order.
	 *
	 * @param string $kind Media kind.
	 * @return Provider[]
	 */
	public function available_for( $kind ) {
		$this->boot();

		$mode = manacore_sources_active_mode();
		$out  = array();

		foreach ( $this->priority( $kind, $mode ) as $id ) {
			$provider = $this->get( $id );

			if ( $provider && $provider->is_available() && $provider->supports( $kind ) ) {
				$out[ $id ] = $provider;
			}
		}

		return $out;
	}

	/**
	 * Provider priority for a kind and mode.
	 *
	 * In `tmdb` mode the key-based provider always leads, per the project spec:
	 * "when a key is registered, prefer it by default". Free providers stay in
	 * the list as automatic fallbacks so a TMDB outage never breaks the editor.
	 *
	 * @param string $kind Media kind.
	 * @param string $mode Active mode.
	 * @return array Ordered provider ids.
	 */
	public function priority( $kind, $mode ) {
		if ( 'tmdb' === $mode ) {
			$order = array(
				'movie'  => array( 'tmdb', 'wikidata', 'omdb' ),
				'series' => array( 'tmdb', 'tvmaze', 'wikidata', 'omdb' ),
				'anime'  => array( 'tmdb', 'jikan', 'wikidata', 'omdb' ),
			);
		} else {
			$order = array(
				'movie'  => array( 'wikidata', 'omdb' ),
				'series' => array( 'tvmaze', 'wikidata', 'omdb' ),
				'anime'  => array( 'jikan', 'tvmaze', 'wikidata', 'omdb' ),
			);
		}

		$ids = isset( $order[ $kind ] ) ? $order[ $kind ] : $order['movie'];

		/**
		 * Filter the provider priority list.
		 *
		 * @param array  $ids  Ordered provider ids.
		 * @param string $kind Media kind.
		 * @param string $mode Active mode.
		 */
		return apply_filters( 'manacore_sources_priority', $ids, $kind, $mode );
	}

	/**
	 * Search across providers.
	 *
	 * @param string $query    Term.
	 * @param string $kind     Media kind.
	 * @param string $provider Optional explicit provider id.
	 * @param array  $args     Extra args.
	 * @return array|\WP_Error
	 */
	public function search( $query, $kind = 'movie', $provider = '', $args = array() ) {
		$query = trim( (string) $query );

		if ( '' === $query ) {
			return new \WP_Error( 'manacore_sources_empty_query', __( 'عبارت جستجو را وارد کنید.', 'manacore' ) );
		}

		$providers = $this->available_for( $kind );

		if ( $provider ) {
			$single = $this->get( $provider );

			if ( ! $single ) {
				return new \WP_Error( 'manacore_sources_unknown', __( 'منبع انتخاب‌شده نامعتبر است.', 'manacore' ) );
			}

			if ( ! $single->is_available() ) {
				return new \WP_Error(
					'manacore_sources_unavailable',
					__( 'این منبع در حال حاضر در دسترس نیست (احتمالاً کلید ثبت نشده است).', 'manacore' )
				);
			}

			$providers = array( $provider => $single );
		}

		if ( ! $providers ) {
			return new \WP_Error( 'manacore_sources_none', __( 'هیچ منبعی برای این نوع محتوا فعال نیست.', 'manacore' ) );
		}

		$results = array();
		$errors  = array();

		foreach ( $providers as $id => $instance ) {
			$hits = $instance->search( $query, $kind, $args );

			if ( is_wp_error( $hits ) ) {
				$errors[ $id ] = $hits->get_error_message();
				continue;
			}

			foreach ( $hits as $hit ) {
				$hit['provider_label'] = $instance->get_label();
				$results[]             = $hit;
			}

			// Stop at the first provider that produced results unless the caller
			// explicitly asked to aggregate everything.
			if ( $results && empty( $args['aggregate'] ) ) {
				break;
			}
		}

		if ( ! $results && $errors ) {
			return new \WP_Error( 'manacore_sources_failed', implode( ' | ', $errors ) );
		}

		return $results;
	}

	/**
	 * Fetch a full record, then enrich it from secondary providers.
	 *
	 * @param string $provider  Provider id.
	 * @param string $remote_id Remote id.
	 * @param string $kind      Media kind.
	 * @param array  $args      Extra args.
	 * @return array|\WP_Error
	 */
	public function fetch( $provider, $remote_id, $kind = 'movie', $args = array() ) {
		$instance = $this->get( $provider );

		if ( ! $instance ) {
			return new \WP_Error( 'manacore_sources_unknown', __( 'منبع انتخاب‌شده نامعتبر است.', 'manacore' ) );
		}

		$record = $instance->fetch( $remote_id, $kind, $args );

		if ( is_wp_error( $record ) ) {
			return $record;
		}

		if ( empty( $args['no_enrich'] ) ) {
			$record = $this->enrich( $record, $kind );
		}

		/**
		 * Filter the fetched record before it reaches the importer.
		 *
		 * @param array  $record Normalized record.
		 * @param string $kind   Media kind.
		 */
		return apply_filters( 'manacore_sources_record', $record, $kind );
	}

	/**
	 * Fill gaps in a record using secondary providers.
	 *
	 * @param array  $record Base record.
	 * @param string $kind   Media kind.
	 * @return array
	 */
	protected function enrich( $record, $kind ) {
		if ( ! manacore_sources_get_option( 'enrich', 1 ) ) {
			return $record;
		}

		// IMDb ratings via OMDb when an IMDb id is known.
		$omdb = $this->get( 'omdb' );

		if ( $omdb && $omdb->is_available() && ! empty( $record['imdb_id'] ) && '' === $record['imdb_rating'] ) {
			$extra = $omdb->fetch( $record['imdb_id'], $kind, array() );

			if ( ! is_wp_error( $extra ) ) {
				$record = $this->merge( $record, $extra );
			}
		}

		// MAL score for anime when the base provider was not Jikan.
		if ( 'anime' === $kind && 'jikan' !== $record['provider'] && '' === $record['mal_rating'] ) {
			$jikan = $this->get( 'jikan' );

			if ( $jikan && $jikan->is_available() ) {
				$hits = $jikan->search( $record['original_title'] ? $record['original_title'] : $record['title'], 'anime' );

				if ( ! is_wp_error( $hits ) && ! empty( $hits[0]['remote_id'] ) ) {
					$extra = $jikan->fetch( $hits[0]['remote_id'], 'anime' );

					if ( ! is_wp_error( $extra ) ) {
						$record = $this->merge( $record, $extra );
					}
				}
			}
		}

		return $record;
	}

	/**
	 * Merge a secondary record into a primary one without overwriting values.
	 *
	 * @param array $base  Primary record.
	 * @param array $extra Secondary record.
	 * @return array
	 */
	protected function merge( $base, $extra ) {
		foreach ( $extra as $key => $value ) {
			if ( in_array( $key, array( 'provider', 'provider_label', 'remote_id', 'kind', 'source_url' ), true ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				if ( empty( $base[ $key ] ) && $value ) {
					$base[ $key ] = $value;
				}
				continue;
			}

			if ( ( ! isset( $base[ $key ] ) || '' === $base[ $key ] ) && '' !== $value ) {
				$base[ $key ] = $value;
			}
		}

		return $base;
	}
}
