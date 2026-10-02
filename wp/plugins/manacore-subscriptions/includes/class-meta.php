<?php
/**
 * Per-post level field, user-profile grants and the grant API.
 *
 * @package ManaCore\Subs
 */

namespace ManaCore\Subs;

defined( 'ABSPATH' ) || exit;

/**
 * Meta.
 */
class Meta {

	use Singleton;

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_filter( 'manacore_meta_schema', array( $this, 'extend_schema' ) );
		add_action( 'init', array( $this, 'register_meta' ), 20 );

		add_action( 'show_user_profile', array( $this, 'profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_profile' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile' ) );

		add_filter( 'manage_users_columns', array( $this, 'user_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'user_column_value' ), 10, 3 );
	}

	/* ---------------------------------------------------------------------
	 * Post meta
	 * ------------------------------------------------------------------ */

	/**
	 * Add the level selector right after the core premium checkbox.
	 *
	 * @param array $schema Core meta schema.
	 * @return array
	 */
	public function extend_schema( $schema ) {
		$choices = array( '' => __( 'پیش‌فرض تنظیمات', 'manacore' ) );

		foreach ( manacore_subs_levels() as $slug => $level ) {
			$choices[ $slug ] = $level['label'];
		}

		$field = array(
			'label'       => __( 'حداقل سطح اشتراک', 'manacore' ),
			'type'        => 'select',
			'choices'     => $choices,
			'description' => __( 'تنها کاربران دارای این سطح (یا بالاتر) به لینک‌های ویژه دسترسی دارند.', 'manacore' ),
		);

		foreach ( $schema as $group_key => $group ) {
			if ( ! isset( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
				continue;
			}

			if ( ! array_key_exists( 'manacore_is_premium', $group['fields'] ) ) {
				continue;
			}

			$field['post_types'] = isset( $group['fields']['manacore_is_premium']['post_types'] )
				? $group['fields']['manacore_is_premium']['post_types']
				: array_keys( manacore_post_types() );

			$fields = array();

			foreach ( $group['fields'] as $key => $definition ) {
				$fields[ $key ] = $definition;

				if ( 'manacore_is_premium' === $key ) {
					$fields['manacore_sub_level'] = $field;
				}
			}

			$schema[ $group_key ]['fields'] = $fields;

			return $schema;
		}

		return $schema;
	}

	/**
	 * Register the post meta key for REST/editor access.
	 */
	public function register_meta() {
		if ( ! function_exists( 'manacore_post_types' ) ) {
			return;
		}

		foreach ( array_keys( manacore_post_types() ) as $post_type ) {
			register_post_meta(
				$post_type,
				'manacore_sub_level',
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_key',
					'auth_callback'     => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * Grant API
	 * ------------------------------------------------------------------ */

	/**
	 * Grant (or extend) a manual subscription level.
	 *
	 * @param int    $user_id User id.
	 * @param string $level   Level slug.
	 * @param int    $expires Expiry timestamp (0 = unlimited).
	 * @param string $source  Where it came from.
	 * @param string $note    Optional admin note.
	 * @return bool
	 */
	public function grant( $user_id, $level, $expires = 0, $source = 'manual', $note = '' ) {
		$user_id = absint( $user_id );
		$level   = sanitize_key( (string) $level );

		if ( ! $user_id || ! Plans::instance()->exists( $level ) ) {
			return false;
		}

		$keys     = manacore_subs_meta_keys();
		$previous = (string) get_user_meta( $user_id, $keys['level'], true );

		update_user_meta( $user_id, $keys['level'], $level );
		update_user_meta( $user_id, $keys['expires'], absint( $expires ) );
		update_user_meta( $user_id, $keys['source'], sanitize_key( $source ) );

		if ( '' !== $note ) {
			update_user_meta( $user_id, $keys['note'], sanitize_text_field( $note ) );
		}

		Access::instance()->flush( $user_id );

		/**
		 * Fires after a level was granted.
		 *
		 * @param int    $user_id  User id.
		 * @param string $level    New level.
		 * @param int    $expires  Expiry timestamp.
		 * @param string $previous Previous level.
		 */
		do_action( 'manacore_subs_granted', $user_id, $level, absint( $expires ), $previous );
		do_action( 'manacore_subs_level_changed', $user_id );

		return true;
	}

	/**
	 * Revoke a manual grant.
	 *
	 * @param int $user_id User id.
	 * @return bool
	 */
	public function revoke( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return false;
		}

		foreach ( manacore_subs_meta_keys() as $key ) {
			delete_user_meta( $user_id, $key );
		}

		Access::instance()->flush( $user_id );

		/**
		 * Fires after a manual grant was revoked.
		 *
		 * @param int $user_id User id.
		 */
		do_action( 'manacore_subs_revoked', $user_id );
		do_action( 'manacore_subs_level_changed', $user_id );

		return true;
	}

	/* ---------------------------------------------------------------------
	 * User profile
	 * ------------------------------------------------------------------ */

	/**
	 * Render the grant fields on the user profile screen.
	 *
	 * @param \WP_User $user User being edited.
	 */
	public function profile_fields( $user ) {
		if ( ! current_user_can( 'manacore_manage_subscriptions' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$keys    = manacore_subs_meta_keys();
		$level   = (string) get_user_meta( $user->ID, $keys['level'], true );
		$expires = (int) get_user_meta( $user->ID, $keys['expires'], true );
		$note    = (string) get_user_meta( $user->ID, $keys['note'], true );
		$woo     = Woo::instance()->user_level( $user->ID );
		$date    = $expires ? gmdate( 'Y-m-d', $expires ) : '';
		?>
		<h2><?php esc_html_e( 'اشتراک ManaCore', 'manacore' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="manacore_sub_level"><?php esc_html_e( 'سطح اشتراک (دستی)', 'manacore' ); ?></label></th>
				<td>
					<select name="manacore_sub_level" id="manacore_sub_level">
						<option value=""><?php esc_html_e( '— بدون اشتراک دستی —', 'manacore' ); ?></option>
						<?php foreach ( manacore_subs_levels() as $slug => $definition ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $level, $slug ); ?>>
								<?php echo esc_html( $definition['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'اعطای دستی مستقل از ووکامرس عمل می‌کند و بالاترین سطح میان این دو اعمال می‌شود.', 'manacore' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="manacore_sub_expires"><?php esc_html_e( 'تاریخ انقضا', 'manacore' ); ?></label></th>
				<td>
					<input type="date" name="manacore_sub_expires" id="manacore_sub_expires" value="<?php echo esc_attr( $date ); ?>" />
					<p class="description"><?php esc_html_e( 'خالی بگذارید تا اشتراک بدون محدودیت زمانی باشد.', 'manacore' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="manacore_sub_note"><?php esc_html_e( 'یادداشت', 'manacore' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" name="manacore_sub_note" id="manacore_sub_note" value="<?php echo esc_attr( $note ); ?>" />
				</td>
			</tr>
			<?php if ( $woo ) : ?>
				<tr>
					<th><?php esc_html_e( 'اشتراک ووکامرس', 'manacore' ); ?></th>
					<td>
						<strong><?php echo esc_html( Plans::instance()->label( $woo ) ); ?></strong>
						<p class="description"><?php esc_html_e( 'این سطح از اشتراک فعال ووکامرس خوانده شده و در این صفحه قابل ویرایش نیست.', 'manacore' ); ?></p>
					</td>
				</tr>
			<?php endif; ?>
		</table>
		<?php
		wp_nonce_field( 'manacore_subs_profile', 'manacore_subs_nonce' );
	}

	/**
	 * Persist the profile grant fields.
	 *
	 * @param int $user_id User id.
	 */
	public function save_profile( $user_id ) {
		if ( ! current_user_can( 'manacore_manage_subscriptions' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$nonce = isset( $_POST['manacore_subs_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['manacore_subs_nonce'] ) )
			: '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'manacore_subs_profile' ) ) {
			return;
		}

		$level = isset( $_POST['manacore_sub_level'] )
			? sanitize_key( wp_unslash( $_POST['manacore_sub_level'] ) )
			: '';

		if ( '' === $level ) {
			$this->revoke( $user_id );
			return;
		}

		$raw_date = isset( $_POST['manacore_sub_expires'] )
			? sanitize_text_field( wp_unslash( $_POST['manacore_sub_expires'] ) )
			: '';

		$expires = 0;

		if ( $raw_date ) {
			$parsed = strtotime( $raw_date . ' 23:59:59 UTC' );

			if ( $parsed ) {
				$expires = (int) $parsed;
			}
		}

		$note = isset( $_POST['manacore_sub_note'] )
			? sanitize_text_field( wp_unslash( $_POST['manacore_sub_note'] ) )
			: '';

		$this->grant( $user_id, $level, $expires, 'manual', $note );
	}

	/* ---------------------------------------------------------------------
	 * Users list column
	 * ------------------------------------------------------------------ */

	/**
	 * Add the subscription column.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function user_column( $columns ) {
		$columns['manacore_sub'] = __( 'اشتراک', 'manacore' );

		return $columns;
	}

	/**
	 * Render the subscription column value.
	 *
	 * @param string $output      Current output.
	 * @param string $column_name Column key.
	 * @param int    $user_id     User id.
	 * @return string
	 */
	public function user_column_value( $output, $column_name, $user_id ) {
		if ( 'manacore_sub' !== $column_name ) {
			return $output;
		}

		$level = Access::instance()->user_level( $user_id );

		if ( '' === $level ) {
			return '<span class="manacore-pill is-off">' . esc_html__( 'ندارد', 'manacore' ) . '</span>';
		}

		$html    = '<span class="manacore-pill is-on">' . esc_html( Plans::instance()->label( $level ) ) . '</span>';
		$expires = Access::instance()->expiry( $user_id );

		if ( $expires ) {
			$html .= '<br /><small>' . esc_html( date_i18n( 'Y/m/d', $expires ) ) . '</small>';
		}

		return $html;
	}
}
