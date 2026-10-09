<?php
/**
 * سئو و داده‌های ساختاریافته (Schema.org).
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Seo
 */
class Seo {

	use Singleton;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'wp_head', array( $this, 'meta_tags' ), 2 );
		add_action( 'wp_head', array( $this, 'schema' ), 3 );
		add_filter( 'document_title_parts', array( $this, 'title' ) );
	}

	/**
	 * آیا افزونه‌ی سئوی دیگری فعال است؟
	 *
	 * @return bool
	 */
	protected function has_seo_plugin() {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || class_exists( 'All_in_One_SEO_Pack' );
	}

	/**
	 * جایگزینی عنوان صفحه.
	 *
	 * @param array $parts بخش‌های عنوان.
	 * @return array
	 */
	public function title( $parts ) {
		if ( $this->has_seo_plugin() || ! is_singular( $this->types() ) ) {
			return $parts;
		}
		$custom = get_post_meta( get_the_ID(), 'manacore_seo_title', true );
		if ( $custom ) {
			$parts['title'] = $custom;
		}
		return $parts;
	}

	/**
	 * انواع محتوای تحت پوشش.
	 *
	 * @return array
	 */
	protected function types() {
		return array_merge( manacore_title_post_types(), array( 'episode' ) );
	}

	/**
	 * تگ‌های متا و اوپن‌گراف.
	 */
	public function meta_tags() {
		if ( $this->has_seo_plugin() || ! is_singular( $this->types() ) ) {
			return;
		}

		$post_id     = get_the_ID();
		$description = get_post_meta( $post_id, 'manacore_seo_description', true );
		if ( ! $description ) {
			$description = wp_strip_all_tags( get_the_excerpt( $post_id ) );
		}
		$description = wp_trim_words( (string) $description, 32, '…' );
		$image       = manacore_poster_url( $post_id, 'large' );

		echo "\n<!-- ManaCore SEO -->\n";
		printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
		printf( '<meta property="og:type" content="video.movie" />' . "\n" );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( get_the_title( $post_id ) ) );
		printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $description ) );
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( get_permalink( $post_id ) ) );
		printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $image ) );
		printf( '<meta name="twitter:card" content="summary_large_image" />' . "\n" );
		printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( get_the_title( $post_id ) ) );
		printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $image ) );
	}

	/**
	 * خروجی JSON-LD.
	 */
	public function schema() {
		if ( ! is_singular( $this->types() ) ) {
			return;
		}

		$post_id   = get_the_ID();
		$post_type = get_post_type( $post_id );

		$schema_type = 'movie' === $post_type ? 'Movie' : ( 'episode' === $post_type ? 'TVEpisode' : 'TVSeries' );

		$data = array(
			'@context'    => 'https://schema.org',
			'@type'       => $schema_type,
			'name'        => get_the_title( $post_id ),
			'url'         => get_permalink( $post_id ),
			'image'       => manacore_poster_url( $post_id, 'large' ),
			'description' => wp_strip_all_tags( get_the_excerpt( $post_id ) ),
		);

		$original = get_post_meta( $post_id, 'manacore_original_title', true );
		if ( $original ) {
			$data['alternateName'] = $original;
		}

		$date = get_post_meta( $post_id, 'manacore_release_date', true );
		if ( $date ) {
			$data['datePublished'] = $date;
		}

		$runtime = (int) get_post_meta( $post_id, 'manacore_runtime', true );
		if ( $runtime ) {
			$data['duration'] = 'PT' . $runtime . 'M';
		}

		$genres = get_the_terms( $post_id, 'genre' );
		if ( $genres && ! is_wp_error( $genres ) ) {
			$data['genre'] = wp_list_pluck( $genres, 'name' );
		}

		$directors = Crew::names( Crew::items( $post_id, 'director' ) );
		if ( $directors ) {
			$data['director'] = array_map(
				static function ( $name ) {
					return array(
						'@type' => 'Person',
						'name'  => $name,
					);
				},
				$directors
			);
		}

		$cast = get_post_meta( $post_id, 'manacore_cast', true );
		$cast = is_string( $cast ) ? json_decode( $cast, true ) : $cast;
		if ( is_array( $cast ) && $cast ) {
			$actors = array();
			foreach ( array_slice( $cast, 0, 10 ) as $person ) {
				if ( empty( $person['name'] ) ) {
					continue;
				}
				$actors[] = array(
					'@type' => 'Person',
					'name'  => $person['name'],
				);
			}
			if ( $actors ) {
				$data['actor'] = $actors;
			}
		}

		$rating = (float) get_post_meta( $post_id, 'manacore_imdb_rating', true );
		$votes  = (int) get_post_meta( $post_id, 'manacore_imdb_votes', true );
		if ( $rating > 0 ) {
			$data['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $rating,
				'bestRating'  => 10,
				'worstRating' => 0,
				'ratingCount' => $votes > 0 ? $votes : max( 1, (int) get_post_meta( $post_id, 'manacore_user_rating_count', true ) ),
			);
		}

		if ( 'episode' === $post_type ) {
			$season  = (int) get_post_meta( $post_id, 'manacore_season_number', true );
			$episode = (int) get_post_meta( $post_id, 'manacore_episode_number', true );
			$parent  = (int) get_post_meta( $post_id, 'manacore_parent_title', true );
			if ( $episode ) {
				$data['episodeNumber'] = $episode;
			}
			if ( $season ) {
				$data['partOfSeason'] = array(
					'@type'        => 'TVSeason',
					'seasonNumber' => $season,
				);
			}
			if ( $parent ) {
				$data['partOfSeries'] = array(
					'@type' => 'TVSeries',
					'name'  => get_the_title( $parent ),
					'url'   => get_permalink( $parent ),
				);
			}
		}

		$data = apply_filters( 'manacore_schema_data', $data, $post_id );

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) // phpcs:ignore WordPress.Security.EscapeOutput
		);
	}
}
