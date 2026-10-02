<?php
/**
 * REST endpoints for the Sources plugin.
 *
 * @package ManaCore\Sources
 */

namespace ManaCore\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller.
 */
class Rest {

	use Singleton;

	const NS = 'manacore/v1';

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Permission callback: editing the target post.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_edit( $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		}

		return current_user_can( 'edit_posts' );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/sources/search',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'query'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'kind'     => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'provider' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'year'     => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'post_id'  => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/sources/preview',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'provider'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'remote_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'kind'      => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'post_id'   => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/sources/import',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'provider'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'remote_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'kind'      => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'overwrite' => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'sections'  => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/sources/status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);
	}

	/**
	 * Search handler.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function search( $request ) {
		$results = Registry::instance()->search(
			$request->get_param( 'query' ),
			$this->kind( $request ),
			(string) $request->get_param( 'provider' ),
			array( 'year' => absint( $request->get_param( 'year' ) ) )
		);

		if ( is_wp_error( $results ) ) {
			return $results;
		}

		return rest_ensure_response(
			array(
				'mode'    => manacore_sources_active_mode(),
				'kind'    => $this->kind( $request ),
				'results' => $results,
			)
		);
	}

	/**
	 * Preview handler – fetch without writing.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function preview( $request ) {
		$record = Registry::instance()->fetch(
			$request->get_param( 'provider' ),
			$request->get_param( 'remote_id' ),
			$this->kind( $request )
		);

		if ( is_wp_error( $record ) ) {
			return $record;
		}

		return rest_ensure_response(
			array(
				'record'  => $record,
				'summary' => $this->summarize( $record ),
			)
		);
	}

	/**
	 * Import handler.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import( $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'manacore_sources_forbidden',
				__( 'دسترسی لازم را ندارید.', 'manacore' ),
				array( 'status' => 403 )
			);
		}

		$record = Registry::instance()->fetch(
			$request->get_param( 'provider' ),
			$request->get_param( 'remote_id' ),
			$this->kind( $request )
		);

		if ( is_wp_error( $record ) ) {
			return $record;
		}

		$sections = (array) $request->get_param( 'sections' );
		$options  = array( 'overwrite' => (bool) $request->get_param( 'overwrite' ) );

		foreach ( array( 'title', 'content', 'meta', 'taxonomies', 'cast', 'seasons', 'gallery', 'thumbnail' ) as $section ) {
			if ( array_key_exists( $section, $sections ) ) {
				$options[ $section ] = ! empty( $sections[ $section ] );
			}
		}

		$changed = Importer::instance()->import( $post_id, $record, $options );

		if ( is_wp_error( $changed ) ) {
			return $changed;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'changed' => $changed,
				'summary' => $this->summarize( $record ),
				'message' => sprintf(
					/* translators: 1: number of fields, 2: number of taxonomies. */
					__( '%1$d فیلد و %2$d دسته‌بندی به‌روزرسانی شد.', 'manacore' ),
					count( $changed['fields'] ),
					count( $changed['taxonomies'] )
				),
			)
		);
	}

	/**
	 * Status handler – which mode and providers are active.
	 *
	 * @return \WP_REST_Response
	 */
	public function status() {
		$providers = array();

		foreach ( Registry::instance()->all() as $id => $provider ) {
			$providers[] = array(
				'id'           => $id,
				'label'        => $provider->get_label(),
				'requires_key' => $provider->requires_key(),
				'available'    => $provider->is_available(),
			);
		}

		return rest_ensure_response(
			array(
				'mode'      => manacore_sources_active_mode(),
				'has_key'   => manacore_sources_has_key(),
				'providers' => $providers,
			)
		);
	}

	/**
	 * Resolve the kind for a request, falling back to the post type.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string
	 */
	protected function kind( $request ) {
		$kind = (string) $request->get_param( 'kind' );

		if ( array_key_exists( $kind, manacore_sources_kinds() ) ) {
			return $kind;
		}

		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( $post_id ) {
			return manacore_sources_kind_for_post_type( get_post_type( $post_id ) );
		}

		return 'movie';
	}

	/**
	 * Build a short human readable summary of a record.
	 *
	 * @param array $record Record.
	 * @return array
	 */
	protected function summarize( $record ) {
		return array(
			'title'      => isset( $record['title'] ) ? $record['title'] : '',
			'original'   => isset( $record['original_title'] ) ? $record['original_title'] : '',
			'year'       => isset( $record['year'] ) ? $record['year'] : '',
			'runtime'    => isset( $record['runtime'] ) ? $record['runtime'] : '',
			'poster'     => isset( $record['poster'] ) ? $record['poster'] : '',
			'genres'     => implode( '، ', array_slice( (array) $record['genres'], 0, 6 ) ),
			'cast_count' => count( (array) $record['cast'] ),
			'seasons'    => count( (array) $record['seasons'] ),
			'provider'   => isset( $record['provider_label'] ) ? $record['provider_label'] : '',
			'source_url' => isset( $record['source_url'] ) ? $record['source_url'] : '',
		);
	}
}
