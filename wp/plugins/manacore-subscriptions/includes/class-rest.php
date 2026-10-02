<?php
/**
 * REST endpoints for subscription state.
 *
 * @package ManaCore\Subs
 */

namespace ManaCore\Subs;

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
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/subscription',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'me' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/subscription/access/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'access' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/subscription/grant',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'grant' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'user_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'level'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'days'    => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'note'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/subscription/revoke',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'revoke' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'user_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Capability check for the management routes.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manacore_manage_subscriptions' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Current user's subscription snapshot.
	 *
	 * @return \WP_REST_Response
	 */
	public function me() {
		$access  = Access::instance();
		$plans   = Plans::instance();
		$level   = $access->user_level();
		$expires = $access->expiry();

		return rest_ensure_response(
			array(
				'logged_in'     => is_user_logged_in(),
				'level'         => $level,
				'label'         => $level ? $plans->label( $level ) : '',
				'is_subscriber' => '' !== $level,
				'expires'       => $expires,
				'expires_human' => $expires ? date_i18n( get_option( 'date_format' ), $expires ) : '',
				'source'        => $access->source(),
				'subscribe_url' => manacore_subs_url(),
			)
		);
	}

	/**
	 * Whether the current user can access a specific post.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function access( $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error(
				'manacore_subs_not_found',
				__( 'نوشته یافت نشد.', 'manacore' ),
				array( 'status' => 404 )
			);
		}

		$required = Access::instance()->required_level( $post_id );

		return rest_ensure_response(
			array(
				'post_id'       => $post_id,
				'required'      => $required,
				'required_label' => $required ? Plans::instance()->label( $required ) : '',
				'can_access'    => manacore_user_can_access( $post_id ),
				'level'         => Access::instance()->user_level(),
				'subscribe_url' => manacore_subs_url(),
			)
		);
	}

	/**
	 * Grant a level to a user.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function grant( $request ) {
		$user_id = absint( $request->get_param( 'user_id' ) );
		$level   = (string) $request->get_param( 'level' );
		$days    = absint( $request->get_param( 'days' ) );

		if ( ! get_userdata( $user_id ) ) {
			return new \WP_Error(
				'manacore_subs_no_user',
				__( 'کاربر یافت نشد.', 'manacore' ),
				array( 'status' => 404 )
			);
		}

		if ( ! Plans::instance()->exists( $level ) ) {
			return new \WP_Error(
				'manacore_subs_bad_level',
				__( 'سطح اشتراک نامعتبر است.', 'manacore' ),
				array( 'status' => 400 )
			);
		}

		$expires = $days ? time() + ( $days * DAY_IN_SECONDS ) : 0;

		Meta::instance()->grant(
			$user_id,
			$level,
			$expires,
			'manual',
			(string) $request->get_param( 'note' )
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'user_id' => $user_id,
				'level'   => $level,
				'expires' => $expires,
			)
		);
	}

	/**
	 * Revoke a manual grant.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function revoke( $request ) {
		$user_id = absint( $request->get_param( 'user_id' ) );

		Meta::instance()->revoke( $user_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'user_id' => $user_id,
			)
		);
	}
}
