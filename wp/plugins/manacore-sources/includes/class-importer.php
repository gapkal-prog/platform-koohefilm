<?php
/**
 * Import normalized records into WordPress posts.
 *
 * @package ManaCore\Sources
 */

namespace ManaCore\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Importer.
 *
 * Writes a normalized provider record into a post: core fields, ManaCore meta,
 * taxonomy terms and (optionally) sideloaded images.
 */
class Importer {

	use Singleton;

	/**
	 * Register hooks.
	 */
	public function hooks() {
		// Reserved for future scheduled refresh hooks.
	}

	/**
	 * Map of record keys to ManaCore meta keys (scalar fields only).
	 *
	 * @return array
	 */
	public function meta_map() {
		return apply_filters(
			'manacore_sources_meta_map',
			array(
				'original_title'  => 'manacore_original_title',
				'tagline'         => 'manacore_tagline',
				'release_date'    => 'manacore_release_date',
				'year'            => 'manacore_year',
				'runtime'         => 'manacore_runtime',
				'status'          => 'manacore_status',
				'poster'          => 'manacore_poster_url',
				'backdrop'        => 'manacore_backdrop_url',
				'logo'            => 'manacore_logo_url',
				'trailer'         => 'manacore_trailer_url',
				'imdb_id'         => 'manacore_imdb_id',
				'imdb_rating'     => 'manacore_imdb_rating',
				'imdb_votes'      => 'manacore_imdb_votes',
				'tmdb_id'         => 'manacore_tmdb_id',
				'tmdb_rating'     => 'manacore_tmdb_rating',
				'mal_id'          => 'manacore_mal_id',
				'mal_rating'      => 'manacore_mal_rating',
				'metascore'       => 'manacore_metascore',
				'rotten_score'    => 'manacore_rotten_score',
				'director'        => 'manacore_director',
				'writer'          => 'manacore_writer',
				'producer'        => 'manacore_producer',
				'composer'        => 'manacore_composer',
				'total_seasons'   => 'manacore_total_seasons',
				'total_episodes'  => 'manacore_total_episodes',
				'episode_runtime' => 'manacore_episode_runtime',
				'air_day'         => 'manacore_air_day',
				'next_episode'    => 'manacore_next_episode_date',
			)
		);
	}

	/**
	 * Map of record keys to taxonomies.
	 *
	 * @return array
	 */
	public function taxonomy_map() {
		return apply_filters(
			'manacore_sources_taxonomy_map',
			array(
				'genres'    => 'genre',
				'countries' => 'country',
				'languages' => 'language',
				'networks'  => 'network',
				'studios'   => 'studio',
			)
		);
	}

	/**
	 * Import a record into a post.
	 *
	 * @param int   $post_id Target post id.
	 * @param array $record  Normalized record.
	 * @param array $options Import options.
	 * @return array|\WP_Error Summary of what changed.
	 */
	public function import( $post_id, $record, $options = array() ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error( 'manacore_sources_no_post', __( 'نوشته مقصد پیدا نشد.', 'manacore' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'manacore_sources_forbidden', __( 'دسترسی لازم را ندارید.', 'manacore' ) );
		}

		$options = wp_parse_args(
			$options,
			array(
				'overwrite'   => (bool) manacore_sources_get_option( 'overwrite', 0 ),
				'title'       => true,
				'content'     => true,
				'meta'        => true,
				'taxonomies'  => true,
				'cast'        => true,
				'seasons'     => true,
				'gallery'     => true,
				'thumbnail'   => (bool) manacore_sources_get_option( 'sideload', 1 ),
			)
		);

		$changed = array(
			'fields'     => array(),
			'taxonomies' => array(),
			'thumbnail'  => false,
		);

		$overwrite = ! empty( $options['overwrite'] );

		/* ---------------- Core post fields ---------------- */

		$postarr = array( 'ID' => $post_id );

		if ( ! empty( $options['title'] ) && ! empty( $record['title'] ) ) {
			$blank = ( '' === trim( (string) $post->post_title ) || __( 'پیش‌نویس خودکار', 'manacore' ) === $post->post_title );

			if ( $overwrite || $blank || 'auto-draft' === $post->post_status ) {
				$postarr['post_title'] = sanitize_text_field( $record['title'] );
				$changed['fields'][]   = 'title';
			}
		}

		if ( ! empty( $options['content'] ) && ! empty( $record['overview'] ) ) {
			$overview = wp_kses_post( $record['overview'] );

			if ( $overwrite || '' === trim( (string) $post->post_excerpt ) ) {
				$postarr['post_excerpt'] = wp_trim_words( wp_strip_all_tags( $overview ), 55 );
				$changed['fields'][]     = 'excerpt';
			}

			if ( $overwrite || '' === trim( (string) $post->post_content ) ) {
				$postarr['post_content'] = $this->build_content( $overview );
				$changed['fields'][]     = 'content';
			}
		}

		if ( count( $postarr ) > 1 ) {
			wp_update_post( $postarr );
		}

		/* ---------------- Scalar meta ---------------- */

		if ( ! empty( $options['meta'] ) ) {
			foreach ( $this->meta_map() as $from => $meta_key ) {
				if ( ! isset( $record[ $from ] ) ) {
					continue;
				}

				$value = $record[ $from ];

				if ( is_array( $value ) || '' === trim( (string) $value ) ) {
					continue;
				}

				$existing = get_post_meta( $post_id, $meta_key, true );

				if ( ! $overwrite && '' !== trim( (string) $existing ) ) {
					continue;
				}

				update_post_meta( $post_id, $meta_key, $this->sanitize_meta( $meta_key, $value ) );
				$changed['fields'][] = $meta_key;
			}

			// Alternative titles (newline separated textarea).
			if ( ! empty( $record['alt_titles'] ) && is_array( $record['alt_titles'] ) ) {
				$existing = get_post_meta( $post_id, 'manacore_alt_titles', true );

				if ( $overwrite || '' === trim( (string) $existing ) ) {
					$titles = array_map( 'sanitize_text_field', $record['alt_titles'] );
					update_post_meta( $post_id, 'manacore_alt_titles', implode( "\n", $titles ) );
					$changed['fields'][] = 'manacore_alt_titles';
				}
			}

			// Spoken languages (comma separated text field).
			if ( ! empty( $record['languages'] ) && is_array( $record['languages'] ) ) {
				$existing = get_post_meta( $post_id, 'manacore_spoken_languages', true );

				if ( $overwrite || '' === trim( (string) $existing ) ) {
					$langs = array_map( 'sanitize_text_field', $record['languages'] );
					update_post_meta( $post_id, 'manacore_spoken_languages', implode( '، ', $langs ) );
					$changed['fields'][] = 'manacore_spoken_languages';
				}
			}

			// Country text mirror (in addition to the taxonomy).
			if ( ! empty( $record['countries'] ) && is_array( $record['countries'] ) ) {
				$existing = get_post_meta( $post_id, 'manacore_country', true );

				if ( $overwrite || '' === trim( (string) $existing ) ) {
					$countries = array_map( 'sanitize_text_field', $record['countries'] );
					update_post_meta( $post_id, 'manacore_country', implode( '، ', $countries ) );
					$changed['fields'][] = 'manacore_country';
				}
			}
		}

		/* ---------------- Repeaters ---------------- */

		if ( ! empty( $options['cast'] ) && ! empty( $record['cast'] ) ) {
			$existing = get_post_meta( $post_id, 'manacore_cast', true );
			$decoded  = json_decode( (string) $existing, true );

			if ( $overwrite || empty( $decoded ) ) {
				$cast = array();

				foreach ( $record['cast'] as $person ) {
					$name = isset( $person['name'] ) ? sanitize_text_field( $person['name'] ) : '';

					if ( '' === $name ) {
						continue;
					}

					$cast[] = array(
						'name'      => $name,
						'character' => isset( $person['character'] ) ? sanitize_text_field( $person['character'] ) : '',
						'photo'     => isset( $person['photo'] ) ? esc_url_raw( $person['photo'] ) : '',
						'person_id' => '',
					);
				}

				if ( $cast ) {
					// wp_slash mirrors how the metabox stores repeaters, so the
					// editor reads back exactly what the importer wrote.
					update_post_meta( $post_id, 'manacore_cast', wp_slash( wp_json_encode( $cast, JSON_UNESCAPED_UNICODE ) ) );
					$changed['fields'][] = 'manacore_cast';
				}
			}
		}

		if ( ! empty( $options['seasons'] ) && ! empty( $record['seasons'] ) ) {
			$existing = get_post_meta( $post_id, 'manacore_seasons', true );
			$decoded  = json_decode( (string) $existing, true );

			if ( $overwrite || empty( $decoded ) ) {
				$seasons = array();

				foreach ( $record['seasons'] as $season ) {
					$seasons[] = array(
						'number'   => isset( $season['number'] ) ? (string) absint( $season['number'] ) : '',
						'name'     => isset( $season['name'] ) ? sanitize_text_field( $season['name'] ) : '',
						'episodes' => isset( $season['episodes'] ) ? (string) absint( $season['episodes'] ) : '',
						'year'     => isset( $season['year'] ) ? sanitize_text_field( $season['year'] ) : '',
						'poster'   => isset( $season['poster'] ) ? esc_url_raw( $season['poster'] ) : '',
						'overview' => isset( $season['overview'] ) ? sanitize_textarea_field( $season['overview'] ) : '',
					);
				}

				if ( $seasons ) {
					update_post_meta( $post_id, 'manacore_seasons', wp_slash( wp_json_encode( $seasons, JSON_UNESCAPED_UNICODE ) ) );
					$changed['fields'][] = 'manacore_seasons';
				}
			}
		}

		if ( ! empty( $options['gallery'] ) && ! empty( $record['gallery'] ) ) {
			$existing = get_post_meta( $post_id, 'manacore_gallery', true );

			if ( $overwrite || '' === trim( (string) $existing ) ) {
				$urls = array_map( 'esc_url_raw', array_slice( (array) $record['gallery'], 0, 12 ) );
				update_post_meta( $post_id, 'manacore_gallery', implode( "\n", array_filter( $urls ) ) );
				$changed['fields'][] = 'manacore_gallery';
			}
		}

		/* ---------------- Taxonomies ---------------- */

		if ( ! empty( $options['taxonomies'] ) ) {
			foreach ( $this->taxonomy_map() as $from => $taxonomy ) {
				if ( empty( $record[ $from ] ) || ! is_array( $record[ $from ] ) ) {
					continue;
				}

				if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
					continue;
				}

				$terms = array_filter( array_map( 'sanitize_text_field', $record[ $from ] ) );

				if ( ! $terms ) {
					continue;
				}

				$result = wp_set_object_terms( $post_id, $terms, $taxonomy, ! $overwrite );

				if ( ! is_wp_error( $result ) ) {
					$changed['taxonomies'][] = $taxonomy;
				}
			}

			// Release year taxonomy.
			if ( ! empty( $record['year'] ) && taxonomy_exists( 'release_year' ) && is_object_in_taxonomy( $post->post_type, 'release_year' ) ) {
				wp_set_object_terms( $post_id, (string) absint( $record['year'] ), 'release_year', ! $overwrite );
				$changed['taxonomies'][] = 'release_year';
			}
		}

		/* ---------------- Featured image ---------------- */

		if ( ! empty( $options['thumbnail'] ) && ! empty( $record['poster'] ) ) {
			if ( $overwrite || ! has_post_thumbnail( $post_id ) ) {
				$attachment_id = $this->sideload( $record['poster'], $post_id, $record['title'] );

				if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
					set_post_thumbnail( $post_id, $attachment_id );
					$changed['thumbnail'] = true;
				}
			}
		}

		/* ---------------- Provenance ---------------- */

		update_post_meta( $post_id, '_manacore_source_provider', sanitize_key( $record['provider'] ) );
		update_post_meta( $post_id, '_manacore_source_id', sanitize_text_field( $record['remote_id'] ) );
		update_post_meta( $post_id, '_manacore_source_synced', current_time( 'mysql' ) );

		if ( ! empty( $record['source_url'] ) ) {
			update_post_meta( $post_id, '_manacore_source_url', esc_url_raw( $record['source_url'] ) );
		}

		$changed['fields'] = array_values( array_unique( $changed['fields'] ) );
		$changed['taxonomies'] = array_values( array_unique( $changed['taxonomies'] ) );

		/*
		 * The values as they were actually stored. The editor is a page that was
		 * rendered *before* this import ran, so its inputs still hold the old
		 * (usually empty) values. Returning the stored values lets the browser
		 * refresh those inputs in place, which is what makes "Import" visibly
		 * work without a page reload.
		 */
		$changed['values']    = $this->stored_values( $post_id, $changed['fields'] );
		$changed['post_title'] = get_post_field( 'post_title', $post_id );
		$changed['thumbnail_url'] = get_the_post_thumbnail_url( $post_id, 'medium' );

		/**
		 * Fires after a record has been imported.
		 *
		 * @param int   $post_id Post id.
		 * @param array $record  Normalized record.
		 * @param array $changed Summary.
		 */
		do_action( 'manacore_sources_imported', $post_id, $record, $changed );

		return $changed;
	}

	/**
	 * Read back the stored value of every field the import touched.
	 *
	 * Only ManaCore meta keys are exposed, and repeaters are returned decoded so
	 * the browser can rebuild their rows.
	 *
	 * @param int   $post_id Post id.
	 * @param array $fields  Field keys reported as changed.
	 * @return array Map of meta key => value.
	 */
	protected function stored_values( $post_id, $fields ) {
		$out = array();

		foreach ( $fields as $key ) {
			if ( 0 !== strpos( (string) $key, 'manacore_' ) ) {
				continue;
			}

			$value = get_post_meta( $post_id, $key, true );

			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$value = (string) $value;

			// Repeaters are stored as JSON; hand back structured rows.
			if ( in_array( $key, array( 'manacore_cast', 'manacore_seasons' ), true ) ) {
				$decoded = json_decode( $value, true );

				if ( is_array( $decoded ) ) {
					$out[ $key ] = $decoded;
					continue;
				}
			}

			$out[ $key ] = $value;
		}

		return $out;
	}

	/**
	 * Build block-editor friendly content from an overview.
	 *
	 * @param string $overview Overview text.
	 * @return string
	 */
	protected function build_content( $overview ) {
		$paragraphs = preg_split( '/\n{2,}/', trim( (string) $overview ) );
		$blocks     = array();

		foreach ( (array) $paragraphs as $paragraph ) {
			$paragraph = trim( $paragraph );

			if ( '' === $paragraph ) {
				continue;
			}

			$blocks[] = "<!-- wp:paragraph -->\n<p>" . esc_html( $paragraph ) . "</p>\n<!-- /wp:paragraph -->";
		}

		return implode( "\n\n", $blocks );
	}

	/**
	 * Sanitize a value for a given meta key.
	 *
	 * @param string $meta_key Meta key.
	 * @param mixed  $value    Raw value.
	 * @return mixed
	 */
	protected function sanitize_meta( $meta_key, $value ) {
		if ( false !== strpos( $meta_key, '_url' ) ) {
			return esc_url_raw( (string) $value );
		}

		if ( in_array( $meta_key, array( 'manacore_imdb_votes', 'manacore_runtime', 'manacore_year', 'manacore_total_seasons', 'manacore_total_episodes', 'manacore_episode_runtime', 'manacore_metascore', 'manacore_rotten_score' ), true ) ) {
			return (string) absint( $value );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sideload a remote image into the media library.
	 *
	 * @param string $url     Image URL.
	 * @param int    $post_id Parent post.
	 * @param string $title   Desired title.
	 * @return int|\WP_Error Attachment id.
	 */
	public function sideload( $url, $post_id, $title = '' ) {
		$url = esc_url_raw( (string) $url );

		if ( ! $url ) {
			return new \WP_Error( 'manacore_sources_bad_image', __( 'نشانی تصویر نامعتبر است.', 'manacore' ) );
		}

		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp = download_url( $url, 30 );

		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$name = basename( wp_parse_url( $url, PHP_URL_PATH ) );

		if ( ! $name || ! preg_match( '/\.(jpe?g|png|webp|gif)$/i', $name ) ) {
			$name = sanitize_title( $title ? $title : 'manacore-poster' ) . '.jpg';
		}

		$file = array(
			'name'     => $name,
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file, $post_id, $title );

		if ( is_wp_error( $attachment_id ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $tmp );
			return $attachment_id;
		}

		return (int) $attachment_id;
	}
}
